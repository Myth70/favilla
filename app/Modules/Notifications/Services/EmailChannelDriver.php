<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Services\MailService;
use PDO;

class EmailChannelDriver implements NotificationChannelDriverInterface
{
    private PDO $pdo;
    private MailService $mailService;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
        $this->mailService = app(MailService::class);
    }

    public function channel(): string
    {
        return 'email';
    }

    public function send(array $job): array
    {
        $user = $this->findRecipient((int) $job['user_id']);
        if (!$user || empty($user['email'])) {
            return [
                'status' => 'skipped',
                'provider_message_id' => null,
                'error_message' => 'Destinatario senza email valida.',
            ];
        }

        $subject = trim((string) ($job['delivery_subject'] ?? $job['dispatch_title'] ?? 'Notifica'));
        $body = (string) ($job['delivery_body'] ?? '');

        if ($body === '') {
            $body = $this->buildDefaultBody($job);
        }

        $success = $this->mailService->send((string) $user['email'], $subject, $body);

        return [
            'status' => $success ? 'sent' : 'failed',
            'provider_message_id' => null,
            'error_message' => $success ? null : 'Invio email fallito.',
        ];
    }

    private function findRecipient(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buildDefaultBody(array $job): string
    {
        $title = htmlspecialchars((string) ($job['dispatch_title'] ?? 'Notifica'), ENT_QUOTES, 'UTF-8');
        $body  = nl2br(htmlspecialchars((string) ($job['dispatch_body'] ?? ''), ENT_QUOTES, 'UTF-8'));
        $link  = ($job['delivery_link'] ?? null) ?: ($job['dispatch_link'] ?? null);

        $html = '<h2>' . $title . '</h2>';
        if ($body !== '') {
            $html .= '<p>' . $body . '</p>';
        }
        if ($link) {
            $safeLink = htmlspecialchars((string) $link, ENT_QUOTES, 'UTF-8');
            $html .= '<p><a href="' . $safeLink . '">Apri la notifica</a></p>';
        }

        return $html;
    }
}
