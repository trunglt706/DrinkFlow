<?php

use Illuminate\Support\Facades\Route;

// Superadmins sign in on their own guard; an admin session never grants access to /superadmin/*.
Route::get('/superadmin/login', [\App\Http\Controllers\Superadmin\AuthController::class, 'loginPage'])
    ->middleware('throttle:admin-login')
    ->name('superadmin.login.page');
Route::post('/superadmin/login', [\App\Http\Controllers\Superadmin\AuthController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('superadmin.login');
Route::post('/superadmin/logout', [\App\Http\Controllers\Superadmin\AuthController::class, 'logout'])
    ->middleware(['auth:superadmin', 'throttle:admin-login'])
    ->name('superadmin.logout');

Route::middleware(['auth:superadmin', 'superadmin', 'superadmin.mask'])->prefix('superadmin')->group(function () {
    // Reveal one masked email / phone number (SensitiveDataService token issued to this Superadmin).
    Route::post('/sensitive/reveal', [\App\Http\Controllers\Superadmin\SensitiveDataController::class, 'reveal'])->middleware('throttle:60,1')->name('superadmin.sensitive.reveal');
    Route::get('/', [\App\Http\Controllers\Superadmin\DashboardController::class, 'index'])->name('superadmin.dashboard');
    Route::get('/dashboard/analytics', [\App\Http\Controllers\Superadmin\DashboardController::class, 'analytics'])->name('superadmin.dashboard.analytics');
    Route::get('/dashboard/insights', [\App\Http\Controllers\Superadmin\DashboardController::class, 'insights'])->name('superadmin.dashboard.insights');
    Route::get('/dashboard/trends', [\App\Http\Controllers\Superadmin\DashboardController::class, 'trends'])->name('superadmin.dashboard.trends');
    // Internal Superadmin handbook (Markdown in resources/guide-content/superadmin, never served statically).
    Route::get('/guides', [\App\Http\Controllers\Superadmin\GuideController::class, 'index'])->name('superadmin.guides.index');
    Route::get('/guides/{slug}', [\App\Http\Controllers\Superadmin\GuideController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('superadmin.guides.show');
    Route::get('/rooms/page', [\App\Http\Controllers\Superadmin\PageController::class, 'rooms'])->middleware('permission:room.view')->name('superadmin.rooms.page');
    Route::get('/rooms', [\App\Http\Controllers\Superadmin\RoomController::class, 'index'])->middleware('permission:room.view')->name('superadmin.rooms.index');
    Route::post('/rooms', [\App\Http\Controllers\Superadmin\RoomController::class, 'store'])->middleware('permission:room.manage')->name('superadmin.rooms.store');
    Route::get('/rooms/{room}/page', [\App\Http\Controllers\Superadmin\PageController::class, 'room'])->middleware('permission:room.view')->name('superadmin.rooms.detail.page');
    Route::get('/rooms/{room}', [\App\Http\Controllers\Superadmin\RoomController::class, 'show'])->middleware('permission:room.view')->name('superadmin.rooms.show');
    Route::match(['put', 'patch'], '/rooms/{room}', [\App\Http\Controllers\Superadmin\RoomController::class, 'update'])->middleware('permission:room.manage')->name('superadmin.rooms.update');
    Route::patch('/rooms/{room}/status', [\App\Http\Controllers\Superadmin\RoomController::class, 'status'])->middleware('permission:room.manage')->name('superadmin.rooms.status');
    Route::delete('/rooms/{room}', [\App\Http\Controllers\Superadmin\RoomController::class, 'destroy'])->middleware('permission:room.manage')->name('superadmin.rooms.destroy');
    Route::get('/admins/page', [\App\Http\Controllers\Superadmin\PageController::class, 'admins'])->middleware('permission:agent.view')->name('superadmin.admins.page');
    Route::get('/admins', [\App\Http\Controllers\Superadmin\AdminController::class, 'index'])->middleware('permission:agent.view')->name('superadmin.admins.index');
    Route::post('/admins', [\App\Http\Controllers\Superadmin\AdminController::class, 'store'])->middleware('permission:agent.manage')->name('superadmin.admins.store');
    Route::get('/admins/{admin}/page', [\App\Http\Controllers\Superadmin\PageController::class, 'admin'])->middleware('permission:agent.view')->name('superadmin.admins.detail.page');
    Route::get('/admins/{admin}', [\App\Http\Controllers\Superadmin\AdminController::class, 'show'])->middleware('permission:agent.view')->name('superadmin.admins.show');
    Route::match(['put', 'patch'], '/admins/{admin}', [\App\Http\Controllers\Superadmin\AdminController::class, 'update'])->middleware('permission:agent.manage')->name('superadmin.admins.update');
    Route::patch('/admins/{admin}/status', [\App\Http\Controllers\Superadmin\AdminController::class, 'status'])->middleware('permission:agent.manage')->name('superadmin.admins.status');
    Route::put('/admins/{admin}/rooms', [\App\Http\Controllers\Superadmin\AdminController::class, 'rooms'])->middleware('permission:agent.manage')->name('superadmin.admins.rooms');
    Route::post('/admins/{admin}/reset-password', [\App\Http\Controllers\Superadmin\AdminController::class, 'resetPassword'])->middleware('permission:agent.manage')->name('superadmin.admins.reset-password');
    Route::delete('/admins/{admin}', [\App\Http\Controllers\Superadmin\AdminController::class, 'destroy'])->middleware('permission:agent.manage')->name('superadmin.admins.destroy');
    Route::get('/global-users/page', [\App\Http\Controllers\Superadmin\PageController::class, 'users'])->middleware('permission:global_user.view')->name('superadmin.global-users.page');
    Route::get('/global-users', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'index'])->middleware('permission:global_user.view')->name('superadmin.global-users.index');
    Route::get('/global-users/{globalUser}/page', [\App\Http\Controllers\Superadmin\PageController::class, 'user'])->middleware('permission:global_user.view')->name('superadmin.global-users.detail.page');
    Route::get('/global-users/{globalUser}', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'show'])->middleware('permission:global_user.view')->name('superadmin.global-users.show');
    Route::patch('/global-users/{globalUser}/status', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'status'])->middleware('permission:global_user.manage')->name('superadmin.global-users.status');
    Route::delete('/global-users/{globalUser}', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'destroy'])->middleware('permission:global_user.manage')->name('superadmin.global-users.destroy');
    Route::delete('/global-users/{globalUser}/memberships/{roomUser}', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'removeMembership'])->middleware('permission:global_user.manage')->name('superadmin.global-users.memberships.remove');
    Route::post('/global-users/{globalUser}/memberships/{roomUser}/devices/{device}/revoke', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'revokeDevice'])->middleware('permission:global_user.manage')->name('superadmin.global-users.devices.revoke');
    Route::post('/global-users/merge', [\App\Http\Controllers\Superadmin\GlobalUserController::class, 'merge'])->middleware('permission:global_user.manage')->name('superadmin.global-users.merge');
    Route::get('/campaigns/page', [\App\Http\Controllers\Superadmin\PageController::class, 'campaigns'])->middleware('permission:room.view')->name('superadmin.campaigns.page');
    Route::get('/campaigns', [\App\Http\Controllers\Superadmin\CampaignController::class, 'index'])->middleware('permission:room.view')->name('superadmin.campaigns.index');
    Route::post('/campaigns/{campaign}/force-close', [\App\Http\Controllers\Superadmin\CampaignController::class, 'forceClose'])->middleware('permission:room.manage')->name('superadmin.campaigns.force-close');
    Route::post('/campaigns/{campaign}/force-cancel', [\App\Http\Controllers\Superadmin\CampaignController::class, 'forceCancel'])->middleware('permission:room.manage')->name('superadmin.campaigns.force-cancel');
    Route::get('/notifications/page', [\App\Http\Controllers\Superadmin\PageController::class, 'notifications'])->middleware('permission:settings.view')->name('superadmin.notifications.page');
    Route::post('/admin-notifications/read-all', [\App\Http\Controllers\Superadmin\AdminNotificationController::class, 'markAllRead'])->name('superadmin.admin-notifications.read-all');
    Route::patch('/admin-notifications/{notification}/read', [\App\Http\Controllers\Superadmin\AdminNotificationController::class, 'read'])->whereNumber('notification')->name('superadmin.admin-notifications.read');
    Route::get('/notifications', [\App\Http\Controllers\Superadmin\NotificationController::class, 'index'])->middleware('permission:settings.view')->name('superadmin.notifications.index');
    Route::post('/notifications', [\App\Http\Controllers\Superadmin\NotificationController::class, 'store'])->middleware('permission:settings.manage')->name('superadmin.notifications.store');
    Route::get('/notifications/{channel}', [\App\Http\Controllers\Superadmin\NotificationController::class, 'show'])->middleware('permission:settings.view')->name('superadmin.notifications.show');
    Route::match(['put', 'patch'], '/notifications/{channel}', [\App\Http\Controllers\Superadmin\NotificationController::class, 'update'])->middleware('permission:settings.manage')->name('superadmin.notifications.update');
    Route::post('/notifications/{channel}/test', [\App\Http\Controllers\Superadmin\NotificationController::class, 'test'])->middleware('permission:settings.manage')->name('superadmin.notifications.test');
    Route::delete('/notifications/{channel}', [\App\Http\Controllers\Superadmin\NotificationController::class, 'destroy'])->middleware('permission:settings.manage')->name('superadmin.notifications.destroy');
    Route::get('/system/page', [\App\Http\Controllers\Superadmin\PageController::class, 'system'])->middleware('permission:settings.view')->name('superadmin.system.page');
    Route::get('/system', [\App\Http\Controllers\Superadmin\SystemController::class, 'index'])->middleware('permission:settings.view')->name('superadmin.system.index');
    Route::put('/system/settings', [\App\Http\Controllers\Superadmin\SystemController::class, 'settings'])->middleware('permission:settings.manage')->name('superadmin.system.settings');
    Route::get('/system/maintenance', [\App\Http\Controllers\Superadmin\SystemController::class, 'maintenance'])->middleware('permission:settings.view')->name('superadmin.system.maintenance');
    Route::put('/system/maintenance', [\App\Http\Controllers\Superadmin\SystemController::class, 'maintenance'])->middleware('permission:settings.manage')->name('superadmin.system.maintenance.update');
    Route::post('/system/reset', [\App\Http\Controllers\Superadmin\SystemController::class, 'reset'])->middleware('permission:settings.manage')->name('superadmin.system.reset');
    Route::put('/system/mail', [\App\Http\Controllers\Superadmin\SystemController::class, 'mailSettings'])->middleware('permission:settings.manage')->name('superadmin.system.mail');
    Route::put('/system/storage', [\App\Http\Controllers\Superadmin\SystemController::class, 'storageSettings'])->middleware('permission:settings.manage')->name('superadmin.system.storage');
    Route::post('/system/mail/test', [\App\Http\Controllers\Superadmin\SystemController::class, 'sendTestMail'])->middleware('throttle:5,1')->middleware('permission:settings.manage')->name('superadmin.system.mail-test');
    Route::get('/audit-logs/page', [\App\Http\Controllers\Superadmin\PageController::class, 'audit'])->middleware('permission:audit.view')->name('superadmin.audit.page');
    Route::get('/audit-logs', [\App\Http\Controllers\Superadmin\AuditController::class, 'index'])->middleware('permission:audit.view')->name('superadmin.audit-logs.index');
    Route::get('/security-events/page', [\App\Http\Controllers\Superadmin\PageController::class, 'security'])->middleware('permission:security.view')->name('superadmin.security.page');
    Route::get('/security-events', [\App\Http\Controllers\Superadmin\SecurityController::class, 'index'])->middleware('permission:security.view')->name('superadmin.security-events.index');
    Route::get('/socket/page', [\App\Http\Controllers\Superadmin\PageController::class, 'socket'])->middleware('permission:queue.view')->name('superadmin.socket.page');
    Route::get('/socket', [\App\Http\Controllers\Superadmin\SocketMonitoringController::class, 'index'])->middleware('permission:queue.view')->name('superadmin.socket.index');
    Route::get('/socket-token', \App\Http\Controllers\Superadmin\SocketTokenController::class)->middleware('json.only')->name('superadmin.socket-token');
    Route::get('/queue/page', [\App\Http\Controllers\Superadmin\PageController::class, 'queue'])->middleware('permission:queue.view')->name('superadmin.queue.page');
    Route::get('/queue/failed', [\App\Http\Controllers\Superadmin\QueueController::class, 'index'])->middleware('permission:queue.view')->name('superadmin.queue.failed.index');
    Route::post('/queue/failed/{failedJob}/retry', [\App\Http\Controllers\Superadmin\QueueController::class, 'retry'])->middleware('permission:queue.manage')->name('superadmin.queue.failed.retry');
    Route::delete('/queue/failed/{failedJob}', [\App\Http\Controllers\Superadmin\QueueController::class, 'forget'])->middleware('permission:queue.manage')->name('superadmin.queue.failed.forget');
    Route::get('/feedbacks/page', [\App\Http\Controllers\Superadmin\PageController::class, 'feedbacks'])->middleware('permission:feedback.view')->name('superadmin.feedbacks.page');
    Route::patch('/feedbacks/{feedback}/status', [\App\Http\Controllers\Superadmin\FeedbackController::class, 'status'])->middleware('permission:feedback.manage')->name('superadmin.feedbacks.status');
    Route::put('/feedbacks/{feedback}', [\App\Http\Controllers\Superadmin\FeedbackController::class, 'update'])->middleware('permission:feedback.manage')->name('superadmin.feedbacks.update');
    Route::delete('/feedbacks/{feedback}', [\App\Http\Controllers\Superadmin\FeedbackController::class, 'destroy'])->middleware('permission:feedback.manage')->name('superadmin.feedbacks.destroy');
    // Agents: list, detail tabs, manager assignment and suspension.
    Route::get('/agents', [\App\Http\Controllers\Superadmin\AgentController::class, 'index'])->middleware('permission:agent.view')->name('superadmin.agents.index');
    // Agents: a Superadmin creates an Agent, which stays pending until it activates its account.
    Route::get('/agents/create', [\App\Http\Controllers\Superadmin\AgentController::class, 'create'])->middleware('permission:agent.manage')->name('superadmin.agents.create');
    Route::post('/agents', [\App\Http\Controllers\Superadmin\AgentController::class, 'store'])->middleware(['permission:agent.manage', 'throttle:30,1'])->name('superadmin.agents.store');
    Route::post('/agents/{admin}/resend-activation', [\App\Http\Controllers\Superadmin\AgentController::class, 'resendActivation'])->whereNumber('admin')->middleware(['permission:agent.manage', 'throttle:10,1'])->name('superadmin.agents.resend-activation');
    Route::get('/agents/{admin}', [\App\Http\Controllers\Superadmin\AgentController::class, 'show'])->whereNumber('admin')->middleware('permission:agent.view')->name('superadmin.agents.show');
    Route::post('/agents/{admin}/managers', [\App\Http\Controllers\Superadmin\AgentController::class, 'assign'])->whereNumber('admin')->middleware('permission:agent.manage')->name('superadmin.agents.assign');
    Route::delete('/agents/{admin}/managers/{superadmin}', [\App\Http\Controllers\Superadmin\AgentController::class, 'unassign'])->whereNumber(['admin', 'superadmin'])->middleware('permission:agent.manage')->name('superadmin.agents.unassign');
    Route::post('/agents/{admin}/suspend', [\App\Http\Controllers\Superadmin\AgentController::class, 'suspend'])->whereNumber('admin')->middleware('permission:agent.manage')->name('superadmin.agents.suspend');
    Route::post('/agents/{admin}/reactivate', [\App\Http\Controllers\Superadmin\AgentController::class, 'reactivate'])->whereNumber('admin')->middleware('permission:agent.manage')->name('superadmin.agents.reactivate');
    // Agents: self-service registrations waiting for review.
    Route::get('/agents/registrations', [\App\Http\Controllers\Superadmin\AgentRegistrationController::class, 'index'])->middleware('permission:agent.approve')->name('superadmin.registrations.index');
    Route::get('/agents/registrations/{admin}', [\App\Http\Controllers\Superadmin\AgentRegistrationController::class, 'show'])->whereNumber('admin')->middleware('permission:agent.approve')->name('superadmin.registrations.show');
    Route::post('/agents/registrations/{admin}/approve', [\App\Http\Controllers\Superadmin\AgentRegistrationController::class, 'approve'])->whereNumber('admin')->middleware('permission:agent.approve')->name('superadmin.registrations.approve');
    Route::post('/agents/registrations/{admin}/reject', [\App\Http\Controllers\Superadmin\AgentRegistrationController::class, 'reject'])->whereNumber('admin')->middleware('permission:agent.approve')->name('superadmin.registrations.reject');
    // Agents: subscriptions (list, per-Agent page, package change, cancellation).
    Route::get('/subscriptions', [\App\Http\Controllers\Superadmin\SubscriptionController::class, 'index'])->middleware('permission:subscription.view')->name('superadmin.subscriptions.index');
    Route::get('/subscriptions/agents/{admin}', [\App\Http\Controllers\Superadmin\SubscriptionController::class, 'show'])->whereNumber('admin')->middleware('permission:subscription.view')->name('superadmin.subscriptions.show');
    Route::post('/subscriptions/agents/{admin}/change', [\App\Http\Controllers\Superadmin\SubscriptionController::class, 'change'])->whereNumber('admin')->middleware('permission:subscription.manage')->name('superadmin.subscriptions.change');
    Route::post('/subscriptions/agents/{admin}/cancel', [\App\Http\Controllers\Superadmin\SubscriptionController::class, 'cancel'])->whereNumber('admin')->middleware('permission:subscription.manage')->name('superadmin.subscriptions.cancel');
    // Platform finance: revenue, outstanding invoices, Agent billing, manual payments and voids.
    Route::get('/revenue', [\App\Http\Controllers\Superadmin\BillingController::class, 'revenue'])->middleware('permission:revenue.view')->name('superadmin.billing.revenue');
    Route::get('/billing/outstanding', [\App\Http\Controllers\Superadmin\BillingController::class, 'outstanding'])->middleware('permission:debt.view')->name('superadmin.billing.outstanding');
    Route::get('/billing/agents/{admin}', [\App\Http\Controllers\Superadmin\BillingController::class, 'agent'])->whereNumber('admin')->middleware('permission:debt.view')->name('superadmin.billing.agent');
    Route::post('/billing/invoices/{invoice}/payments', [\App\Http\Controllers\Superadmin\BillingController::class, 'recordPayment'])->whereNumber('invoice')->middleware(['permission:debt.manage', 'throttle:30,1'])->name('superadmin.billing.payments.store');
    Route::post('/billing/invoices/{invoice}/void', [\App\Http\Controllers\Superadmin\BillingController::class, 'void'])->whereNumber('invoice')->middleware('permission:debt.manage')->name('superadmin.billing.invoices.void');
    // Plans: subscription packages sold to Agents.
    Route::get('/packages', [\App\Http\Controllers\Superadmin\PackageController::class, 'index'])->middleware('permission:package.view')->name('superadmin.packages.index');
    Route::post('/packages', [\App\Http\Controllers\Superadmin\PackageController::class, 'store'])->middleware('permission:package.manage')->name('superadmin.packages.store');
    Route::get('/packages/{package}', [\App\Http\Controllers\Superadmin\PackageController::class, 'show'])->whereNumber('package')->middleware('permission:package.view')->name('superadmin.packages.show');
    Route::put('/packages/{package}', [\App\Http\Controllers\Superadmin\PackageController::class, 'update'])->whereNumber('package')->middleware('permission:package.manage')->name('superadmin.packages.update');
    Route::post('/packages/{package}/archive', [\App\Http\Controllers\Superadmin\PackageController::class, 'archive'])->whereNumber('package')->middleware('permission:package.manage')->name('superadmin.packages.archive');
    Route::delete('/packages/{package}', [\App\Http\Controllers\Superadmin\PackageController::class, 'destroy'])->whereNumber('package')->middleware('permission:package.manage')->name('superadmin.packages.destroy');
    // Governance: superadmin roles (permission templates).
    Route::get('/roles', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'index'])->middleware('permission:superadmin.view')->name('superadmin.roles.index');
    Route::get('/roles/create', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'create'])->middleware('permission:superadmin.manage')->name('superadmin.roles.create');
    Route::post('/roles', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'store'])->middleware('permission:superadmin.manage')->name('superadmin.roles.store');
    Route::get('/roles/{role}', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'edit'])->whereNumber('role')->middleware('permission:superadmin.view')->name('superadmin.roles.edit');
    Route::put('/roles/{role}', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'update'])->whereNumber('role')->middleware('permission:superadmin.manage')->name('superadmin.roles.update');
    Route::delete('/roles/{role}', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'destroy'])->whereNumber('role')->middleware('permission:superadmin.manage')->name('superadmin.roles.destroy');
    Route::post('/superadmins/{superadmin}/role', [\App\Http\Controllers\Superadmin\SuperadminRoleController::class, 'apply'])->middleware('permission:superadmin.manage')->name('superadmin.superadmins.role');
    // Governance: superadmin accounts, permissions and scopes.
    Route::get('/superadmins', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'index'])->middleware('permission:superadmin.view')->name('superadmin.superadmins.index');
    Route::post('/superadmins', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'store'])->middleware('permission:superadmin.manage')->name('superadmin.superadmins.store');
    Route::get('/superadmins/{superadmin}', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'show'])->middleware('permission:superadmin.view')->name('superadmin.superadmins.show');
    Route::put('/superadmins/{superadmin}', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'update'])->middleware('permission:superadmin.manage')->name('superadmin.superadmins.update');
    Route::post('/permissions/sync', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'syncCatalog'])->middleware(['permission:superadmin.manage', 'throttle:10,1'])->name('superadmin.permissions.sync');
    Route::put('/superadmins/{superadmin}/permissions', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'permissions'])->middleware('permission:superadmin.manage')->name('superadmin.superadmins.permissions');
    Route::delete('/superadmins/{superadmin}', [\App\Http\Controllers\Superadmin\SuperadminController::class, 'destroy'])->middleware('permission:superadmin.manage')->name('superadmin.superadmins.destroy');
    Route::get('/versions/page', [\App\Http\Controllers\Superadmin\PageController::class, 'versions'])->middleware('permission:version.view')->name('superadmin.versions.page');
    Route::get('/versions', [\App\Http\Controllers\Superadmin\VersionController::class, 'index'])->middleware('permission:version.view')->name('superadmin.versions.index');
    Route::post('/versions', [\App\Http\Controllers\Superadmin\VersionController::class, 'store'])->middleware('permission:version.manage')->name('superadmin.versions.store');
    Route::post('/versions/preview', [\App\Http\Controllers\Superadmin\VersionController::class, 'preview'])->middleware('permission:version.manage')->name('superadmin.versions.preview');
    Route::match(['put', 'patch'], '/versions/{version}', [\App\Http\Controllers\Superadmin\VersionController::class, 'update'])->middleware('permission:version.manage')->name('superadmin.versions.update');
    Route::delete('/versions/{version}', [\App\Http\Controllers\Superadmin\VersionController::class, 'destroy'])->middleware('permission:version.manage')->name('superadmin.versions.destroy');
    // Games of the /me "Trò chơi" catalogue.
    Route::get('/games', [\App\Http\Controllers\Superadmin\GameController::class, 'index'])->middleware('permission:game.view')->name('superadmin.games.index');
    Route::post('/games', [\App\Http\Controllers\Superadmin\GameController::class, 'store'])->middleware('permission:game.manage')->name('superadmin.games.store');
    Route::get('/games/{game}', [\App\Http\Controllers\Superadmin\GameController::class, 'show'])->whereNumber('game')->middleware('permission:game.view')->name('superadmin.games.show');
    Route::put('/games/{game}', [\App\Http\Controllers\Superadmin\GameController::class, 'update'])->whereNumber('game')->middleware('permission:game.manage')->name('superadmin.games.update');
    Route::delete('/games/{game}', [\App\Http\Controllers\Superadmin\GameController::class, 'destroy'])->whereNumber('game')->middleware('permission:game.manage')->name('superadmin.games.destroy');
});
