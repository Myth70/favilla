<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Modules\Notifications\Repositories\TelegramUserLinkRepository;

class TelegramChannelDriver implements NotificationChannelDriverInterface
{
    private TelegramBotRepository $botRepo;
    private TelegramUserLinkRepository $linkRepo;

    public function __construct()
    {
        $this->botRepo = app(TelegramBotRepository::class);
        $this->linkRepo = app(TelegramUserLinkRepository::class);
    }

    public function channel(): string
    {
        return 'telegram';
    }

    public function send(array $job): array
    {
        $bot = $this->botRepo->findDefaultEnabled();
        if (!$bot) {
            return [
                'status' => 'skipped',
                'provider_message_id' => null,
                'error_message' => 'Nessun bot Telegram attivo configurato.',
            ];
        }

        $link = $this->linkRepo->findLinkedByUserId((int) $job['user_id']);
        if (!$link || empty($link['chat_id'])) {
            return [
                'status' => 'skipped',
                'provider_message_id' => null,
                'error_message' => 'Utente non collegato a Telegram.',
            ];
        }

        $text = $this->buildMessage($job);
        $endpoint = 'https://api.telegram.org/bot' . $bot['bot_token'] . '/sendMessage';
        $payload = json_encode([
            'chat_id' => (string) $link['chat_id'],
            'text'    => $text,
            'disable_web_page_preview' => false,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 15,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $context);
        if ($response === false) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => 'Telegram API non raggiungibile.',
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
            $error = is_array($decoded) ? ($decoded['description'] ?? 'Telegram API error.') : 'Telegram API response non valida.';
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'error_message' => (string) $error,
            ];
        }

        $messageId = (string) ($decoded['result']['message_id'] ?? '');
        return [
            'status' => 'sent',
            'provider_message_id' => $messageId !== '' ? $messageId : null,
            'error_message' => null,
        ];
    }

    private function buildMessage(array $job): string
    {
        $parts = [];
        $title = trim((string) ($job['delivery_subject'] ?? $job['dispatch_title'] ?? 'Notifica'));
        $body  = trim(strip_tags((string) ($job['delivery_body'] ?? $job['dispatch_body'] ?? '')));
        $link  = trim((string) ($job['delivery_link'] ?: ($job['dispatch_link'] ?? '')));

        if ($title !== '') {
            $parts[] = $title;
        }
        if ($body !== '') {
            $parts[] = $body;
        }
        if ($link !== '') {
            $parts[] = $link;
        }

        return implode("\n\n", $parts);
    }
}
