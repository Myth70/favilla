<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class NotificationPreferenceRepository extends BaseRepository
{
    protected string $table = 'user_notification_preferences';
    protected array $fillable = [
        'user_id',
        'module_slug',
        'event_slug',
        'channel_slug',
        'is_enabled',
    ];
    protected bool $timestamps = true;

    public function resolveChannelBindings(int $userId, string $moduleSlug, string $eventSlug, array $bindings): array
    {
        if (empty($bindings)) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT channel_slug, event_slug, is_enabled
             FROM user_notification_preferences
             WHERE user_id = ?
               AND module_slug = ?
               AND event_slug IN (?, ?)'
        );
        $stmt->execute([$userId, $moduleSlug, '', $eventSlug]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $moduleDefaults = [];
        $eventOverrides = [];

        foreach ($rows as $row) {
            $target = $row['event_slug'] === '' ? 'module' : 'event';
            if ($target === 'module') {
                $moduleDefaults[$row['channel_slug']] = (bool) $row['is_enabled'];
            } else {
                $eventOverrides[$row['channel_slug']] = (bool) $row['is_enabled'];
            }
        }

        foreach ($bindings as &$binding) {
            $enabled = (bool) $binding['is_enabled'] && (bool) $binding['channel_active'];
            $channel = $binding['channel_slug'];

            if (array_key_exists($channel, $moduleDefaults)) {
                $enabled = $moduleDefaults[$channel] && (bool) $binding['channel_active'];
            }

            if (array_key_exists($channel, $eventOverrides)) {
                $enabled = $eventOverrides[$channel] && (bool) $binding['channel_active'];
            }

            $binding['resolved_enabled'] = $enabled;
        }
        unset($binding);

        return $bindings;
    }

    /**
     * Variante batch di resolveChannelBindings(): risolve i binding per un
     * insieme di utenti con UNA sola query invece di N, eliminando l'N+1 nel
     * fan-out verso un ruolo. Applica la stessa logica per-utente (default di
     * modulo + override di evento) su una copia indipendente di $bindings.
     *
     * @param int[] $userIds
     * @param array<int,array<string,mixed>> $bindings
     * @return array<int,array<int,array<string,mixed>>>  userId => bindings risolti
     */
    public function resolveChannelBindingsForUsers(array $userIds, string $moduleSlug, string $eventSlug, array $bindings): array
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $id): bool => $id > 0
        )));

        if (empty($userIds) || empty($bindings)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT user_id, channel_slug, event_slug, is_enabled
             FROM user_notification_preferences
             WHERE user_id IN ({$placeholders})
               AND module_slug = ?
               AND event_slug IN (?, ?)"
        );
        $stmt->execute(array_merge($userIds, [$moduleSlug, '', $eventSlug]));

        $moduleDefaultsByUser = [];
        $eventOverridesByUser = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uid = (int) $row['user_id'];
            if ($row['event_slug'] === '') {
                $moduleDefaultsByUser[$uid][$row['channel_slug']] = (bool) $row['is_enabled'];
            } else {
                $eventOverridesByUser[$uid][$row['channel_slug']] = (bool) $row['is_enabled'];
            }
        }

        $result = [];
        foreach ($userIds as $uid) {
            $moduleDefaults = $moduleDefaultsByUser[$uid] ?? [];
            $eventOverrides = $eventOverridesByUser[$uid] ?? [];

            $resolved = [];
            foreach ($bindings as $binding) {
                $channel = $binding['channel_slug'];
                $enabled = (bool) $binding['is_enabled'] && (bool) $binding['channel_active'];

                if (array_key_exists($channel, $moduleDefaults)) {
                    $enabled = $moduleDefaults[$channel] && (bool) $binding['channel_active'];
                }
                if (array_key_exists($channel, $eventOverrides)) {
                    $enabled = $eventOverrides[$channel] && (bool) $binding['channel_active'];
                }

                $binding['resolved_enabled'] = $enabled;
                $resolved[] = $binding;
            }
            $result[$uid] = $resolved;
        }

        return $result;
    }

    public function upsertPreference(int $userId, string $moduleSlug, string $eventSlug, string $channelSlug, bool $isEnabled): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM user_notification_preferences
             WHERE user_id = ? AND module_slug = ? AND event_slug = ? AND channel_slug = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $moduleSlug, $eventSlug, $channelSlug]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $this->update((int) $existingId, ['is_enabled' => $isEnabled ? 1 : 0]);
            return;
        }

        $this->create([
            'user_id'     => $userId,
            'module_slug' => $moduleSlug,
            'event_slug'  => $eventSlug,
            'channel_slug' => $channelSlug,
            'is_enabled'  => $isEnabled ? 1 : 0,
        ]);
    }

    /**
     * Module-level preference map for a user.
     *
     * @return array<string, array<string, bool>>
     */
    public function getModulePreferenceMap(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT module_slug, channel_slug, is_enabled
             FROM user_notification_preferences
             WHERE user_id = ? AND event_slug = ?'
        );
        $stmt->execute([$userId, '']);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['module_slug']][$row['channel_slug']] = (bool) $row['is_enabled'];
        }

        return $map;
    }

    /**
     * Event-level preference map for a user.
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    public function getEventPreferenceMap(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT module_slug, event_slug, channel_slug, is_enabled
             FROM user_notification_preferences
             WHERE user_id = ? AND event_slug <> ?'
        );
        $stmt->execute([$userId, '']);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['module_slug']][$row['event_slug']][$row['channel_slug']] = (bool) $row['is_enabled'];
        }

        return $map;
    }

    public function deletePreference(int $userId, string $moduleSlug, string $eventSlug, string $channelSlug): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM user_notification_preferences
             WHERE user_id = ? AND module_slug = ? AND event_slug = ? AND channel_slug = ?'
        );

        $stmt->execute([$userId, $moduleSlug, $eventSlug, $channelSlug]);
        return $stmt->rowCount() > 0;
    }
}
