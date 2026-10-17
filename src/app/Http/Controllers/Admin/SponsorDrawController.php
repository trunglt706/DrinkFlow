<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageSponsorDrawRequest;
use App\Http\Requests\ConfigureSponsorDrawRequest;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Room;
use App\Models\SponsorDrawSession;
use App\Services\Realtime\SocketTokenService;
use App\Services\SponsorDraw\SponsorDrawEventLog;
use App\Services\SponsorDraw\SponsorDrawService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Draws of a room (/admin/{room}/sponsor-draws): create a draft, save its settings (sponsors or shops), share its
 * link, draw the turns, confirm and notify, and review past sessions. The admin.room middleware has checked access to the room.
 */
class SponsorDrawController extends Controller
{
    public function __construct(private readonly SponsorDrawService $draws) {}

    /**
     * Sessions of the room; a new one is created from the "New session" button.
     */
    public function index(Room $room): View
    {
        return view('admin.sponsor-draws.index', [
            'room' => $room,
            'sessions' => SponsorDrawSession::query()->where('room_id', $room->id)
                ->with(['campaign:id,name', 'targetCampaign:id,name', 'createdBy:id,name'])->withCount('results')
                ->latest('id')->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)
                ->through(fn (SponsorDrawSession $session): SponsorDrawSession => $session->setRelation('room', $room)),
        ]);
    }

    /**
     * Create a draft session and open its draw page with the settings dialog.
     */
    public function store(ManageSponsorDrawRequest $request, Room $room): RedirectResponse
    {
        $session = $this->draws->createDraft($room, $this->admin($request));

        return redirect()->route('admin.sponsor-draws.show', [$room, $session])
            ->with('status', __('sponsor_draw.flash.draft_created'))
            ->with('open_settings', true);
    }

    /**
     * Save the settings of a session (draft, or ready before its first turn) and open its draw page.
     */
    public function settings(ConfigureSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): RedirectResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $targetId = $request->validated('target_campaign_id');
        $this->draws->configure(
            $sponsorDraw,
            $this->admin($request),
            $request->type(),
            (int) ($request->validated('target_winner_count') ?? 1),
            $targetId !== null ? Campaign::query()->findOrFail((int) $targetId) : null,
            $request->shopNames(),
        );

        return redirect()->route('admin.sponsor-draws.show', [$room, $sponsorDraw])
            ->with('status', __('sponsor_draw.flash.configured'));
    }

    /**
     * Draw page of a session (settings, controls, link, proof and delivery status).
     */
    public function show(Room $room, SponsorDrawSession $sponsorDraw): View
    {
        $this->ensureRoom($room, $sponsorDraw);
        $token = $this->draws->currentToken($sponsorDraw);
        $hasResults = $sponsorDraw->results()->exists();

        return view('admin.sponsor-draws.show', [
            'room' => $room,
            'session' => $sponsorDraw->loadMissing(['campaign:id,name', 'targetCampaign:id,name', 'createdBy:id,name', 'confirmedBy:id,name']),
            'state' => $this->draws->state($sponsorDraw, true),
            'linkUrl' => $token !== null ? route('user.sponsor-draws.show', [$room, $token]) : null,
            // Settings stay editable until the first turn is drawn.
            'canConfigure' => $sponsorDraw->status->canConfigure() && ! $hasResults,
            'targetCampaigns' => Campaign::query()->where('room_id', $room->id)
                ->whereIn('status', [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value, CampaignStatus::Active->value])
                ->latest('id')->get(['id', 'name', 'status']),
            'shopSuggestions' => $this->draws->shopSuggestions($room),
            'eligibleMembers' => $this->draws->eligibleMembersQuery($room)->count(),
            'currentShops' => $sponsorDraw->type === \App\Enums\SponsorDrawType::Shops
                ? $sponsorDraw->eligibleUsers()->orderBy('position')->pluck('display_name_snapshot')->all()
                : [],
            'maxWinners' => (int) config('sponsor_draw.max_winners', 50),
            'maxShops' => (int) config('sponsor_draw.max_shops', 100),
        ]);
    }

    /**
     * Current state and the events after `?after=` (reconnect / missed realtime push).
     */
    public function state(Request $request, Room $room, SponsorDrawSession $sponsorDraw, SponsorDrawEventLog $events): JsonResponse
    {
        $this->ensureRoom($room, $sponsorDraw);

        return response()->json(['data' => [
            'state' => $this->draws->state($sponsorDraw, true),
            'events' => $events->after($sponsorDraw, max(0, $request->integer('after'))),
        ]]);
    }

    /**
     * Draw the next turn (the backend chooses the winner).
     */
    public function draw(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): JsonResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $result = $this->draws->draw($sponsorDraw, $this->admin($request));

        return response()->json(['data' => [
            'draw_order' => $result->draw_order,
            'name' => $result->display_name_snapshot,
            'state' => $this->draws->state($sponsorDraw->refresh(), true),
        ]]);
    }

    /**
     * Issue a new access link (the old one stops working).
     */
    public function reissueLink(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): RedirectResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $this->draws->reissueLink($sponsorDraw, $this->admin($request));

        return back()->with('status', __('sponsor_draw.flash.link_issued'));
    }

    /**
     * Cancel a session that is not confirmed yet.
     */
    public function cancel(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): RedirectResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $this->draws->cancel($sponsorDraw, $this->admin($request), (string) $request->validated('reason'));

        return back()->with('status', __('sponsor_draw.flash.cancelled'));
    }

    /**
     * Confirm the result and send it to the room's notification channels.
     */
    public function confirm(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): RedirectResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $this->draws->confirm($sponsorDraw, $this->admin($request));

        return back()->with('status', __('sponsor_draw.flash.confirmed'));
    }

    /**
     * Take one candidate out of the list before the first turn (JSON: the new state).
     */
    public function removeEligible(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): JsonResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $session = $this->draws->removeEligible($sponsorDraw, $this->admin($request), (int) $request->validated('position'));

        return response()->json(['message' => __('sponsor_draw.flash.eligible_removed'), 'data' => ['state' => $this->draws->state($session, true)]]);
    }

    /**
     * Rebuild the list from the room's eligible members before the first turn (JSON: the new state).
     */
    public function syncEligible(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): JsonResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $session = $this->draws->syncEligible($sponsorDraw, $this->admin($request));

        return response()->json(['message' => __('sponsor_draw.flash.eligible_synced', ['count' => $session->eligible_count]), 'data' => ['state' => $this->draws->state($session, true)]]);
    }

    /**
     * Discard every drawn turn so the drawing starts over (not once confirmed).
     */
    public function reset(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): RedirectResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $this->draws->reset($sponsorDraw, $this->admin($request));

        return back()->with('status', __('sponsor_draw.flash.reset'));
    }

    /**
     * Send again the channels whose delivery failed.
     */
    public function resend(ManageSponsorDrawRequest $request, Room $room, SponsorDrawSession $sponsorDraw): RedirectResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        $count = $this->draws->resendFailed($sponsorDraw, $this->admin($request));

        return back()->with('status', __('sponsor_draw.flash.resent', ['count' => $count]));
    }

    /**
     * Verification record (snapshot, seed once revealed, results and hash chain) as a JSON download.
     */
    public function proof(Room $room, SponsorDrawSession $sponsorDraw): JsonResponse
    {
        $this->ensureRoom($room, $sponsorDraw);

        return response()->json($this->draws->proof($sponsorDraw), 200, [
            'Content-Disposition' => 'attachment; filename="sponsor-draw-'.$sponsorDraw->id.'-proof.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Realtime token of the session channel (only while the link is valid).
     */
    public function socketToken(Room $room, SponsorDrawSession $sponsorDraw, SocketTokenService $tokens): JsonResponse
    {
        $this->ensureRoom($room, $sponsorDraw);
        abort_unless($sponsorDraw->linkIsValid(), 410, __('sponsor_draw.errors.link_expired'));

        return response()->json(['data' => ['token' => $tokens->issueForDrawViewer($sponsorDraw)]]);
    }

    /**
     * A session is only reachable through its own room (no cross-room access by ID).
     */
    private function ensureRoom(Room $room, SponsorDrawSession $session): void
    {
        abort_unless((int) $session->room_id === (int) $room->id, 404);
    }

    private function admin(Request $request): Admin
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return $admin;
    }
}
