<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationChannelRequest;
use App\Http\Requests\TestNotificationChannelDraftRequest;
use App\Models\NotificationChannel;
use App\Models\Room;
use App\Services\Audit\AuditService;
use App\Services\Notification\RoomNotificationChannelService;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class NotificationChannelController extends Controller
{
    /**
     * Handle the index operation.
     *
     * @param Room $room Room entity.
     * @param RoomNotificationChannelService $service Channel service.
     * @return JsonResponse List of masked notification channels.
     */
    public function index(Room $room, RoomNotificationChannelService $service): JsonResponse
    {
        $channels = $room->notificationChannels()->get()->map(fn (NotificationChannel $channel) => $service->mask($channel));
        return response()->json(['data' => $channels]);
    }

    /**
     * Display the standalone Notification Channels & Webhook Integrations page.
     *
     * @param Request $request Incoming request.
     * @param Room $room Room entity.
     * @param RoomNotificationChannelService $service Channel service.
     * @param RoomNotificationChannelDispatcher $dispatcher Renders the test-send previews in the current locale.
     * @return View Blade view.
     */
    public function page(Request $request, Room $room, RoomNotificationChannelService $service, RoomNotificationChannelDispatcher $dispatcher): View
    {
        $channels = $room->notificationChannels()->get()->map(fn (NotificationChannel $channel) => $service->mask($channel));

        return view('admin.notifications', [
            'room' => $room,
            'channels' => $channels,
            'testPreviews' => $dispatcher->testPreviews($room),
        ]);
    }

    /**
     * Handle the store operation.
     *
     * @param NotificationChannelRequest $request Validated request.
     * @param Room $room Room entity.
     * @param RoomNotificationChannelService $service Channel service.
     * @param AuditService $audit Audit service.
     * @return JsonResponse Created channel data.
     */
    public function store(NotificationChannelRequest $request, Room $room, RoomNotificationChannelService $service, AuditService $audit): JsonResponse
    {
        $channel = $service->save($room->id, $request->validated());
        $audit->record('notification_channel.created', 'notification_channel', $channel->id, $room->id, [], ['type' => $channel->type, 'status' => $channel->status]);
        return response()->json(['data' => $service->mask($channel)], 201);
    }

    /**
     * Return editable channel details for the room administrator.
     *
     * @param Room $room Room entity.
     * @param NotificationChannel $channel Notification channel.
     * @param RoomNotificationChannelService $service Channel service.
     * @return JsonResponse Editable channel data.
     */
    public function show(Room $room, NotificationChannel $channel, RoomNotificationChannelService $service): JsonResponse
    {
        abort_unless($channel->room_id === $room->id, 404);

        // The editor receives the stored credentials: never let a browser or proxy cache them.
        return response()->json(['data' => $service->editable($channel)])
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Handle the update operation.
     *
     * @param NotificationChannelRequest $request Validated request.
     * @param Room $room Room entity.
     * @param NotificationChannel $channel Notification channel.
     * @param RoomNotificationChannelService $service Channel service.
     * @param AuditService $audit Audit service.
     * @return JsonResponse Updated channel data.
     */
    public function update(NotificationChannelRequest $request, Room $room, NotificationChannel $channel, RoomNotificationChannelService $service, AuditService $audit): JsonResponse
    {
        abort_unless($channel->room_id === $room->id, 404);
        $before = ['type' => $channel->type, 'status' => $channel->status];
        $channel = $service->save($room->id, $request->validated(), $channel);
        $audit->record('notification_channel.updated', 'notification_channel', $channel->id, $room->id, $before, ['type' => $channel->type, 'status' => $channel->status]);
        return response()->json(['data' => $service->mask($channel)]);
    }

    /**
     * Handle the test notification sending operation with template support and rate limiting.
     *
     * @param Request $request Incoming HTTP request.
     * @param Room $room Room entity.
     * @param NotificationChannel $channel Notification channel.
     * @param RoomNotificationChannelService $service Channel service.
     * @param RoomNotificationChannelDispatcher $dispatcher Channel dispatcher.
     * @return JsonResponse Status response.
     */
    public function test(Request $request, Room $room, NotificationChannel $channel, RoomNotificationChannelService $service, RoomNotificationChannelDispatcher $dispatcher): JsonResponse
    {
        abort_unless($channel->room_id === $room->id, 404);
        abort_unless($service->configured($channel), 422, __('admin.channel_not_configured'));

        $admin = $request->user('admin');
        $rateLimitKey = 'channel-test:'.$channel->id.':'.($admin?->id ?? $request->ip());
        $maxAttempts = 5;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => __('admin.notification_channel_test_rate_limited', ['seconds' => $seconds]),
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, $decaySeconds);

        $template = (string) $request->input('template', 'test_ping');
        $allowedTemplates = [...RoomNotificationChannelDispatcher::TEST_TEMPLATES, 'notification.test'];
        if (! in_array($template, $allowedTemplates, true)) {
            $template = 'test_ping';
        }

        try {
            $dispatcher->test($channel, $template);
        } catch (\InvalidArgumentException) {
            // The stored destination is not a safe public HTTPS endpoint (e.g. saved before SSRF validation existed).
            return response()->json(['message' => __('admin.webhook_url_unsafe')], 422);
        }

        return response()->json([
            'message' => 'test_sent',
            'data' => [
                'channel_id' => $channel->id,
                'type' => $channel->type,
                'template' => $template,
            ],
        ]);
    }

    /**
     * Send a test message with the values currently in the channel form, before the channel is saved.
     *
     * @param TestNotificationChannelDraftRequest $request Validated form values (authorized room admin).
     * @param Room $room Room entity.
     * @param RoomNotificationChannelService $service Channel service.
     * @param RoomNotificationChannelDispatcher $dispatcher Channel dispatcher.
     * @return JsonResponse Status response; 422 when the settings are incomplete or the provider refuses the message.
     */
    public function testDraft(TestNotificationChannelDraftRequest $request, Room $room, RoomNotificationChannelService $service, RoomNotificationChannelDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validated();
        $channel = null;
        if (! empty($data['channel_id'])) {
            $channel = $room->notificationChannels()->whereKey((int) $data['channel_id'])->first();
            abort_if($channel === null, 404);
        }

        $rateLimitKey = 'channel-test-draft:'.$room->id.':'.($request->user('admin')?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return response()->json([
                'message' => __('admin.notification_channel_test_rate_limited', ['seconds' => $seconds]),
                'retry_after' => $seconds,
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        try {
            $config = $service->draftConfig($data, $channel);
        } catch (\DomainException) {
            return response()->json(['message' => __('admin.notification_channel_draft_incomplete')], 422);
        }

        try {
            $dispatcher->testDraft((string) $data['type'], $config, (string) ($data['template'] ?? 'test_ping'), $room);
        } catch (\InvalidArgumentException) {
            return response()->json(['message' => __('admin.webhook_url_unsafe')], 422);
        } catch (\Throwable $exception) {
            // The provider's error may echo the request URL (a Telegram URL carries the bot token): log the class only.
            Log::warning('Notification channel draft test failed.', [
                'room_id' => $room->id,
                'type' => $data['type'],
                'exception' => $exception::class,
            ]);

            return response()->json(['message' => __('admin.notification_channel_test_failed')], 422);
        }

        return response()->json(['message' => __('admin.notification_channel_test_sent'), 'data' => ['type' => $data['type']]]);
    }

    /**
     * Delete a channel of the room from the database.
     *
     * @param Room $room Room entity.
     * @param NotificationChannel $channel Notification channel.
     * @param RoomNotificationChannelService $service Channel service.
     * @param AuditService $audit Audit service.
     * @return JsonResponse Status response.
     */
    public function destroy(Room $room, NotificationChannel $channel, RoomNotificationChannelService $service, AuditService $audit): JsonResponse
    {
        abort_unless($channel->room_id === $room->id, 404);
        $service->delete($channel, $audit);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
