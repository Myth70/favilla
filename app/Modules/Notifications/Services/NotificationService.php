<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationsRepository;
use PDO;

/**
 * NotificationService — usabile da qualsiasi modulo.
 *
 * Uso raccomandato da un qualsiasi controller/service (template-first):
 *   use App\Modules\Notifications\Services\NotificationService;
 *   NotificationService::dispatchEventToUser(
 *       'modulo.evento',
 *       'Modulo',
 *       $userId,
 *       ['key' => 'value'],
 *       '/percorso/opzionale'
 *   );
 *
 * API legacy compatibile:
 *   NotificationService::send(...)
 *   NotificationService::sendToRole(...)
 */
class NotificationService
{
    private static function dispatcher(): NotificationDispatcherService
    {
        return app(NotificationDispatcherService::class);
    }

    private static function repo(): NotificationsRepository
    {
        return app(NotificationsRepository::class);
    }

    // ------------------------------------------------------------------
    // Metodi di lettura (delegano al repository)
    // ------------------------------------------------------------------

    public static function getUnreadCount(int $userId): int
    {
        return self::repo()->getUnreadCountForUser($userId);
    }

    public static function getUnread(int $userId, int $limit = 8): array
    {
        return self::repo()->getUnreadForUser($userId, $limit);
    }

    public static function getPaged(int $userId, int $page, int $perPage = 20, ?string $filter = null): array
    {
        return self::repo()->getPagedForUser($userId, $page, $perPage, $filter);
    }

    public static function markAsRead(int $id, int $userId): bool
    {
        return self::repo()->markAsRead($id, $userId);
    }

    public static function markAllAsRead(int $userId): void
    {
        self::repo()->markAllAsRead($userId);
    }

    public static function markManyAsRead(array $ids, int $userId): int
    {
        return self::repo()->markManyAsRead($ids, $userId);
    }

    public static function deleteForUser(int $id, int $userId): bool
    {
        return self::repo()->deleteForUser($id, $userId);
    }

    public static function deleteManyForUser(array $ids, int $userId): int
    {
        return self::repo()->deleteManyForUser($ids, $userId);
    }

    /**
     * Elimina le notifiche piu' vecchie di $days giorni (lette).
     * Restituisce il numero di righe eliminate.
     */
    public static function deleteOlderThan(int $days): int
    {
        return self::repo()->deleteOlderThan($days);
    }

    /**
     * Restituisce la lista degli utenti attivi (id, name, email).
     * Utile per il form admin di invio notifiche.
     */
    public static function getActiveUsers(): array
    {
        /** @var PDO $pdo */
        $pdo = app(PDO::class);
        return $pdo->query(
            'SELECT id, name, email FROM users
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY name ASC'
        )->fetchAll();
    }

    /**
     * Restituisce la lista dei ruoli disponibili (id, name, slug).
     */
    public static function getAvailableRoles(): array
    {
        /** @var PDO $pdo */
        $pdo = app(PDO::class);
        return $pdo->query(
            'SELECT id, name, slug FROM roles ORDER BY name ASC'
        )->fetchAll();
    }

    /**
     * Verifica che un utente esista ed e' attivo.
     */
    public static function isValidUser(int $userId): bool
    {
        /** @var PDO $pdo */
        $pdo  = app(PDO::class);
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL'
        );
        $stmt->execute([$userId]);
        return (bool) $stmt->fetch();
    }

    // ------------------------------------------------------------------
    // Metodi di scrittura
    // ------------------------------------------------------------------

    /**
     * Invia una notifica a un singolo utente.
     *
     * @param int         $toUserId   ID del destinatario
     * @param string      $title      Titolo breve
     * @param string      $body       Testo descrittivo (opzionale)
     * @param string      $type       info | success | warning | danger
     * @param string|null $link       URL di navigazione opzionale
     * @param int|null    $fromUserId ID mittente (default: utente corrente in sessione)
     * @param string|null $icon       Classe Font Awesome custom (es. 'fa-shopping-cart'). Se null, usa icona di tipo.
     * @return int                    ID della notifica creata
     */
    public static function send(
        int     $toUserId,
        string  $title,
        string  $body       = '',
        string  $type       = 'info',
        ?string $link       = null,
        ?int    $fromUserId = null,
        ?string $icon       = null
    ): int {
        if ($fromUserId === null) {
            $fromUserId = auth()['id'] ?? null;
        }

        $result = self::dispatcher()->dispatchEventToUsers(
            'system.direct_send',
            'Notifications',
            [$toUserId],
            [
                'name' => 'Invio diretto sistema',
                'description' => 'Evento generico usato dall\'API send()',
                'default_level' => 'info',
                'is_system' => true,
            ],
            [
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'link' => $link,
                'icon' => $icon,
                'context' => [
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                ],
            ],
            $fromUserId,
            false
        );

        return (int) ($result['legacy_notification_id'] ?? $result['dispatch_id']);
    }

    /**
     * Invia la stessa notifica a tutti gli utenti di un determinato ruolo.
     *
     * @param string $roleSlug  Slug del ruolo (colonna `slug` della tabella roles)
     * @return int[]            Array di ID notifiche create
     */
    public static function sendToRole(
        string  $roleSlug,
        string  $title,
        string  $body       = '',
        string  $type       = 'info',
        ?string $link       = null,
        ?int    $fromUserId = null,
        ?string $icon       = null
    ): array {
        if ($fromUserId === null) {
            $fromUserId = auth()['id'] ?? null;
        }

        $result = self::dispatcher()->dispatchEventToRole(
            'system.direct_send',
            'Notifications',
            $roleSlug,
            [
                'name' => 'Invio diretto sistema',
                'description' => 'Evento generico usato dall\'API sendToRole()',
                'default_level' => 'info',
                'is_system' => true,
            ],
            [
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'link' => $link,
                'icon' => $icon,
                'context' => [
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                ],
            ],
            $fromUserId,
            false
        );

        return $result['legacy_notification_ids'] ?? [];
    }

    /**
     * @param array<string, mixed> $eventMeta
     * @param array<string, mixed> $context
     */
    public static function sendEventToUser(
        string $eventSlug,
        string $sourceModule,
        int $toUserId,
        string $title,
        string $body = '',
        string $type = 'info',
        ?string $link = null,
        ?int $fromUserId = null,
        ?string $icon = null,
        array $context = [],
        array $eventMeta = []
    ): int {
        if ($fromUserId === null) {
            $fromUserId = auth()['id'] ?? null;
        }

        $result = self::dispatcher()->dispatchEventToUsers(
            $eventSlug,
            $sourceModule,
            [$toUserId],
            $eventMeta,
            [
                'title'   => $title,
                'body'    => $body,
                'type'    => $type,
                'link'    => $link,
                'icon'    => $icon,
                'context' => $context,
            ],
            $fromUserId,
            (bool) ($eventMeta['is_system'] ?? false)
        );

        return (int) ($result['legacy_notification_ids'][0] ?? $result['dispatch_id']);
    }

    /**
     * @param array<string, mixed> $eventMeta
     * @param array<string, mixed> $context
     * @return int[]
     */
    public static function sendEventToRole(
        string $eventSlug,
        string $sourceModule,
        string $roleSlug,
        string $title,
        string $body = '',
        string $type = 'info',
        ?string $link = null,
        ?int $fromUserId = null,
        ?string $icon = null,
        array $context = [],
        array $eventMeta = []
    ): array {
        if ($fromUserId === null) {
            $fromUserId = auth()['id'] ?? null;
        }

        $result = self::dispatcher()->dispatchEventToRole(
            $eventSlug,
            $sourceModule,
            $roleSlug,
            $eventMeta,
            [
                'title'   => $title,
                'body'    => $body,
                'type'    => $type,
                'link'    => $link,
                'icon'    => $icon,
                'context' => $context,
            ],
            $fromUserId,
            (bool) ($eventMeta['is_system'] ?? false)
        );

        return $result['legacy_notification_ids'] ?? [];
    }

    /**
     * Dispatch template-first notification to one user using only event slug + context.
     * The visible content is fully controlled by dispatcher templates.
     *
     * @param array<string, mixed> $context
     */
    public static function dispatchEventToUser(
        string $eventSlug,
        string $sourceModule,
        int $toUserId,
        array $context = [],
        ?string $link = null,
        ?int $fromUserId = null,
        string $type = 'info',
        ?string $icon = null
    ): int {
        if ($fromUserId === null) {
            $fromUserId = auth()['id'] ?? null;
        }

        return self::sendEventToUser(
            $eventSlug,
            $sourceModule,
            $toUserId,
            '',
            '',
            $type,
            $link,
            $fromUserId,
            $icon,
            $context,
            []
        );
    }

    /**
     * Dispatch template-first notification to all users in a role using only event slug + context.
     *
     * @param array<string, mixed> $context
     * @return int[]
     */
    public static function dispatchEventToRole(
        string $eventSlug,
        string $sourceModule,
        string $roleSlug,
        array $context = [],
        ?string $link = null,
        ?int $fromUserId = null,
        string $type = 'info',
        ?string $icon = null
    ): array {
        if ($fromUserId === null) {
            $fromUserId = auth()['id'] ?? null;
        }

        return self::sendEventToRole(
            $eventSlug,
            $sourceModule,
            $roleSlug,
            '',
            '',
            $type,
            $link,
            $fromUserId,
            $icon,
            $context,
            []
        );
    }
}
