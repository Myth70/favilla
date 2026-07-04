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
