<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SaveGameNotificationChannelRequest;
use App\Models\Game;
use App\Models\GameNotificationChannel;
use App\Models\GlobalUser;
use App\Services\Game\GameNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in user's notification channels of the Utilities area (where game results are sent).
 */
class GameNotificationChannelsController extends Controller
{
    public function __construct(private readonly GameNotificationService $notifications) {}

    /**
     * List of the user's channels.
     *
     * @param Request $request Current request.
     * @return View List page.
     */
    public function index(Request $request): View
    {
        $user = $this->currentGlobalUser($request);

        return view('user.global.games.channels', [
            'user' => $user,
            'channels' => $this->notifications->forOwner($user)->loadCount('rooms'),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    /**
     * New channel form. ?return={game slug} sends the user back to that game's create-room form after saving.
     *
     * @param Request $request Current request.
     * @return View Form page.
     */
    public function create(Request $request): View
    {
        return view('user.global.games.channel-form', [
            'user' => $this->currentGlobalUser($request),
            'channel' => null,
            'editable' => ['config' => [], 'secrets_configured' => []],
            'returnTo' => $this->returnGame($request),
            'breadcrumbs' => $this->breadcrumbs(['label' => __('games.channels.create_title'), 'url' => route('user.me.utilities.channels.create')]),
        ]);
    }

    /**
     * Create the channel.
     *
     * @param SaveGameNotificationChannelRequest $request Validated form.
     * @return RedirectResponse Channel list, or the create-room form it came from.
     */
    public function store(SaveGameNotificationChannelRequest $request): RedirectResponse
    {
        $this->notifications->save($this->currentGlobalUser($request), $request->channelData());
        $game = $this->returnGame($request);
        $target = $game !== null ? route('user.me.utilities.rooms.create', $game) : route('user.me.utilities.channels.index');

        return redirect($target)->with('status', __('games.flash.channel_created'));
    }

    /**
     * Edit form; credentials are never sent back, only flagged as configured.
     *
     * @param Request $request Current request.
     * @param GameNotificationChannel $channel Channel.
     * @return View Form page.
     */
    public function edit(Request $request, GameNotificationChannel $channel): View
    {
        $user = $this->authorizeOwner($request, $channel);

        return view('user.global.games.channel-form', [
            'user' => $user,
            'channel' => $channel,
            'editable' => $this->notifications->editable($channel),
            'returnTo' => null,
            'breadcrumbs' => $this->breadcrumbs(['label' => $channel->name, 'url' => route('user.me.utilities.channels.edit', $channel)]),
        ]);
    }

    /**
     * Save the channel.
     *
     * @param SaveGameNotificationChannelRequest $request Validated form (owner checked).
     * @param GameNotificationChannel $channel Channel.
     * @return RedirectResponse Channel list.
     */
    public function update(SaveGameNotificationChannelRequest $request, GameNotificationChannel $channel): RedirectResponse
    {
        $this->notifications->save($this->currentGlobalUser($request), $request->channelData(), $channel);

        return redirect()->route('user.me.utilities.channels.index')->with('status', __('games.flash.channel_updated'));
    }

    /**
     * Delete the channel (rooms using it stop sending).
     *
     * @param Request $request Current request.
     * @param GameNotificationChannel $channel Channel.
     * @return RedirectResponse Channel list.
     */
    public function destroy(Request $request, GameNotificationChannel $channel): RedirectResponse
    {
        $this->authorizeOwner($request, $channel);
        $this->notifications->delete($channel);

        return redirect()->route('user.me.utilities.channels.index')->with('status', __('games.flash.channel_deleted'));
    }

    /**
     * Send a test message.
     *
     * @param Request $request Current request.
     * @param GameNotificationChannel $channel Channel.
     * @return RedirectResponse Back with the outcome.
     */
    public function test(Request $request, GameNotificationChannel $channel): RedirectResponse
    {
        $this->authorizeOwner($request, $channel);

        return $this->notifications->sendTest($channel)
            ? back()->with('status', __('games.flash.channel_test_sent'))
            : back()->withErrors(['channel' => __('games.channels.errors.delivery_failed')]);
    }

    /**
     * Only the owner manages a channel.
     *
     * @param Request $request Current request.
     * @param GameNotificationChannel $channel Channel.
     * @return GlobalUser Signed-in owner.
     */
    private function authorizeOwner(Request $request, GameNotificationChannel $channel): GlobalUser
    {
        $user = $this->currentGlobalUser($request);
        Gate::forUser($user)->authorize('manage', $channel);

        return $user;
    }

    /**
     * Game slug to return to after creating a channel from the create-room form (only an existing, active game).
     *
     * @param Request $request Current request.
     * @return string|null Game slug.
     */
    private function returnGame(Request $request): ?string
    {
        $slug = $request->input('return');
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return Game::query()->where('slug', $slug)->where('is_active', true)->value('slug');
    }

    /**
     * Breadcrumbs: personal space › utilities › channels › last.
     *
     * @param array{label: string, url: string}|null $last Last crumb.
     * @return array<int, array{label: string, url: string}> Breadcrumbs.
     */
    private function breadcrumbs(?array $last = null): array
    {
        return array_values(array_filter([
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('games.page_title'), 'url' => route('user.me.utilities')],
            ['label' => __('games.channels.title'), 'url' => route('user.me.utilities.channels.index')],
            $last,
        ]));
    }
}
