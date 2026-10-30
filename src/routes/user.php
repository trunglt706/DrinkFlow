<?php

use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])
    ->middleware('throttle:user-auth-google')
    ->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])
    ->middleware('throttle:user-auth-google')
    ->name('auth.google.callback');
Route::post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/');
})->middleware('throttle:user-auth-logout')->name('logout');

// Chỉ đăng ký ở môi trường local: đăng nhập nhanh bằng user đầu tiên để test.
// Yêu cầu thêm: chỉ chấp nhận truy cập trực tiếp từ localhost (127.0.0.1), ngay cả khi
// APP_ENV bị cấu hình nhầm thành "local" trên một máy chủ có thể truy cập từ mạng ngoài.
if (app()->environment('local')) {
    Route::get('/dev/login', function (\Illuminate\Http\Request $request) {
        abort_unless($request->ip() === '127.0.0.1', 404);

        $user = \App\Models\GlobalUser::query()->orderBy('id')->firstOrFail();
        \Illuminate\Support\Facades\Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('user.me.dashboard');
    })->name('dev.login');
}

// room.ip: the room's allowed / blocked IP lists; room.email_domain: the room's allowed account email domains.
Route::get('/rooms/{room}', [\App\Http\Controllers\User\RoomController::class, 'show'])->middleware(['room.ip', 'room.subscription'])->name('user.rooms.show');
Route::get('/rooms/{room}/join', \App\Http\Controllers\User\JoinPageController::class)
    ->middleware(['room.ip', 'room.email_domain', 'room.subscription'])
    ->name('user.rooms.join.show');
Route::post('/rooms/{room}/join', [\App\Http\Controllers\User\RoomController::class, 'join'])
    ->middleware(['throttle:room-join', 'room.ip', 'room.email_domain', 'room.subscription'])
    ->name('user.rooms.join');

Route::middleware(['room.ip', 'global.user', 'room.user', 'room.subscription'])->group(function () {
    Route::get('/rooms/{room}/dashboard', \App\Http\Controllers\User\DashboardController::class)->name('user.dashboard');
    Route::get('/rooms/{room}/orders', [\App\Http\Controllers\User\OrderController::class, 'index'])->name('user.orders.index');
    Route::get('/rooms/{room}/campaigns', [\App\Http\Controllers\User\CampaignController::class, 'index'])->name('user.campaigns.index');
    Route::get('/rooms/{room}/campaigns/{campaign}', [\App\Http\Controllers\User\CampaignController::class, 'show'])->name('user.campaigns.show');
    Route::get('/rooms/{room}/campaigns/{campaign}/details', [\App\Http\Controllers\User\CampaignController::class, 'details'])->name('user.campaigns.details');
    Route::get('/rooms/{room}/campaigns/{campaign}/favorite-items', [\App\Http\Controllers\User\CampaignController::class, 'favoriteItems'])->name('user.campaigns.favorite-items');
    Route::post('/rooms/{room}/campaigns/{campaign}/decline', [\App\Http\Controllers\User\CampaignController::class, 'decline'])->name('user.campaigns.decline');
    Route::post('/rooms/{room}/campaigns/{campaign}/rejoin', [\App\Http\Controllers\User\CampaignController::class, 'rejoin'])->name('user.campaigns.rejoin');
    Route::get('/rooms/{room}/campaigns/{campaign}/order', \App\Http\Controllers\User\CampaignOrderPageController::class)->name('user.campaigns.order-page');
    Route::post('/rooms/{room}/campaigns/{campaign}/cart', [\App\Http\Controllers\User\CampaignController::class, 'addToCart'])->middleware('throttle:60,1')->name('user.campaigns.cart.store');
    Route::patch('/rooms/{room}/campaigns/{campaign}/cart/{index}', [\App\Http\Controllers\User\CampaignController::class, 'updateCartProxy'])->name('user.campaigns.cart.proxy');
    Route::delete('/rooms/{room}/campaigns/{campaign}/cart', [\App\Http\Controllers\User\CampaignController::class, 'clearCart'])->name('user.campaigns.cart.clear');
    Route::delete('/rooms/{room}/campaigns/{campaign}/cart/{index}', [\App\Http\Controllers\User\CampaignController::class, 'removeFromCart'])->name('user.campaigns.cart.remove');
    Route::post('/rooms/{room}/campaigns/{campaign}/orders', [\App\Http\Controllers\User\OrderController::class, 'store'])->name('user.orders.store');
    Route::get('/rooms/{room}/members/lookup', [\App\Http\Controllers\User\CampaignController::class, 'lookupMember'])
        ->middleware('throttle:room-member-lookup')
        ->name('user.room-members.lookup');
    Route::get('/rooms/{room}/orders/{order}', [\App\Http\Controllers\User\OrderController::class, 'show'])->name('user.orders.show');
    Route::get('/rooms/{room}/orders/{order:code}/view', \App\Http\Controllers\User\OrderPageController::class)->name('user.orders.page');
    Route::get('/rooms/{room}/orders/{order}/payment', [\App\Http\Controllers\User\OrderController::class, 'payment'])->name('user.orders.payment');
    Route::post('/rooms/{room}/orders/{order}/confirm-payment', [\App\Http\Controllers\User\OrderController::class, 'confirmPayment'])->name('user.orders.confirm-payment');
    Route::get('/rooms/{room}/debts', [\App\Http\Controllers\User\DebtController::class, 'index'])->name('user.debts.index');
    Route::post('/rooms/{room}/debts/confirm-payment', [\App\Http\Controllers\User\DebtController::class, 'confirmPayment'])->name('user.debts.confirm-payment');
    Route::post('/rooms/{room}/debts/snooze-reminders', [\App\Http\Controllers\User\DebtController::class, 'snoozeReminders'])->middleware('throttle:10,1')->name('user.debts.snooze-reminders');
    // Member rates an item of their received order (1–5 stars, short comment).
    Route::post('/rooms/{room}/order-items/{item}/rating', [\App\Http\Controllers\User\OrderItemRatingController::class, 'store'])->whereNumber('item')->middleware(['json.only', 'throttle:30,1'])->name('user.order-items.rating');
    Route::get('/rooms/{room}/analytics',[\App\Http\Controllers\User\AnalyticsController::class, 'room'])->name('user.analytics.room');
    Route::get('/rooms/{room}/socket-token', \App\Http\Controllers\User\SocketTokenController::class)->middleware('json.only')->name('user.socket-token');
    Route::get('/rooms/{room}/notifications', [\App\Http\Controllers\User\RoomNotificationController::class, 'index'])->name('user.rooms.notifications');
    Route::get('/rooms/{room}/profile', [\App\Http\Controllers\User\RoomProfileController::class, 'index'])->name('user.rooms.profile');
    // The viewer's TOP 1-3 sponsor medals of the current year, month by month.
    Route::get('/rooms/{room}/achievements', [\App\Http\Controllers\User\AchievementController::class, 'index'])->name('user.rooms.achievements');
    // The viewer's year recap (story + shareable card): past years, and the current year during the recap season.
    Route::get('/rooms/{room}/recap/{year}', [\App\Http\Controllers\User\YearRecapController::class, 'show'])->whereNumber('year')->middleware('throttle:60,1')->name('user.rooms.recap');
    Route::get('/rooms/{room}/guides', [\App\Http\Controllers\User\RoomGuideController::class, 'index'])->name('user.rooms.guides');
    Route::get('/rooms/{room}/guides/{slug}', [\App\Http\Controllers\User\RoomGuideController::class, 'show'])->name('user.rooms.guides.show');
    // Stand-alone birthday celebration page of the room: every member whose birthday is within 3 days.
    Route::get('/rooms/{room}/birthday', [\App\Http\Controllers\User\BirthdayController::class, 'show'])->middleware('throttle:60,1')->name('user.rooms.birthday');
    // Stand-alone occasion pages (/1-1, /tet, /8-3, /20-10, /19-11, /25-12), reachable while the occasion is shown.
    Route::get('/rooms/{room}/{occasion}', [\App\Http\Controllers\User\OccasionController::class, 'show'])
        ->where('occasion', \App\Enums\RoomOccasion::slugPattern())->middleware('throttle:60,1')->name('user.rooms.occasion');
    // Wishes wall of the event pages: list, write (limited per member and occurrence), heart, delete one's own wish.
    $eventWishType = implode('|', \App\Actions\Room\SendRoomEventNotificationAction::types());
    Route::get('/rooms/{room}/events/{type}/wishes', [\App\Http\Controllers\User\EventWishController::class, 'index'])->where('type', $eventWishType)->middleware(['json.only', 'throttle:60,1'])->name('user.event-wishes.index');
    Route::post('/rooms/{room}/events/{type}/wishes', [\App\Http\Controllers\User\EventWishController::class, 'store'])->where('type', $eventWishType)->middleware(['json.only', 'throttle:10,1'])->name('user.event-wishes.store');
    Route::post('/rooms/{room}/event-wishes/{wish}/heart', [\App\Http\Controllers\User\EventWishController::class, 'heart'])->whereNumber('wish')->middleware(['json.only', 'throttle:60,1'])->name('user.event-wishes.heart');
    Route::delete('/rooms/{room}/event-wishes/{wish}', [\App\Http\Controllers\User\EventWishController::class, 'destroy'])->whereNumber('wish')->middleware(['json.only', 'throttle:20,1'])->name('user.event-wishes.destroy');
    // Sponsor draw landing page (read-only); link token + campaign membership checked on every request.
    Route::get('/rooms/{room}/sponsor-draws/{token}', [\App\Http\Controllers\User\SponsorDrawController::class, 'show'])->where('token', '[A-Za-z0-9]{40,128}')->middleware('throttle:60,1')->name('user.sponsor-draws.show');
    Route::get('/rooms/{room}/sponsor-draws/{token}/state', [\App\Http\Controllers\User\SponsorDrawController::class, 'state'])->where('token', '[A-Za-z0-9]{40,128}')->middleware(['json.only', 'throttle:120,1'])->name('user.sponsor-draws.state');
    Route::get('/rooms/{room}/sponsor-draws/{token}/socket-token', [\App\Http\Controllers\User\SponsorDrawController::class, 'socketToken'])->where('token', '[A-Za-z0-9]{40,128}')->middleware(['json.only', 'throttle:30,1'])->name('user.sponsor-draws.socket-token');
});

Route::middleware(['global.user'])->group(function () {
    Route::get('/me/socket-token', \App\Http\Controllers\User\GlobalSocketTokenController::class)->middleware('json.only')->name('user.me.socket-token');
    // Global User Portal Pages (/me/*) - Available to all users
    Route::get('/me', \App\Http\Controllers\User\Global\DashboardController::class)->name('user.me.dashboard');
    Route::get('/me/profile', \App\Http\Controllers\User\Global\ProfileController::class)->name('user.me.profile');
    Route::match(['post', 'patch'], '/me/profile', [\App\Http\Controllers\User\Global\ProfileController::class, 'update'])->name('user.me.profile.update');
    Route::post('/me/rooms/join', [\App\Http\Controllers\User\Global\RoomsController::class, 'joinByCode'])
        ->middleware('throttle:room-join')
        ->name('user.me.rooms.join');
    Route::get('/me/notifications', [\App\Http\Controllers\User\Global\NotificationController::class, 'index'])->name('user.me.notifications');
    Route::post('/me/notifications/read-all', [\App\Http\Controllers\User\Global\NotificationController::class, 'markAllRead'])->name('user.me.notifications.read-all');
    Route::get('/me/devices', [\App\Http\Controllers\User\Global\ProfileController::class, 'devices'])->name('user.me.devices');
    Route::get('/blocked', [\App\Http\Controllers\User\Global\BlockedAccountController::class, 'show'])->name('user.blocked');
    Route::post('/blocked/appeal', [\App\Http\Controllers\User\Global\BlockedAccountController::class, 'appeal'])->name('user.blocked.appeal');
    Route::post('/me/devices/logout/{session_id}', [\App\Http\Controllers\User\Global\ProfileController::class, 'logoutDevice'])->name('user.me.devices.logout');
    Route::post('/me/devices/logout-all', [\App\Http\Controllers\User\Global\ProfileController::class, 'logoutOtherDevices'])->name('user.me.devices.logout-all');
    Route::post('/me/account/delete', [\App\Http\Controllers\User\Global\ProfileController::class, 'deleteAccount'])->name('user.me.account.delete');

    Route::get('/me/feedback', [\App\Http\Controllers\User\Global\ProfileController::class, 'feedback'])->name('user.me.feedback');
    Route::post('/me/feedback', [\App\Http\Controllers\User\Global\ProfileController::class, 'storeFeedback'])
        ->middleware('throttle:feedback-submission')
        ->name('user.me.feedback.store');

    Route::get('/me/guides', [\App\Http\Controllers\User\Global\GuideController::class, 'index'])->name('user.me.guides');
    Route::get('/me/guides/{slug}', [\App\Http\Controllers\User\Global\GuideController::class, 'show'])->name('user.me.guides.show');

    // Mini games ("Trò chơi"): catalogue, the owner's rooms per game, room dashboard and scoring rule sets.
    Route::get('/me/games', [\App\Http\Controllers\User\Global\GamesController::class, 'index'])->name('user.me.games');
    Route::prefix('/me/games/{game:slug}')->name('user.me.games.')->scopeBindings()->group(function (): void {
        Route::get('/', [\App\Http\Controllers\User\Global\GamesController::class, 'show'])->name('show');
        Route::get('/rooms/create', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'create'])->name('rooms.create');
        Route::post('/rooms', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'store'])->middleware('throttle:20,1')->name('rooms.store');
        Route::get('/rooms/{gameRoom}', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'show'])->whereNumber('gameRoom')->name('rooms.show');
        Route::middleware('throttle:60,1')->whereNumber('gameRoom')->group(function (): void {
            Route::post('/rooms/{gameRoom}/start', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'start'])->name('rooms.start');
            Route::post('/rooms/{gameRoom}/finish', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'finish'])->name('rooms.finish');
            Route::post('/rooms/{gameRoom}/rounds', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'openRound'])->name('rooms.rounds.store');
            Route::post('/rooms/{gameRoom}/rule-version', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'applyRuleVersion'])->name('rooms.rule-version');
            Route::post('/rooms/{gameRoom}/public-link', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'regenerateLink'])->name('rooms.public-link.regenerate');
            Route::delete('/rooms/{gameRoom}/public-link', [\App\Http\Controllers\User\Global\GameRoomsController::class, 'revokeLink'])->name('rooms.public-link.revoke');
            Route::post('/rooms/{gameRoom}/players', [\App\Http\Controllers\User\Global\GamePlayersController::class, 'store'])->name('rooms.players.store');
            Route::patch('/rooms/{gameRoom}/players/{player}', [\App\Http\Controllers\User\Global\GamePlayersController::class, 'update'])->whereNumber('player')->name('rooms.players.update');
            Route::post('/rooms/{gameRoom}/results', [\App\Http\Controllers\User\Global\GameResultsController::class, 'store'])->middleware('json.only')->name('rooms.results.store');
            Route::post('/rooms/{gameRoom}/results/{result}/void', [\App\Http\Controllers\User\Global\GameResultsController::class, 'void'])->whereNumber('result')->name('rooms.results.void');
        });
        Route::get('/rule-sets', [\App\Http\Controllers\User\Global\GameRuleSetsController::class, 'index'])->name('rule-sets.index');
        Route::get('/rule-sets/create', [\App\Http\Controllers\User\Global\GameRuleSetsController::class, 'create'])->name('rule-sets.create');
        Route::post('/rule-sets', [\App\Http\Controllers\User\Global\GameRuleSetsController::class, 'store'])->middleware('throttle:20,1')->name('rule-sets.store');
        Route::get('/rule-sets/{ruleSet}/edit', [\App\Http\Controllers\User\Global\GameRuleSetsController::class, 'edit'])->whereNumber('ruleSet')->name('rule-sets.edit');
        Route::put('/rule-sets/{ruleSet}', [\App\Http\Controllers\User\Global\GameRuleSetsController::class, 'update'])->whereNumber('ruleSet')->middleware('throttle:20,1')->name('rule-sets.update');
    });

    // Global User JSON API & Action Endpoints
    Route::get('/profile', [\App\Http\Controllers\User\Global\ProfileController::class, 'show'])->name('user.profile.show');
    Route::get('/notifications', [\App\Http\Controllers\User\Global\NotificationController::class, 'index'])->name('user.notifications.index');
    Route::patch('/notifications/{notification}/read', [\App\Http\Controllers\User\Global\NotificationController::class, 'read'])->name('user.notifications.read');

    // Room-Dependent Pages and Endpoints (Requires user to have joined at least 1 active room)
    Route::middleware(['user.has_rooms'])->group(function () {
        Route::get('/me/rooms', \App\Http\Controllers\User\Global\RoomsController::class)->name('user.me.rooms');
        Route::get('/me/orders', \App\Http\Controllers\User\Global\OrdersController::class)->middleware('user.active_room')->name('user.me.orders');
        Route::get('/me/statistics', \App\Http\Controllers\User\Global\AnalyticsController::class)->middleware('user.active_room')->name('user.me.statistics');
        Route::get('/me/statistics/export', [\App\Http\Controllers\User\Global\AnalyticsController::class, 'export'])
            ->middleware('throttle:10,1')
            ->middleware('user.active_room')->name('user.me.statistics.export');
        Route::get('/me/payments', [\App\Http\Controllers\User\Global\PaymentsController::class, 'index'])->middleware('user.active_room')->name('user.me.payments');
        Route::get('/me/payments/export', [\App\Http\Controllers\User\Global\PaymentsController::class, 'export'])
            ->middleware('throttle:10,1')
            ->middleware('user.active_room')->name('user.me.payments.export');

        Route::get('/rooms', [\App\Http\Controllers\User\Global\RoomsController::class, 'index'])->name('user.rooms.index');
        Route::get('/history', [\App\Http\Controllers\User\Global\OrdersController::class, 'api'])->middleware('user.active_room')->name('user.history.index');
        Route::get('/analytics', [\App\Http\Controllers\User\Global\AnalyticsController::class, 'global'])->middleware('user.active_room')->name('user.analytics.global');
    });
});
