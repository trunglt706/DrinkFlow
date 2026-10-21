<?php

declare(strict_types=1);

namespace App\Services\Notification\Concerns;

use App\Support\Security\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;

/**
 * Driver-neutral delivery + message formatting for chatwork/slack/telegram/webhook notification
 * channels. Shared by room-scoped and system-scoped channel dispatchers so the wire protocol for
 * each driver type is defined exactly once.
 */
trait SendsNotificationChannelPayloads
{
    /**
     * Send a payload using the concrete notification-channel driver protocol.
     *
     * @param string $type Driver type.
     * @param array<string, string> $config Driver configuration.
     * @param array<string, mixed> $payload Driver-neutral payload.
     * @return void
     */
    private function send(string $type, array $config, array $payload): void
    {
        match ($type) {
            'slack' => $this->postJson($this->required($config, 'webhook_url'), [
                'text' => $this->formatSlackMessage($payload),
            ], [], ['hooks.slack.com']),
            'telegram' => Http::timeout(5)->post('https://api.telegram.org/bot'.$this->pathSegment($this->required($config, 'bot_token'), '/^\d+:[A-Za-z0-9_-]+$/').'/sendMessage', [
                'chat_id' => $this->required($config, 'chat_id'),
                'text' => $this->formatTelegramMessage($payload),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => false,
            ])->throw(),
            'chatwork' => Http::timeout(5)->withHeaders(['X-ChatWorkToken' => $this->required($config, 'api_token')])->asForm()->post('https://api.chatwork.com/v2/rooms/'.$this->pathSegment($this->required($config, 'room_id'), '/^\d+$/').'/messages', [
                'body' => $this->formatChatworkMessage($payload),
            ])->throw(),
            'webhook' => $this->postJson(
                $this->required($config, 'webhook_url'),
                $this->formatWebhookPayload($payload),
                isset($config['secret_token']) && $config['secret_token'] !== '' ? ['X-Webhook-Secret' => $config['secret_token']] : [],
            ),
            default => throw new \InvalidArgumentException('Unsupported notification channel type.'),
        };
    }

    /**
     * POST a JSON payload to a user-supplied URL after SSRF validation, pinning the resolved address and refusing redirects.
     *
     * @param string $url Destination URL configured by an administrator.
     * @param array<string, mixed> $body JSON body.
     * @param array<string, string> $headers Extra request headers.
     * @param array<int, string> $allowedHosts Exact host names allowed; empty means any public host.
     * @return void
     * @throws \InvalidArgumentException When the URL is not a safe public HTTPS target.
     */
    private function postJson(string $url, array $body, array $headers = [], array $allowedHosts = []): void
    {
        $options = app(OutboundUrlGuard::class)->httpOptions($url, $allowedHosts);

        Http::timeout(5)->withOptions($options)->withHeaders($headers)->post($url, $body)->throw();
    }

    /**
     * Ensure a credential interpolated into a fixed API URL path cannot alter that path.
     *
     * @param string $value Credential or identifier.
     * @param string $pattern Regular expression the value must fully match.
     * @return string The unchanged value.
     * @throws \InvalidArgumentException When the value does not match the expected format.
     */
    private function pathSegment(string $value, string $pattern): string
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new \InvalidArgumentException('Notification channel configuration is invalid.');
        }

        return $value;
    }

    /**
     * Format notification for Telegram with rich HTML styling.
     *
     * @param array<string, mixed> $payload Notification payload.
     * @return string Telegram HTML message.
     */
    public function formatTelegramMessage(array $payload): string
    {
        $event = (string) ($payload['event'] ?? 'notification');
        $title = trim((string) ($payload['title'] ?? 'DrinkFlow Notification'));
        $icon = $this->icon($event);
        $details = $this->extractDetails($payload, $title);

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lines = ["<b>{$icon} [DrinkFlow] {$escapedTitle}</b>"];
        $lines[] = '━━━━━━━━━━━━━━━━━━━━';

        $orderUrl = null;
        foreach ($details as $line) {
            if (preg_match('/^(?:Đặt món|Order|注文):\s*(https?:\/\/\S+)/iu', $line, $matches)) {
                $orderUrl = $matches[1];
                continue;
            }

            if (($parts = $this->splitLabelValue($line)) !== null) {
                [$label, $val] = $parts;
                $escapedLabel = htmlspecialchars(trim($label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escapedVal = htmlspecialchars(trim($val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lineIcon = $this->fieldIcon(trim($label));
                $lines[] = "{$lineIcon} <b>{$escapedLabel}:</b> {$escapedVal}";
            } else {
                $escapedLine = htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $lines[] = "• {$escapedLine}";
            }
        }

        if ($orderUrl !== null) {
            $orderNowText = htmlspecialchars(__('messages.campaign_order_now'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $lines[] = '━━━━━━━━━━━━━━━━━━━━';
            $lines[] = "👉 <a href=\"{$orderUrl}\"><b>{$orderNowText}</b></a>";
        }

        $lines[] = '<i>⚡ DrinkFlow Notification</i>';

        return implode("\n", $lines);
    }

    /**
     * Format notification for Slack with mrkdwn styling.
     *
     * @param array<string, mixed> $payload Notification payload.
     * @return string Slack mrkdwn text.
     */
    public function formatSlackMessage(array $payload): string
    {
        $event = (string) ($payload['event'] ?? 'notification');
        $title = trim((string) ($payload['title'] ?? 'DrinkFlow Notification'));
        $icon = $this->icon($event);
        $details = $this->extractDetails($payload, $title);

        $lines = ["*{$icon} [DrinkFlow] {$title}*"];
        $lines[] = '────────────────────────────';

        $orderUrl = null;
        foreach ($details as $line) {
            if (preg_match('/^(?:Đặt món|Order|注文):\s*(https?:\/\/\S+)/iu', $line, $matches)) {
                $orderUrl = $matches[1];
                continue;
            }

            if (($parts = $this->splitLabelValue($line)) !== null) {
                [$label, $val] = $parts;
                $lineIcon = $this->fieldIcon(trim($label));
                $lines[] = "{$lineIcon} *".trim($label).":* ".trim($val);
            } else {
                $lines[] = "• {$line}";
            }
        }

        if ($orderUrl !== null) {
            $orderNowText = __('messages.campaign_order_now');
            $lines[] = '────────────────────────────';
            $lines[] = "👉 <{$orderUrl}|*{$orderNowText}*>";
        }

        $lines[] = '_DrinkFlow Notification_';

        return implode("\n", $lines);
    }

    /**
     * Format notification for ChatWork with tags.
     *
     * @param array<string, mixed> $payload Notification payload.
     * @return string ChatWork BBCode text.
     */
    public function formatChatworkMessage(array $payload): string
    {
        $event = (string) ($payload['event'] ?? 'notification');
        $title = trim((string) ($payload['title'] ?? 'DrinkFlow Notification'));
        $icon = $this->icon($event);
        $details = $this->extractDetails($payload, $title);

        $header = "[info][title]{$icon} [DrinkFlow] {$title}[/title]";
        $bodyLines = [];
        $orderUrl = null;

        foreach ($details as $line) {
            if (preg_match('/^(?:Đặt món|Order|注文):\s*(https?:\/\/\S+)/iu', $line, $matches)) {
                $orderUrl = $matches[1];
                continue;
            }

            if (($parts = $this->splitLabelValue($line)) !== null) {
                [$label, $val] = $parts;
                $lineIcon = $this->fieldIcon(trim($label));
                $bodyLines[] = "{$lineIcon} ".trim($label).": ".trim($val);
            } else {
                $bodyLines[] = "• {$line}";
            }
        }

        if ($orderUrl !== null) {
            $orderNowText = __('messages.campaign_order_now');
            $bodyLines[] = "[hr]👉 {$orderNowText}: {$orderUrl}";
        }

        return $header.implode("\n", $bodyLines).'[/info]';
    }

    /**
     * Format structured webhook payload.
     *
     * @param array<string, mixed> $payload Notification payload.
     * @return array<string, mixed> Webhook JSON data.
     */
    public function formatWebhookPayload(array $payload): array
    {
        $event = (string) ($payload['event'] ?? 'notification');
        $plainMessage = $this->formatMessage($payload);

        return array_merge($payload, [
            'icon' => $this->icon($event),
            'message' => $plainMessage,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Extract clean detail lines from a raw payload message.
     *
     * @param array<string, mixed> $payload Notification payload.
     * @param string $title Title to strip from lines.
     * @return array<int, string> Filtered detail lines.
     */
    private function extractDetails(array $payload, string $title): array
    {
        $rawMessage = trim((string) ($payload['message'] ?? ''));
        $lines = $rawMessage === '' ? [] : (preg_split('/\r\n|\r|\n/', $rawMessage) ?: []);

        if ($lines !== [] && trim((string) $lines[0]) === $title) {
            array_shift($lines);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $line): string => trim((string) $line),
            $lines,
        ), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Format fallback plain message.
     *
     * @param array<string, mixed> $payload Driver-neutral notification payload.
     * @return string Readable notification text.
     */
    public function formatMessage(array $payload): string
    {
        $event = (string) ($payload['event'] ?? 'notification');
        $title = trim((string) ($payload['title'] ?? 'DrinkFlow notification'));
        $details = $this->extractDetails($payload, $title);

        $formatted = $this->icon($event).' '.$title;
        if ($details !== []) {
            $formatted .= "\n\n".implode("\n", array_map(static fn (string $line): string => '• '.$line, $details));
        }

        return $formatted;
    }

    /**
     * Resolve a visual icon for a notification event.
     *
     * @param string $event Notification event name.
     * @return string Unicode icon.
     */
    private function icon(string $event): string
    {
        return match (true) {
            $event === 'campaign.created' => '🚀',
            $event === 'campaign.closed' => '🔒',
            $event === 'campaign.cancelled' => '🚫',
            str_starts_with($event, 'campaign.') => '📣',
            str_starts_with($event, 'order.') => '🧾',
            str_starts_with($event, 'debt.'), str_starts_with($event, 'payment.') => '💳',
            str_starts_with($event, 'user.') => '👤',
            str_starts_with($event, 'security.') => '🔐',
            $event === 'notification.test' => '🧪',
            str_starts_with($event, 'birthday.') => '🎂',
            str_starts_with($event, 'recap.') => '🎉',
            default => '🔔',
        };
    }

    /**
     * Split a "Label: value" detail line, ignoring colons that belong to a URL scheme
     * so a line such as "… => https://example.test/x" is never cut into "https: //…".
     *
     * @param string $line Detail line.
     * @return array{0: string, 1: string}|null Label and value, or null when the line has no label.
     */
    private function splitLabelValue(string $line): ?array
    {
        $colon = strpos($line, ':');
        if ($colon === false) {
            return null;
        }

        if (preg_match('~[a-z][a-z0-9+.-]*://~i', $line, $match, PREG_OFFSET_CAPTURE) === 1 && $match[0][1] <= $colon) {
            return null;
        }

        return explode(':', $line, 2);
    }

    /**
     * Resolve icon for standard field labels.
     *
     * @param string $label Field label.
     * @return string Emoj/Symbol.
     */
    private function fieldIcon(string $label): string
    {
        $lower = mb_strtolower($label);
        return match (true) {
            str_contains($lower, 'tên') || str_contains($lower, 'name') || str_contains($lower, '名前') => '🧋',
            str_contains($lower, 'quán') || str_contains($lower, 'thương hiệu') || str_contains($lower, 'store') || str_contains($lower, 'restaurant') || str_contains($lower, '店舗') => '🏬',
            str_contains($lower, 'hạn') || str_contains($lower, 'thời gian') || str_contains($lower, 'deadline') || str_contains($lower, '締切') => '⏰',
            str_contains($lower, 'tài trợ') || str_contains($lower, 'sponsor') || str_contains($lower, 'スポンサー') => '🎁',
            str_contains($lower, 'trần') || str_contains($lower, 'giá') || str_contains($lower, 'budget') || str_contains($lower, 'amount') || str_contains($lower, 'tiền') || str_contains($lower, '価格') => '💰',
            default => '•',
        };
    }

    /**
     * Return a required driver configuration value.
     *
     * @param array<string, string> $config Driver configuration.
     * @param string $key Required key.
     * @return string Configured value.
     */
    private function required(array $config, string $key): string
    {
        $value = $config[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Notification channel configuration is incomplete.');
        }

        return $value;
    }
}
