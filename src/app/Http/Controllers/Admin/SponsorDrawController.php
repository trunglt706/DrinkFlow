<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageSponsorDrawRequest;
use App\Http\Requests\StoreSponsorDrawRequest;
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
 * Sponsor draws of a room (/admin/{room}/sponsor-draws): create a session, share its link, draw the turns,
 * confirm and notify, and review past sessions. The admin.room middleware has checked access to the room.
 */
class SponsorDrawController extends Controller
{
    public function __construct(private readonly SponsorDrawService $draws) {}

    /**
     * Sessions of the room and the creation form.
     */
    public function index(Room $room): View
    {
        return view('admin.sponsor-draws.index', [
            'room' => $room,
            'sessions' => SponsorDrawSession::query()->where('room_id', $room->id)
                ->with(['campaign:id,name', 'targetCampaign:id,name', 'createdBy:id,name'])->withCount('results')
                ->latest('id')->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)
                ->through(fn (SponsorDrawSession $session): SponsorDrawSession => $session->setRelation('room', $room)),
            'campaigns' => Campaign::query()->where('room_id', $room->id)->where('status', '!=', CampaignStatus::Cancelled->value)
                ->latest('id')->limit(100)->get(['id', 'name', 'status']),
            'targetCampaigns' => Campaign::query()->where('room_id', $room->id)
                ->whereIn('status', [CampaignStatus::Draft->value, CampaignStatus::Scheduled->value, CampaignStatus::Active->value])
                ->latest('id')->get(['id', 'name', 'status']),
            'maxWinners' => (int) config('sponsor_draw.max_winners', 50),
        ]);
    }

    /**
     * Create a session (snapshot frozen, link issued) and open its draw page.
     */
    public function store(StoreSponsorDrawRequest $request, Room $room): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->draws->create(
            $room,
            $this->admin($request),
            Campaign::query()->findOrFail((int) $data['campaign_id']),
            (int) $data['target_winner_count'],
            isset($data['target_campaign_id']) ? Campaign::query()->findOrFail((int) $data['target_campaign_id']) : null,
        );

        return redirect()->route('admin.sponsor-draws.show', [$room, $result['session']])
            ->with('status', __('sponsor_draw.flash.created'));
    }

    /**
     * Draw page of a session (controls, link, proof and delivery status).
     */
    public function show(Room $room, SponsorDrawSession $sponsorDraw): View
    {
        $this->ensureRoom($room, $sponsorDraw);
        $token = $this->draws->currentToken($sponsorDraw);

        return view('admin.sponsor-draws.show', [
            'room' => $room,
            'session' => $sponsorDraw->loadMissing(['campaign:id,name', 'targetCampaign:id,name', 'createdBy:id,name', 'confirmedBy:id,name']),
            'state' => $this->draws->state($sponsorDraw, true),
            'linkUrl' => $token !== null ? route('user.sponsor-draws.show', [$room, $token]) : null,
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
