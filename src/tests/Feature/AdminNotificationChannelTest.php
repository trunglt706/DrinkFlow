<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Room;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\Notification\RoomNotificationChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminNotificationChannelTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'Channel Tester',
            'email' => 'channel-tester@example.test',
            'password' => 'secret-password',
            'status' => 'active',
        ]);

        $this->room = Room::create([
            'name' => 'Engineering',
            'slug' => 'engineering',
            'status' => 'active',
        ]);

        $this->admin->rooms()->attach($this->room);
    }

    /** Ensure testing a channel with default ping sends correct payload. */
    public function test_admin_can_send_test_ping_notification(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $channel = app(RoomNotificationChannelService::class)->save($this->room->id, [
            'type' => 'telegram',
            'name' => 'Team Telegram',
            'status' => 'enabled',
            'config' => ['bot_token' => '123456:abcdef', 'chat_id' => '-100987654'],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels/{$channel->id}/test");

        $response->assertOk()
            ->assertJsonPath('message', 'test_sent')
            ->assertJsonPath('data.template', 'test_ping');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'api.telegram.org/bot123456:abcdef/sendMessage')
                && $request['chat_id'] === '-100987654'
                && $request['parse_mode'] === 'HTML'
                && str_contains((string) $request['text'], 'DrinkFlow');
        });
    }

    /** Ensure testing channel with different templates sends formatted content. */
    public function test_admin_can_send_campaign_created_template_test(): void
    {
        Http::fake(['https://hooks.slack.com/*' => Http::response('ok', 200)]);

        $channel = app(RoomNotificationChannelService::class)->save($this->room->id, [
            'type' => 'slack',
            'name' => 'Team Slack',
            'status' => 'enabled',
            'config' => ['webhook_url' => 'https://hooks.slack.com/services/T00/B00/X00'],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels/{$channel->id}/test", [
                'template' => 'campaign.created',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'test_sent')
            ->assertJsonPath('data.template', 'campaign.created');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://hooks.slack.com/services/T00/B00/X00'
                && str_contains((string) $request['text'], '[DrinkFlow]')
                && str_contains((string) $request['text'], 'Phê La');
        });
    }

    /** Ensure testing channel with debt reminder template sends formatted content. */
    public function test_admin_can_send_debt_reminder_template_test(): void
    {
        Http::fake(['https://api.chatwork.com/*' => Http::response(['message_id' => '123'], 200)]);

        $channel = app(RoomNotificationChannelService::class)->save($this->room->id, [
            'type' => 'chatwork',
            'name' => 'Team ChatWork',
            'status' => 'enabled',
            'config' => ['api_token' => 'chatwork-secret-token', 'room_id' => '98765432'],
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels/{$channel->id}/test", [
                'template' => 'debt.reminder',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'test_sent')
            ->assertJsonPath('data.template', 'debt.reminder');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.chatwork.com/v2/rooms/98765432/messages'
                && str_contains((string) $request['body'], '[info][title]')
                && str_contains((string) $request['body'], '[/info]');
        });
    }

    /** Ensure testing channel is rate limited to 5 attempts per minute. */
    public function test_channel_testing_is_rate_limited(): void
    {
        Http::fake(['https://hooks.slack.com/*' => Http::response('ok', 200)]);
        RateLimiter::clear("channel-test:1:{$this->admin->id}");

        $channel = app(RoomNotificationChannelService::class)->save($this->room->id, [
            'type' => 'slack',
            'name' => 'Rate Limit Slack',
            'status' => 'enabled',
            'config' => ['webhook_url' => 'https://hooks.slack.com/services/T00/B00/X00'],
        ]);

        // Send 5 test notifications (all should succeed)
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin, 'admin')
                ->postJson("/admin/{$this->room->id}/notification-channels/{$channel->id}/test", ['template' => 'test_ping'])
                ->assertOk();
        }

        // 6th test notification should hit rate limit (429)
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels/{$channel->id}/test", ['template' => 'test_ping']);

        $response->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);
    }

    /** Ensure RoomNotificationChannelDispatcher formats rich messages accurately across all drivers. */
    public function test_dispatcher_formats_messages_for_all_supported_drivers(): void
    {
        $dispatcher = app(RoomNotificationChannelDispatcher::class);
        $payload = [
            'event' => 'campaign.created',
            'title' => 'Chiến dịch mới',
            'message' => "Chiến dịch mới\nTên: Trà sữa Phê La\nQuán / Thương hiệu: Phê La\nThời hạn: 11:30 16/09/2026\nĐặt món: https://drinkflow.test/order",
        ];

        // Telegram HTML
        $telegramText = $dispatcher->formatTelegramMessage($payload);
        $this->assertStringContainsString('<b>🚀 [DrinkFlow] Chiến dịch mới</b>', $telegramText);
        $this->assertStringContainsString('<b>Tên:</b> Trà sữa Phê La', $telegramText);
        $this->assertStringContainsString('<a href="https://drinkflow.test/order">', $telegramText);

        // Slack mrkdwn
        $slackText = $dispatcher->formatSlackMessage($payload);
        $this->assertStringContainsString('*🚀 [DrinkFlow] Chiến dịch mới*', $slackText);
        $this->assertStringContainsString('*Tên:* Trà sữa Phê La', $slackText);
        $this->assertStringContainsString('<https://drinkflow.test/order|', $slackText);

        // Chatwork BBCode
        $chatworkText = $dispatcher->formatChatworkMessage($payload);
        $this->assertStringContainsString('[info][title]🚀 [DrinkFlow] Chiến dịch mới[/title]', $chatworkText);
        $this->assertStringContainsString('Tên: Trà sữa Phê La', $chatworkText);
        $this->assertStringContainsString('👉 ', $chatworkText);
        $this->assertStringContainsString('[/info]', $chatworkText);

        // Webhook JSON
        $webhookData = $dispatcher->formatWebhookPayload($payload);
        $this->assertSame('campaign.created', $webhookData['event']);
        $this->assertSame('🚀', $webhookData['icon']);
        $this->assertArrayHasKey('timestamp', $webhookData);
    }

    /** Deleting a channel removes the row (it used to only disable it) and is audited. */
    public function test_deleting_a_channel_removes_it_from_the_database(): void
    {
        $channel = $this->telegramChannel('Team Telegram', '123456:abcdef');
        $otherRoom = Room::create(['name' => 'Sales', 'slug' => 'sales', 'status' => 'active']);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$otherRoom->id}/notification-channels/{$channel->id}")
            ->assertStatus(403);
        $this->assertDatabaseHas('notification_channels', ['id' => $channel->id]);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson("/admin/{$this->room->id}/notification-channels/{$channel->id}")
            ->assertOk()->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'notification_channel.deleted', 'target_id' => $channel->id]);
    }

    /** "Test" in the form sends with the values typed in, without saving anything. */
    public function test_admin_can_test_unsaved_form_values(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->actingAs($this->admin, 'admin')
            ->postJson("/admin/{$this->room->id}/notification-channels/test-draft", [
                'type' => 'telegram',
                'config' => ['bot_token' => '777:draft-token', 'chat_id' => '-100555'],
            ])
            ->assertOk()->assertJsonPath('message', __('admin.notification_channel_test_sent'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bot777:draft-token/sendMessage') && $request['chat_id'] === '-100555');
        $this->assertDatabaseCount('notification_channels', 0);
    }

    /** Incomplete settings, unsafe URLs and provider refusals are reported without a server error. */
    public function test_draft_test_rejects_incomplete_or_failing_settings(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false], 401)]);
        $url = "/admin/{$this->room->id}/notification-channels/test-draft";

        $this->actingAs($this->admin, 'admin')
            ->postJson($url, ['type' => 'telegram', 'config' => ['bot_token' => '777:draft-token']])
            ->assertStatus(422)->assertJsonPath('message', __('admin.notification_channel_draft_incomplete'));
        $this->actingAs($this->admin, 'admin')
            ->postJson($url, ['type' => 'webhook', 'config' => ['webhook_url' => 'http://127.0.0.1/hook']])
            ->assertStatus(422)->assertJsonValidationErrors('config.webhook_url');
        $this->actingAs($this->admin, 'admin')
            ->postJson($url, ['type' => 'telegram', 'config' => ['bot_token' => '777:draft-token', 'chat_id' => '-1']])
            ->assertStatus(422)->assertJsonPath('message', __('admin.notification_channel_test_failed'));
    }

    /** Editing: a blank credential in the form falls back to the stored one; another room's channel is refused. */
    public function test_draft_test_of_an_edited_channel_reuses_stored_credentials(): void
    {
        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $channel = $this->telegramChannel('Team Telegram', '123456:stored');
        $otherRoom = Room::create(['name' => 'Sales', 'slug' => 'sales', 'status' => 'active']);
        $foreign = app(RoomNotificationChannelService::class)->save($otherRoom->id, [
            'type' => 'telegram', 'name' => 'Foreign', 'status' => 'enabled',
            'config' => ['bot_token' => '999:foreign', 'chat_id' => '-1'],
        ]);
        $url = "/admin/{$this->room->id}/notification-channels/test-draft";

        $this->actingAs($this->admin, 'admin')
            ->postJson($url, ['type' => 'telegram', 'channel_id' => $channel->id, 'config' => ['bot_token' => '', 'chat_id' => '-100222']])
            ->assertOk();
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bot123456:stored/sendMessage') && $request['chat_id'] === '-100222');

        $this->actingAs($this->admin, 'admin')
            ->postJson($url, ['type' => 'telegram', 'channel_id' => $foreign->id, 'config' => ['chat_id' => '-1']])
            ->assertNotFound();
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '999:foreign'));
    }

    /** One channel failing never prevents the other channels of the room from receiving the message. */
    public function test_a_failing_channel_does_not_block_the_other_channels(): void
    {
        Http::fake([
            'https://api.telegram.org/bot111:broken/*' => Http::response(['ok' => false], 500),
            'https://api.telegram.org/bot222:works/*' => Http::response(['ok' => true], 200),
        ]);
        $this->telegramChannel('Broken', '111:broken');
        // Undecryptable credentials must not stop the loop either.
        \App\Models\NotificationChannel::query()->create(['room_id' => $this->room->id, 'type' => 'telegram', 'name' => 'Corrupt', 'status' => 'enabled', 'config_encrypted' => 'not-encrypted']);
        $this->telegramChannel('Works', '222:works');

        $attempts = app(RoomNotificationChannelDispatcher::class)->dispatch($this->room, ['event' => 'notification.test', 'title' => 'Hi', 'message' => 'Hello']);

        $this->assertSame(1, $attempts);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bot222:works/sendMessage'));
    }

    private function telegramChannel(string $name, string $token): \App\Models\NotificationChannel
    {
        return app(RoomNotificationChannelService::class)->save($this->room->id, [
            'type' => 'telegram', 'name' => $name, 'status' => 'enabled',
            'config' => ['bot_token' => $token, 'chat_id' => '-100987654'],
        ]);
    }
}
