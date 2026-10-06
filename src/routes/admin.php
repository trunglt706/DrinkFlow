<?php

use Illuminate\Support\Facades\Route;

// Admin Authentication & Password Recovery Routes
Route::get('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'loginPage'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.page');
Route::post('/admin/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('admin.login');
Route::post('/admin/login/two-factor/cancel', [\App\Http\Controllers\Admin\AuthController::class, 'cancelTwoFactorLogin'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.two-factor.cancel');
// Public Agent registration: pending account + email verification, approved later by a Superadmin.
Route::get('/admin/register', [\App\Http\Controllers\Admin\RegistrationController::class, 'create'])->name('admin.register.page');
Route::post('/admin/register', [\App\Http\Controllers\Admin\RegistrationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('admin.register');
Route::get('/admin/register/pending', [\App\Http\Controllers\Admin\RegistrationController::class, 'pending'])->name('admin.register.pending');
Route::post('/admin/register/resend', [\App\Http\Controllers\Admin\RegistrationController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('admin.register.resend');
Route::get('/admin/register/verify/{admin}/{hash}', [\App\Http\Controllers\Admin\RegistrationController::class, 'verify'])
    ->whereNumber('admin')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('admin.register.verify');
// Invited Agent activation: choose the sign-in value, then the account becomes active.
Route::get('/admin/activate/{admin}/{hash}', [\App\Http\Controllers\Admin\ActivationController::class, 'form'])
    ->whereNumber('admin')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('admin.activate.form');
Route::post('/admin/activate/{admin}/{hash}', [\App\Http\Controllers\Admin\ActivationController::class, 'activate'])
    ->whereNumber('admin')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('admin.activate');
Route::get('/admin/forgot-password',[\App\Http\Controllers\Admin\AuthController::class, 'forgotPasswordPage'])
    ->middleware('throttle:admin-forgot-password')
    ->name('admin.forgot-password.page');
Route::post('/admin/forgot-password', [\App\Http\Controllers\Admin\AuthController::class, 'sendResetOtp'])
    ->middleware('throttle:admin-forgot-password')
    ->name('admin.forgot-password.send');
Route::get('/admin/verify-otp', [\App\Http\Controllers\Admin\AuthController::class, 'verifyOtpPage'])
    ->middleware('throttle:admin-verify-otp')
    ->name('admin.verify-otp.page');
Route::post('/admin/verify-otp', [\App\Http\Controllers\Admin\AuthController::class, 'verifyOtp'])
    ->middleware('throttle:admin-verify-otp')
    ->name('admin.verify-otp.submit');
Route::get('/admin/reset-password', [\App\Http\Controllers\Admin\AuthController::class, 'resetPasswordPage'])
    ->middleware('throttle:admin-reset-password')
    ->name('admin.reset-password.page');
Route::post('/admin/reset-password', [\App\Http\Controllers\Admin\AuthController::class, 'resetPassword'])
    ->middleware('throttle:admin-reset-password')
    ->name('admin.reset-password.submit');
Route::get('/admin', [\App\Http\Controllers\Admin\AuthController::class, 'landing'])->middleware('auth:admin')->name('admin.landing');
Route::post('/admin/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->middleware(['auth:admin', 'throttle:admin-login'])->name('admin.logout');
Route::middleware('auth:admin')->prefix('admin/profile')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\ProfileController::class, 'show'])->name('admin.profile');
    Route::get('/avatar', [\App\Http\Controllers\Admin\ProfileController::class, 'avatar'])->name('admin.profile.avatar.show');
    Route::patch('/', [\App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('admin.profile.update');
    Route::post('/avatar', [\App\Http\Controllers\Admin\ProfileController::class, 'uploadAvatar'])->name('admin.profile.avatar');
    Route::patch('/password', [\App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->middleware('throttle:5,1')->name('admin.profile.password');
    Route::patch('/two-factor', [\App\Http\Controllers\Admin\ProfileController::class, 'updateTwoFactor'])->middleware('throttle:5,1')->name('admin.profile.two-factor');
});

// The Agent's own rooms (ownership + quota). Declared before /admin/{room}; these slugs are reserved (Room::RESERVED_SLUGS).
Route::middleware('auth:admin')->prefix('admin/rooms')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\RoomController::class, 'index'])->name('admin.rooms.index');
    Route::get('/create', [\App\Http\Controllers\Admin\RoomController::class, 'create'])->name('admin.rooms.create');
    Route::post('/', [\App\Http\Controllers\Admin\RoomController::class, 'store'])->middleware('throttle:20,1')->name('admin.rooms.store');
    Route::get('/{room}/edit', [\App\Http\Controllers\Admin\RoomController::class, 'edit'])->name('admin.rooms.edit');
    Route::put('/{room}', [\App\Http\Controllers\Admin\RoomController::class, 'update'])->name('admin.rooms.update');
    Route::post('/{room}/archive', [\App\Http\Controllers\Admin\RoomController::class, 'archive'])->name('admin.rooms.archive');
    Route::post('/{room}/restore', [\App\Http\Controllers\Admin\RoomController::class, 'restore'])->name('admin.rooms.restore');
});

// The Agent's subscription: package, quota, upgrade/downgrade, cancellation and history.
Route::middleware('auth:admin')->prefix('admin/subscription')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\SubscriptionController::class, 'show'])->name('admin.subscription.show');
    Route::post('/change', [\App\Http\Controllers\Admin\SubscriptionController::class, 'change'])->middleware('throttle:10,1')->name('admin.subscription.change');
    Route::post('/upgrade/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancelUpgrade'])->middleware('throttle:10,1')->name('admin.subscription.upgrade.cancel');
    Route::post('/scheduled/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancelScheduled'])->middleware('throttle:10,1')->name('admin.subscription.scheduled.cancel');
    Route::post('/cancel', [\App\Http\Controllers\Admin\SubscriptionController::class, 'cancel'])->middleware('throttle:10,1')->name('admin.subscription.cancel');
    Route::post('/resume', [\App\Http\Controllers\Admin\SubscriptionController::class, 'resume'])->middleware('throttle:10,1')->name('admin.subscription.resume');
});

// The Agent's platform billing history (invoices and payments of its own account).
Route::get('/admin/billing', [\App\Http\Controllers\Admin\BillingController::class, 'index'])->middleware('auth:admin')->name('admin.billing.index');
Route::get('/admin/billing/invoices/{invoice}/pdf', [\App\Http\Controllers\Admin\BillingController::class, 'download'])->whereNumber('invoice')->middleware(['auth:admin', 'throttle:20,1'])->name('admin.billing.download');
Route::get('/admin/billing/invoices/{invoice}/pay', [\App\Http\Controllers\Admin\BillingController::class, 'pay'])->whereNumber('invoice')->middleware(['auth:admin', 'throttle:10,1'])->name('admin.billing.pay');

Route::middleware(['auth:admin', 'admin.room', 'room.subscription'])
    ->prefix('admin/{room}')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'page'])->name('admin.dashboard.page');
    Route::get('/manage', [\App\Http\Controllers\Admin\DashboardController::class, 'manage'])->name('admin.manage.page');
    Route::get('/dashboard/data', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/socket-token', \App\Http\Controllers\Admin\SocketTokenController::class)->middleware('json.only')->name('admin.socket-token');
    Route::post('/notifications/read-all', [\App\Http\Controllers\Admin\NotificationController::class, 'markAllRead'])->name('admin.notifications.read-all');
    Route::post('/notifications/broadcast', [\App\Http\Controllers\Admin\NotificationController::class, 'broadcast'])->middleware('throttle:admin-bulk')->name('admin.notifications.broadcast');
    Route::post('/sponsor-honors/recalculate', [\App\Http\Controllers\Admin\SponsorHonorController::class, 'recalculate'])->middleware('throttle:sponsor-honors-recalculate')->name('admin.sponsor-honors.recalculate');

    // Dedicated Standalone Page Views
    Route::get('/campaigns/list', [\App\Http\Controllers\Admin\CampaignController::class, 'page'])->name('admin.campaigns.page');
    Route::get('/orders/manage', [\App\Http\Controllers\Admin\OrderController::class, 'page'])->name('admin.orders.page');
    Route::get('/debts/ledger', [\App\Http\Controllers\Admin\DebtController::class, 'page'])->name('admin.debts.page');
    Route::get('/room-users/directory', [\App\Http\Controllers\Admin\RoomUserController::class, 'page'])->name('admin.room-users.page');
    Route::get('/settings/general', [\App\Http\Controllers\Admin\RoomSettingsController::class, 'page'])->name('admin.settings.page');
    Route::get('/notification-channels/integrations', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'page'])->name('admin.notification-channels.page');
    Route::get('/reports/analytics', [\App\Http\Controllers\Admin\ReportController::class, 'page'])->name('admin.reports.page');
    Route::get('/audit/logs', [\App\Http\Controllers\Admin\AuditController::class, 'page'])->name('admin.audit.page');

    // Campaign CRUD & Lifecycle API
    Route::get('/campaigns', [\App\Http\Controllers\Admin\CampaignController::class, 'index'])->name('admin.campaigns.index');
    Route::get('/campaigns/create', [\App\Http\Controllers\Admin\CampaignController::class, 'create'])->name('admin.campaigns.create');
    Route::get('/campaigns/previous-menus', [\App\Http\Controllers\Admin\CampaignController::class, 'previousMenus'])->name('admin.campaigns.previous-menus');
    Route::get('/campaigns/{campaign}/edit', [\App\Http\Controllers\Admin\CampaignController::class, 'edit'])->name('admin.campaigns.edit');
    Route::get('/campaigns/{campaign}/export/{dataset}', [\App\Http\Controllers\Admin\CampaignController::class, 'exportDetail'])->middleware('throttle:admin-export')->name('admin.campaigns.export-detail');
    Route::get('/campaigns/{campaign}/info', [\App\Http\Controllers\Admin\CampaignController::class, 'showInfo'])->name('admin.campaigns.info');
    Route::get('/campaigns/{campaign}/orders', [\App\Http\Controllers\Admin\CampaignController::class, 'showOrders'])->name('admin.campaigns.orders');
    Route::post('/campaigns/{campaign}/confirm-debts-paid', [\App\Http\Controllers\Admin\CampaignController::class, 'confirmDebtsPaid'])->middleware('throttle:admin-bulk')->name('admin.campaigns.confirm-debts-paid');
    Route::get('/campaigns/{campaign}/menu', [\App\Http\Controllers\Admin\CampaignController::class, 'showMenu'])->name('admin.campaigns.menu');
    Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignController::class, 'show'])->name('admin.campaigns.show');
    Route::get('/orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('admin.orders.index');
    Route::get('/orders/aggregate', [\App\Http\Controllers\Admin\CampaignController::class, 'aggregate'])->name('admin.orders.aggregate');
    Route::get('/orders/aggregate/export', [\App\Http\Controllers\Admin\CampaignController::class, 'exportAggregate'])->middleware('throttle:admin-export')->name('admin.orders.aggregate.export');
    Route::post('/orders/on-behalf', [\App\Http\Controllers\Admin\OrderController::class, 'storeOnBehalf'])->middleware('throttle:30,1')->name('admin.orders.on-behalf');
    Route::get('/orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'show'])->name('admin.orders.show');
    Route::patch('/orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'update'])->name('admin.orders.update');
    Route::put('/orders/{order}/items', [\App\Http\Controllers\Admin\OrderController::class, 'updateItems'])->middleware('throttle:30,1')->name('admin.orders.items.update');
    Route::patch('/orders/{order}/status', [\App\Http\Controllers\Admin\OrderController::class, 'updateStatus'])->name('admin.orders.status');
    Route::post('/orders/bulk-status', [\App\Http\Controllers\Admin\OrderController::class, 'bulkStatus'])->middleware('throttle:admin-bulk')->name('admin.orders.bulk-status');
    Route::post('/orders/bulk-cancel', [\App\Http\Controllers\Admin\OrderController::class, 'bulkCancel'])->middleware('throttle:admin-bulk')->name('admin.orders.bulk-cancel');
    Route::post('/orders/{order}/cancel', [\App\Http\Controllers\Admin\OrderController::class, 'cancel'])->name('admin.orders.cancel');
    Route::post('/orders/{order}/unlock', [\App\Http\Controllers\Admin\OrderController::class, 'unlock'])->name('admin.orders.unlock');
    Route::delete('/orders/{order}', [\App\Http\Controllers\Admin\OrderController::class, 'destroy'])->name('admin.orders.destroy');
    Route::post('/orders/{order}/confirm-payment', [\App\Http\Controllers\Admin\OrderController::class, 'confirmPayment'])->name('admin.orders.confirm-payment');
    Route::post('/campaigns', [\App\Http\Controllers\Admin\CampaignController::class, 'store'])->name('admin.campaigns.store');
    Route::post('/campaigns/menu-images', [\App\Http\Controllers\Admin\CampaignController::class, 'uploadImage'])->name('admin.campaigns.menu-images.store');
    Route::patch('/campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignController::class, 'update'])->name('admin.campaigns.update');
    Route::delete('/campaigns/{campaign}', [\App\Http\Controllers\Admin\CampaignController::class, 'destroy'])->name('admin.campaigns.destroy');
    Route::post('/campaigns/{campaign}/activate', [\App\Http\Controllers\Admin\CampaignController::class, 'activate'])->name('admin.campaigns.activate');
    Route::post('/campaigns/{campaign}/extend-deadline', [\App\Http\Controllers\Admin\CampaignController::class, 'extendDeadline'])->name('admin.campaigns.extend-deadline');
    Route::patch('/campaigns/{campaign}/payment-account', [\App\Http\Controllers\Admin\CampaignController::class, 'updatePaymentAccount'])->name('admin.campaigns.payment-account');
    Route::post('/campaigns/{campaign}/lock-ordering', [\App\Http\Controllers\Admin\CampaignController::class, 'lockOrdering'])->name('admin.campaigns.lock-ordering');
    Route::post('/campaigns/{campaign}/unlock-ordering', [\App\Http\Controllers\Admin\CampaignController::class, 'unlockOrdering'])->name('admin.campaigns.unlock-ordering');
    Route::post('/campaigns/{campaign}/mark-delivering', [\App\Http\Controllers\Admin\CampaignController::class, 'markDelivering'])->name('admin.campaigns.mark-delivering');
    Route::post('/campaigns/{campaign}/resend-notification', [\App\Http\Controllers\Admin\CampaignController::class, 'resendNotification'])->middleware('throttle:campaign-resend-notification')->name('admin.campaigns.resend-notification');
    Route::post('/campaigns/{campaign}/cancel', [\App\Http\Controllers\Admin\CampaignController::class, 'cancel'])->name('admin.campaigns.cancel');
    Route::post('/campaigns/{campaign}/archive', [\App\Http\Controllers\Admin\CampaignController::class, 'archive'])->name('admin.campaigns.archive');
    Route::post('/campaigns/{campaign}/duplicate', [\App\Http\Controllers\Admin\CampaignController::class, 'duplicate'])->name('admin.campaigns.duplicate');
    Route::post('/campaigns/{campaign}/split-bill', [\App\Http\Controllers\Admin\CampaignController::class, 'splitBill'])->name('admin.campaigns.split-bill');
    Route::post('/campaigns/{campaign}/items', [\App\Http\Controllers\Admin\CampaignController::class, 'storeItem'])->name('admin.campaign-items.store');
    Route::patch('/campaigns/{campaign}/items-batch-status', [\App\Http\Controllers\Admin\CampaignController::class, 'batchUpdateItemStatus'])->name('admin.campaign-items.batch-status');
    Route::patch('/campaigns/{campaign}/items/{item}', [\App\Http\Controllers\Admin\CampaignController::class, 'updateItem'])->name('admin.campaign-items.update');
    Route::patch('/campaigns/{campaign}/items/{item}/status', [\App\Http\Controllers\Admin\CampaignController::class, 'toggleItemStatus'])->name('admin.campaign-items.status');
    Route::delete('/campaigns/{campaign}/items/{item}', [\App\Http\Controllers\Admin\CampaignController::class, 'archiveItem'])->name('admin.campaign-items.archive');
    Route::post('/campaigns/{campaign}/items/{item}/toppings', [\App\Http\Controllers\Admin\CampaignController::class, 'storeTopping'])->name('admin.campaign-item-toppings.store');
    Route::post('/campaigns/{campaign}/items/{item}/sizes', [\App\Http\Controllers\Admin\CampaignController::class, 'storeSize'])->name('admin.campaign-item-sizes.store');
    Route::patch('/campaigns/{campaign}/items/{item}/toppings/{option}', [\App\Http\Controllers\Admin\CampaignController::class, 'updateTopping'])->name('admin.campaign-item-toppings.update');
    Route::patch('/campaigns/{campaign}/items/{item}/sizes/{option}', [\App\Http\Controllers\Admin\CampaignController::class, 'updateSize'])->name('admin.campaign-item-sizes.update');
    Route::delete('/campaigns/{campaign}/items/{item}/toppings/{option}', [\App\Http\Controllers\Admin\CampaignController::class, 'deleteTopping'])->name('admin.campaign-item-toppings.delete');
    Route::delete('/campaigns/{campaign}/items/{item}/sizes/{option}', [\App\Http\Controllers\Admin\CampaignController::class, 'deleteSize'])->name('admin.campaign-item-sizes.delete');
    Route::get('/campaigns/{campaign}/close-summary', [\App\Http\Controllers\Admin\CampaignController::class, 'closeSummary'])->name('admin.campaigns.close-summary');
    Route::post('/campaigns/{campaign}/close',[\App\Http\Controllers\Admin\CampaignController::class, 'close'])->name('admin.campaigns.close');
    Route::post('/crawler/preview', [\App\Http\Controllers\Admin\CrawlerController::class, 'preview'])
        ->middleware('throttle:crawler-preview')
        ->name('admin.crawler.preview');
    Route::post('/campaigns/{campaign}/crawler/import', [\App\Http\Controllers\Admin\CrawlerController::class, 'import'])->name('admin.crawler.import');

    // Data Gateway Converter API
    Route::get('/data-gateway/config', [\App\Http\Controllers\Admin\DataGatewayController::class, 'config'])->name('admin.data-gateway.config');
    Route::post('/data-gateway/generate-prompt', [\App\Http\Controllers\Admin\DataGatewayController::class, 'generatePrompt'])
        ->middleware('throttle:crawler-preview')
        ->name('admin.data-gateway.generate-prompt');
    Route::post('/data-gateway/analyze', [\App\Http\Controllers\Admin\DataGatewayController::class, 'analyze'])
        ->middleware('throttle:crawler-preview')
        ->name('admin.data-gateway.analyze');
    Route::get('/debts', [\App\Http\Controllers\Admin\DebtController::class, 'index'])->name('admin.debts.index');
    Route::get('/debts/export', [\App\Http\Controllers\Admin\DebtController::class, 'export'])->middleware('throttle:admin-export')->name('admin.debts.export');
    Route::post('/debts/settle', [\App\Http\Controllers\Admin\DebtController::class, 'settle'])->middleware('throttle:admin-bulk')->name('admin.debts.settle');
    Route::post('/debts/remind', [\App\Http\Controllers\Admin\DebtController::class, 'remind'])->middleware('throttle:admin-bulk')->name('admin.debts.remind');
    Route::post('/debts/payment-requests/{paymentRequest}/approve', [\App\Http\Controllers\Admin\DebtController::class, 'approveRequest'])->whereNumber('paymentRequest')->name('admin.debts.payment-requests.approve');
    Route::post('/debts/payment-requests/{paymentRequest}/reject', [\App\Http\Controllers\Admin\DebtController::class, 'rejectRequest'])->whereNumber('paymentRequest')->name('admin.debts.payment-requests.reject');
    Route::get('/debts/{debt}', [\App\Http\Controllers\Admin\DebtController::class, 'show'])->name('admin.debts.show');
    Route::post('/debts/{debt}/payments', [\App\Http\Controllers\Admin\DebtController::class, 'pay'])->name('admin.debts.pay');
    Route::post('/debts/{debt}/approve', [\App\Http\Controllers\Admin\DebtController::class, 'approve'])->name('admin.debts.approve');
    Route::post('/debts/{debt}/adjust', [\App\Http\Controllers\Admin\DebtController::class, 'adjust'])->name('admin.debts.adjust');
    Route::patch('/debts/{debt}/status', [\App\Http\Controllers\Admin\DebtController::class, 'status'])->name('admin.debts.status');
    Route::get('/room-users', [\App\Http\Controllers\Admin\RoomUserController::class, 'index'])->name('admin.room-users.index');
    Route::post('/room-users', [\App\Http\Controllers\Admin\RoomUserController::class, 'store'])->middleware('throttle:admin-bulk')->name('admin.room-users.store');
    Route::get('/room-users/{roomUser}', [\App\Http\Controllers\Admin\RoomUserController::class, 'show'])->name('admin.room-users.show');
    Route::patch('/room-users/{roomUser}/status', [\App\Http\Controllers\Admin\RoomUserController::class, 'status'])->name('admin.room-users.status');
    Route::post('/room-users/bulk-action', [\App\Http\Controllers\Admin\RoomUserController::class, 'bulkAction'])->middleware('throttle:admin-bulk')->name('admin.room-users.bulk-action');
    Route::post('/room-users/{roomUser}/restore', [\App\Http\Controllers\Admin\RoomUserController::class, 'restore'])->name('admin.room-users.restore');
    Route::delete('/room-users/{roomUser}', [\App\Http\Controllers\Admin\RoomUserController::class, 'destroy'])->name('admin.room-users.destroy');
    Route::post('/room-users/{roomUser}/devices/{device}/revoke', [\App\Http\Controllers\Admin\RoomUserController::class, 'revokeDevice'])->name('admin.room-user-devices.revoke');
    Route::get('/payment-accounts', [\App\Http\Controllers\Admin\PaymentAccountController::class, 'index'])->name('admin.payment-accounts.index');
    Route::post('/payment-accounts', [\App\Http\Controllers\Admin\PaymentAccountController::class, 'store'])->name('admin.payment-accounts.store');
    Route::patch('/payment-accounts/{account}', [\App\Http\Controllers\Admin\PaymentAccountController::class, 'update'])->name('admin.payment-accounts.update');
    Route::delete('/payment-accounts/{account}', [\App\Http\Controllers\Admin\PaymentAccountController::class, 'destroy'])->name('admin.payment-accounts.destroy');
    Route::get('/payment-accounts/{account}/qr', [\App\Http\Controllers\Admin\PaymentAccountController::class, 'qr'])->name('admin.payment-accounts.qr');
    Route::get('/settings', [\App\Http\Controllers\Admin\RoomSettingsController::class, 'show'])->name('admin.settings.show');
    Route::patch('/settings', [\App\Http\Controllers\Admin\RoomSettingsController::class, 'update'])->name('admin.settings.update');
    Route::get('/notification-channels', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'index'])->name('admin.notification-channels.index');
    Route::post('/notification-channels', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'store'])->name('admin.notification-channels.store');
    Route::get('/notification-channels/{channel}', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'show'])->name('admin.notification-channels.show');
    Route::patch('/notification-channels/{channel}', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'update'])->name('admin.notification-channels.update');
    Route::post('/notification-channels/{channel}/test', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'test'])->name('admin.notification-channels.test');
    Route::delete('/notification-channels/{channel}', [\App\Http\Controllers\Admin\NotificationChannelController::class, 'destroy'])->name('admin.notification-channels.destroy');
    Route::get('/reports', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/reports/export', [\App\Http\Controllers\Admin\ReportController::class, 'export'])->middleware('throttle:admin-export')->name('admin.reports.export');
    Route::get('/audit', [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('admin.audit.index');
});
