<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Modules\Notifications\Repositories\TelegramUserLinkRepository;
use App\Services\AuditService;

class TelegramLinkService
{
    private TelegramBotRepository $botRepo;
    private TelegramUserLinkRepository $linkRepo;

    public function __construct()
    {
        $this->botRepo = app(TelegramBotRepository::class);
        $this->linkRepo = app(TelegramUserLinkRepository::class);
    }

    public function getWizardData(int $userId): array
    {
        $bot = $this->botRepo->findDefaultEnabled();
        $linked = $this->linkRepo->findLinkedByUserId($userId);
        $pending = null;

        if ($bot && !$linked) {
            $pending = $this->ensurePendingLink($userId, (int) $bot['id']);
        }

        return [
            'available'        => $bot !== null,
            'bot_name'         => $bot['name'] ?? null,
            'bot_username'     => $bot['bot_username'] ?? null,
            'webhook_url'      => $bot ? $this->buildWebhookUrl((string) $bot['webhook_secret']) : null,
            'linked'           => $linked !== null,
            'linked_at'        => $linked['linked_at'] ?? null,
            'telegram_username' => $linked['telegram_username'] ?? null,
            'chat_id'          => $linked['chat_id'] ?? null,
            'pending_token'    => $pending['link_token'] ?? null,
            'deep_link'        => ($bot && !empty($bot['bot_username']) && !empty($pending['link_token']))
                ? 'https://t.me/' . $bot['bot_username'] . '?start=' . $pending['link_token']
                : null,
        ];
    }

    public function regenerateToken(int $userId): array
    {
        $bot = $this->botRepo->findDefaultEnabled();
        if (!$bot) {
            throw new \RuntimeException('Nessun bot Telegram attivo configurato.');
        }

        $this->linkRepo->revokeByUserId($userId);
        $pending = $this->ensurePendingLink($userId, (int) $bot['id'], true);

        AuditService::log('notification_telegram_token_regenerated', 'telegram_link', (int) $pending['id']);

        return $this->getWizardData($userId);
    }

    public function disconnect(int $userId): void
    {
        $revoked = $this->linkRepo->revokeByUserId($userId);
        if ($revoked) {
            AuditService::log('notification_telegram_disconnected', 'telegram_link', $userId);
        }
    }

    /**
     * @param array<string, mixed> $update
     */
    public function handleWebhook(string $secret, array $update): array
    {
        $bot = $this->botRepo->findDefault();
        if (!$bot || !hash_equals((string) ($bot['webhook_secret'] ?? ''), $secret)) {
            return ['ok' => false, 'message' => 'Webhook secret non valido.', 'status' => 403];
        }

        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!is_array($message)) {
            return ['ok' => true, 'message' => 'Update ignorato.', 'status' => 200];
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        $telegramUserId = (string) ($message['from']['id'] ?? '');
        $telegramUsername = (string) ($message['from']['username'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));

        if ($text === '') {
            return ['ok' => true, 'message' => 'Messaggio senza testo.', 'status' => 200];
        }

        $token = $this->extractStartToken($text);
        if ($token === null) {
            $this->sendTelegramMessage((string) $bot['bot_token'], $chatId, "Ciao. Usa il link di collegamento generato nel tuo profilo Favilla per completare l'associazione.");
            return ['ok' => true, 'message' => 'Token mancante.', 'status' => 200];
        }

        $link = $this->linkRepo->findByToken((int) $bot['id'], $token);
        if (!$link) {
            $this->sendTelegramMessage((string) $bot['bot_token'], $chatId, 'Token di collegamento non valido o scaduto. Rigenera il link dal tuo profilo Favilla.');
            return ['ok' => true, 'message' => 'Token non trovato.', 'status' => 200];
        }

        $metadata = json_encode([
            'update_id' => $update['update_id'] ?? null,
            'chat'      => $message['chat'] ?? null,
            'from'      => $message['from'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->linkRepo->markLinked((int) $link['id'], [
            'telegram_user_id'  => $telegramUserId,
            'chat_id'           => $chatId,
            'telegram_username' => $telegramUsername !== '' ? $telegramUsername : null,
            'metadata_json'     => $metadata,
        ]);

        AuditService::log('notification_telegram_linked', 'telegram_link', (int) $link['id'], null, [
            'user_id'           => (int) $link['user_id'],
            'telegram_user_id'  => $telegramUserId,
            'telegram_username' => $telegramUsername,
        ]);

        $this->sendTelegramMessage(
            (string) $bot['bot_token'],
            $chatId,
            'Collegamento completato. Da ora puoi ricevere notifiche personali da Favilla su questa chat.'
        );

        return ['ok' => true, 'message' => 'Link completato.', 'status' => 200];
    }

    private function ensurePendingLink(int $userId, int $botId, bool $forceNewToken = false): array
    {
        $linked = $this->linkRepo->findLinkedByUserId($userId);
        if ($linked && !$forceNewToken) {
            return $linked;
        }

        $token = $this->generateToken();
        return $this->linkRepo->ensurePendingLink($userId, $botId, $token);
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    private function extractStartToken(string $text): ?string
    {
        if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9\-_]{10,})$/', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^[A-Za-z0-9\-_]{10,}$/', $text)) {
            return $text;
        }

        return null;
    }

    private function buildWebhookUrl(string $secret): string
    {
        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $basePath = rtrim((string) config('app.base_path', ''), '/');
        return $baseUrl . $basePath . '/notifications/telegram/webhook/' . rawurlencode($secret);
    }

    private function sendTelegramMessage(string $botToken, string $chatId, string $message): void
    {
        if ($botToken === '' || $chatId === '') {
            return;
        }

        $payload = json_encode([
            'chat_id' => $chatId,
            'text'    => $message,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_get_contents(
            'https://api.telegram.org/bot' . $botToken . '/sendMessage',
            false,
            stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 15,
                ],
            ])
        );
    }
}
