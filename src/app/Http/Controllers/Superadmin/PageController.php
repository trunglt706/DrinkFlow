<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Enums\Permission;
use App\Services\Authorization\AgentDirectoryService;
use App\Services\Authorization\AgentScope;
use App\Http\Controllers\Controller;
use App\Enums\CampaignStatus;
use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\SecurityEvent;
use App\Models\Superadmin;
use App\Models\SystemNotificationChannel;
use App\Models\Version;
use App\Services\Notification\SuperadminNotificationService;
use App\Services\Notification\NotificationPresentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Handle the rooms operation.
     * @return View Result of the operation.
     */
    public function rooms(Request $request): View
    {
        $query = Room::query()->withCount(['roomUsers', 'campaigns', 'admins']);
        app(AgentScope::class)->applyToRooms($query, $request->user('superadmin'), Permission::RoomView);
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
        $status = $request->string('status')->toString();
        if ($status !== '') $query->where('status', $status);
        $sort = $request->string('sort')->toString();
        match ($sort) {
            'name' => $query->orderBy('name'),
            'members' => $query->orderByDesc('room_users_count'),
            'campaigns' => $query->orderByDesc('campaigns_count'),
            default => $query->latest(),
        };
        return view('superadmin.rooms', ['rooms' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(), 'filters' => compact('search', 'status', 'sort')]);
    }
    /**
     * Room profile shell (data loads from the room API). Scope-checked here too, so an out-of-scope
     * room ID is refused at once instead of rendering an empty page.
     *
     * @param Room $room Room from the URL.
     * @return View Room profile page.
     */
    public function room(Room $room): View
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $room);

        // Only the key goes to the page script: the room data itself loads from the (scoped) room API.
        return view('superadmin.room-detail', ['roomKey' => $room->slug]);
    }
    /**
     * Former Agent list: merged into Agents (superadmin.agents.index), kept as a permanent redirect for old
     * links and bookmarks, with the search and status filters carried over.
     *
     * @param Request $request Query string: q, status.
     * @return RedirectResponse Redirect to the Agent list.
     */
    public function admins(Request $request): RedirectResponse
    {
        return redirect()->route('superadmin.agents.index', array_filter($request->only(['q', 'status']), static fn ($value): bool => is_string($value) && $value !== ''), 301);
    }

    /**
     * Former Agent profile: merged into the Agent page (superadmin.agents.show), kept as a permanent redirect.
     * The scope is checked first, so an out-of-scope Agent ID is still refused here.
     *
     * @param Admin $admin Agent.
     * @return RedirectResponse Redirect to the Agent page.
     */
    public function admin(Admin $admin): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('view', $admin);

        return redirect()->route('superadmin.agents.show', $admin, 301);
    }

    /**
     * Handle the users operation.
     * @return View Result of the operation.
     */
    public function users(Request $request): View
    {
        $query = GlobalUser::query()->withCount('roomUsers')->latest();
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('normalized_name', 'like', "%".strtoupper($search)."%")->orWhere('email', 'like', "%{$search}%"));
        $status = \App\Enums\GlobalUserStatus::tryFrom($request->string('status')->toString())?->value ?? '';
        // Soft-deleted accounts only show up when explicitly filtered for.
        $status !== ''
            ? $query->where('status', $status)
            : $query->where('status', '!=', \App\Enums\GlobalUserStatus::Deleted->value);
        // Members of at least one room owned by the Agent; the Agent must be visible (agent.view scope).
        $agent = app(AgentDirectoryService::class)->visibleAgentId($request->user('superadmin'), $request->integer('agent') ?: null);
        if ($agent !== null) $query->memberOfAgentRooms($agent);
        return view('superadmin.users', ['users' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(), 'filters' => compact('search', 'status', 'agent')]);
    }
    /**
     * Handle the user operation.
     * @return View Result of the operation.
     */
    public function user(): View
    {
        return view('superadmin.user-detail');
    }
    /**
     * List campaigns across every room, filtered by keyword, room and status.
     *
     * Unknown status values and non-numeric room ids are ignored rather than matched literally.
     *
     * @param Request $request Query string: q, agent (owner of the rooms), room_id, status.
     * @return View Campaign registry page with the room list for the room filter.
     */
    public function campaigns(Request $request): View
    {
        $query = Campaign::query()->with('room:id,name,slug')->withCount('orders')->latest();
        app(AgentScope::class)->applyToRoomOwned($query, $request->user('superadmin'), Permission::RoomView);
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('restaurant', 'like', "%{$search}%"));
        $roomId = filter_var($request->query('room_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        if ($roomId !== null) $query->where('room_id', $roomId);
        // Campaigns of the rooms owned by one Agent (the room scope above still applies).
        $agentId = filter_var($request->query('agent'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        if ($agentId !== null) $query->whereIn('room_id', Room::query()->where('owner_admin_id', $agentId)->select('id'));
        $status = CampaignStatus::tryFrom($request->string('status')->toString());
        if ($status !== null) $query->where('status', $status->value);

        return view('superadmin.campaigns', [
            'campaigns' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(),
            // Every visible room, tagged with its owner: the Room select narrows itself to the chosen Agent.
            'rooms' => app(AgentScope::class)->applyToRooms(Room::query(), $request->user('superadmin'), Permission::RoomView)->orderBy('name')->get(['id', 'name', 'owner_admin_id']),
            'filters' => ['search' => $search, 'room_id' => $roomId, 'agent' => $agentId, 'status' => $status?->value ?? ''],
        ]);
    }
    /**
     * Render the notifications page: the signed-in account's own inbox, then the system alert channels.
     *
     * The inbox lists the superadmin_notifications addressed to the signed-in Superadmin (the same source as
     * the header bell), never another account's. Both lists paginate independently (`inbox_page` / `page`).
     *
     * @param Request $request Query string: inbox_status (all|unread|read), inbox_q, date_from / date_to (inbox), q and channel_type (channels).
     * @param SuperadminNotificationService $notifications Superadmin notification service.
     * @param NotificationPresentationService $presentation Localized title/body/icon for each notification.
     * @return View Notifications page.
     */
    public function notifications(Request $request, SuperadminNotificationService $notifications, NotificationPresentationService $presentation): View
    {
        /** @var \App\Models\Superadmin $admin */
        $admin = $request->user('superadmin');
        $inboxStatus = in_array($request->query('inbox_status'), [SuperadminNotificationService::FILTER_UNREAD, SuperadminNotificationService::FILTER_READ], true)
            ? (string) $request->query('inbox_status')
            : '';
        $inboxSearch = trim($request->string('inbox_q')->toString());
        // Received-date window of the inbox (date-range picker: date_from / date_to); unreadable dates are ignored.
        $inboxFrom = \App\Support\Helpers\DateRangeHelper::parseCarbonSafe($request->string('date_from')->toString() ?: null)?->startOfDay();
        $inboxTo = \App\Support\Helpers\DateRangeHelper::parseCarbonSafe($request->string('date_to')->toString() ?: null)?->endOfDay();
        $inbox = $notifications
            ->paginateFor($admin, ['status' => $inboxStatus, 'search' => $inboxSearch, 'from' => $inboxFrom, 'to' => $inboxTo], \App\Constants\Pagination::ADMIN_PER_PAGE, 'inbox_page')
            ->withQueryString();
        $inboxPresentations = [];
        foreach ($inbox as $notification) {
            $inboxPresentations[$notification->id] = $presentation->present($notification);
        }

        $query = SystemNotificationChannel::query()->latest();
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('type', 'like', "%{$search}%"));
        $channelType = $request->string('channel_type')->toString();
        $channelType = array_key_exists($channelType, SystemNotificationChannel::TYPES) ? $channelType : '';
        if ($channelType !== '') $query->where('type', $channelType);

        return view('superadmin.notifications', [
            'channels' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(),
            'filters' => compact('search', 'inboxStatus', 'inboxSearch', 'channelType') + [
                'inboxFrom' => $inboxFrom?->toDateString() ?? '', 'inboxTo' => $inboxTo?->toDateString() ?? '',
            ],
            'inbox' => $inbox,
            'inboxPresentations' => $inboxPresentations,
            'inboxUnreadCount' => $notifications->unreadCountFor($admin),
        ]);
    }
    /**
     * Render the system settings & maintenance page.
     *
     * @return View Page with the reset confirmation phrase and the local disks the storage settings can pick.
     */
    public function system(): View
    {
        return view('superadmin.system', [
            'resetPhrase' => \App\Actions\Superadmin\ResetSystemAction::CONFIRMATION_PHRASE,
            'storageDisks' => app(\App\Services\System\SystemConfigService::class)->localDisks(),
        ]);
    }
    /**
     * List audit logs written through the admin console only (room admins and superadmins).
     *
     * End-user and system entries are excluded. The event filter matches an exact event key chosen
     * from the events that actually exist for admin actors, and the actor filter only accepts
     * admin/superadmin.
     *
     * @param Request $request Query string: event, target_type, date_from, date_to.
     * @return View Audit log page with translated event options.
     */
    public function audit(Request $request): View
    {
        // Superadmin actions only: room admins' activity is shown in each room's own audit page.
        $actorTypes = [AuditLog::ACTOR_SUPERADMIN];
        $query = AuditLog::query()
            ->whereIn('actor_type', $actorTypes)
            ->with(['room:id,name', 'actorSuperadmin:id,name,email'])
            ->latest('created_at')
            ->latest('id');
        $event = trim($request->string('event')->toString());
        if ($event !== '') $query->where('event', $event);
        $targetType = trim($request->string('target_type')->toString());
        if ($targetType !== '') $query->where('target_type', $targetType);
        // Actor filter: one Superadmin's actions only (ignored unless it is a positive ID).
        $actorId = $request->integer('actor_id');
        if ($actorId > 0) $query->where('actor_id', $actorId);
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        if ($dateFrom !== '') $query->whereDate('created_at', '>=', $dateFrom);
        if ($dateTo !== '') $query->whereDate('created_at', '<=', $dateTo);

        $eventOptions = AuditLog::query()
            ->whereIn('actor_type', $actorTypes)
            ->distinct()
            ->pluck('event')
            ->mapWithKeys(fn (string $key): array => [$key => AuditLog::labelForEvent($key)])
            ->sort();
        $targetOptions = AuditLog::query()
            ->whereIn('actor_type', $actorTypes)
            ->distinct()
            ->pluck('target_type')
            ->filter()
            ->mapWithKeys(fn (string $type): array => [$type => (new AuditLog(['target_type' => $type]))->targetLabel()])
            ->sort();

        // Superadmins that appear in the log (deleted accounts drop out of the list but their rows stay).
        $actorOptions = Superadmin::query()
            ->whereIn('id', AuditLog::query()->whereIn('actor_type', $actorTypes)->whereNotNull('actor_id')->distinct()->select('actor_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('superadmin.audit', [
            'audits' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(),
            'eventOptions' => $eventOptions,
            'targetOptions' => $targetOptions,
            'actorOptions' => $actorOptions,
            'filters' => ['event' => $event, 'target_type' => $targetType, 'actor_id' => $actorId > 0 ? $actorId : null, 'date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }
    /**
     * Handle the security operation.
     * @return View Result of the operation.
     */
    public function security(Request $request): View
    {
        $query = SecurityEvent::query()->with('room:id,name')->latest('created_at');
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('type', 'like', "%{$search}%")->orWhere('ip_address', 'like', "%{$search}%"));
        $severity = $request->string('severity')->toString();
        if ($severity !== '') $query->where('severity', $severity);
        $actorType = $request->string('actor_type')->toString();
        if ($actorType !== '') $query->where('actor_type', $actorType);
        return view('superadmin.security', [
            'events' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(),
            'filters' => ['search' => $search, 'severity' => $severity, 'actor_type' => $actorType],
        ]);
    }
    /**
     * Handle the socket operation.
     * @return View Result of the operation.
     */
    public function socket(): View
    {
        return view('superadmin.socket');
    }
    /**
     * Handle the queue operation.
     * @return View Result of the operation.
     */
    public function queue(Request $request): View
    {
        $query = DB::table('failed_jobs')->latest('failed_at');
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('queue', 'like', "%{$search}%")->orWhere('uuid', 'like', "%{$search}%"));
        return view('superadmin.queue', ['jobs' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(), 'filters' => compact('search')]);
    }
    /**
     * Handle the versions operation.
     * @return View Result of the operation.
     */
    public function versions(Request $request): View
    {
        $query = Version::query()->latest('release_date');
        $search = trim($request->string('q')->toString());
        if ($search !== '') $query->where(fn ($q) => $q->where('version', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
        // Release-date window; an unreadable date is ignored.
        $from = \App\Support\Helpers\DateRangeHelper::parseCarbonSafe($request->string('date_from')->toString() ?: null)?->toDateString();
        $to = \App\Support\Helpers\DateRangeHelper::parseCarbonSafe($request->string('date_to')->toString() ?: null)?->toDateString();
        if ($from !== null) $query->whereDate('release_date', '>=', $from);
        if ($to !== null) $query->whereDate('release_date', '<=', $to);
        return view('superadmin.versions', [
            'versions' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(),
            'filters' => ['search' => $search, 'date_from' => $from ?? '', 'date_to' => $to ?? ''],
        ]);
    }

    /**
     * Feedback moderation queue: approve or take down user feedback.
     *
     * @param Request $request Incoming request (filters: status, rating, q).
     * @return View Feedback moderation view.
     */
    public function feedbacks(Request $request): View
    {
        $status = (string) $request->query('status', FeedbackStatus::Inactive->value);
        $rating = $request->integer('rating');
        $search = trim($request->string('q')->toString());

        $query = Feedback::query()->with('globalUser:id,name,email')->latest('created_at')->latest('id');
        if (FeedbackStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }
        if ($rating >= 1 && $rating <= 5) {
            $query->where('rating', $rating);
        }
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('content', 'like', "%{$search}%")->orWhere('user_display_name', 'like', "%{$search}%"));
        }
        // Feedback from members of the rooms owned by one (visible) Agent.
        $agent = app(AgentDirectoryService::class)->visibleAgentId($request->user('superadmin'), $request->integer('agent') ?: null);
        if ($agent !== null) {
            $query->whereHas('globalUser', static fn ($users) => $users->memberOfAgentRooms($agent));
        }

        return view('superadmin.feedbacks', [
            'feedbacks' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString(),
            'filters' => ['status' => $status, 'rating' => $rating >= 1 && $rating <= 5 ? $rating : null, 'search' => $search, 'agent' => $agent],
            'pendingCount' => Feedback::query()->where('status', FeedbackStatus::Inactive->value)->count(),
        ]);
    }
}
