<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveAdmin;
use App\Http\Requests\UpdateAdminPasswordRequest;
use App\Http\Requests\UpdateAdminProfileRequest;
use App\Http\Requests\UpdateAdminTwoFactorRequest;
use App\Http\Requests\UploadAdminAvatarRequest;
use App\Models\Admin;
use App\Services\Audit\AuditService;
use App\Services\Media\ImageUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Display the authenticated admin's profile and security page.
     *
     * @param Request $request Incoming request.
     * @return View Profile page.
     */
    public function show(Request $request): View
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return view('admin.profile', ['admin' => $admin->loadCount('rooms')]);
    }

    /**
     * Update the authenticated admin's editable profile fields.
     *
     * @param Request $request Incoming request.
     * @param AuditService $audit Activity audit service.
     * @return RedirectResponse Redirect back with status.
     */
    public function update(UpdateAdminProfileRequest $request, AuditService $audit): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $data = $request->validated();

        $before = $admin->only(['name', 'phone', 'department']);
        $admin->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'department' => $data['department'] ?? null,
        ]);
        $audit->record('admin.profile_updated', 'admin', $admin->id, null, $before, $admin->fresh()->only(array_keys($before)));

        return back()->with('status', __('admin.profile_updated'));
    }

    /**
     * Upload an avatar for the authenticated admin.
     *
     * @param UploadAdminAvatarRequest $request Validated avatar upload request.
     * @param AuditService $audit Activity audit service.
     * @param ImageUploadService $imageUploadService Shared image optimization service.
     * @return RedirectResponse Redirect back with status.
     */
    public function uploadAvatar(
        UploadAdminAvatarRequest $request,
        AuditService $audit,
        ImageUploadService $imageUploadService
    ): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $data = $request->validated();
        $uploaded = $imageUploadService->optimizeAndStore(
            file: $data['avatar'],
            directory: 'uploads/admin-avatars',
            maxWidth: 400,
            maxHeight: 400,
            quality: 85
        );

        if ($admin->avatar_url) {
            $imageUploadService->deleteFile($admin->avatar_url);
        }

        $before = ['avatar_configured' => (bool) $admin->avatar_url];
        $admin->update(['avatar_url' => $uploaded['path']]);
        $audit->record('admin.avatar_updated', 'admin', $admin->id, null, $before, ['avatar_configured' => true]);

        return back()->with('status', __('admin.avatar_updated'));
    }

    /**
     * Stream the authenticated administrator's avatar without requiring a public storage symlink.
     *
     * @param Request $request Incoming authenticated request.
     * @return StreamedResponse Optimized avatar image response.
     */
    public function avatar(Request $request): StreamedResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $storedValue = (string) ($admin->avatar_url ?? '');
        $path = str_contains($storedValue, '/storage/')
            ? Str::after($storedValue, '/storage/')
            : ltrim($storedValue, '/');

        abort_if($path === '' || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Change the authenticated admin's password after current-password verification.
     *
     * @param Request $request Incoming request.
     * @param AuditService $audit Activity audit service.
     * @return RedirectResponse Redirect back with status.
     */
    public function updatePassword(UpdateAdminPasswordRequest $request, AuditService $audit): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $data = $request->validated();

        if (!Hash::check($data['current_password'], $admin->password)) {
            $audit->record('admin.password_update_failed', 'admin', $admin->id);
            return back()->withErrors(['current_password' => __('admin.current_password_incorrect')]);
        }

        $admin->forceFill(['remember_token' => Str::random(60)]);
        $admin->update(['password' => $data['password']]);
        // Keep this session signed in while every other session/remember-me cookie of the admin is revoked.
        $request->session()->put('admin_password_fingerprint', EnsureActiveAdmin::passwordFingerprint($admin->refresh()));
        $audit->record('admin.password_updated', 'admin', $admin->id);

        return back()->with('status', __('admin.password_updated'));
    }

    /**
     * Enable or disable two-factor sign-in after verifying the current password.
     *
     * @param Request $request Incoming request.
     * @param AuditService $audit Activity audit service.
     * @return RedirectResponse Redirect back with a status or validation error.
     */
    public function updateTwoFactor(UpdateAdminTwoFactorRequest $request, AuditService $audit): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $admin->password)) {
            $audit->record('admin.two_factor_update_failed', 'admin', $admin->id);
            return back()->withErrors(['two_factor_password' => __('admin.current_password_incorrect')]);
        }

        $before = (bool) $admin->two_factor_enabled;
        $admin->update(['two_factor_enabled' => (bool) $data['two_factor_enabled']]);
        $audit->record('admin.two_factor_updated', 'admin', $admin->id, null, ['two_factor_enabled' => $before], ['two_factor_enabled' => (bool) $data['two_factor_enabled']]);

        return back()->with('status', __('admin.profile_updated'));
    }
}
