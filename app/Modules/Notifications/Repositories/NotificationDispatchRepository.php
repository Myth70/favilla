<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class NotificationDispatchRepository extends BaseRepository
{
    protected string $table = 'notification_dispatches';
    protected array $fillable = [
        'event_slug',
        'source_module',
        'recipient_user_id',
        'recipient_role_slug',
        'title',
        'body',
        'type',
        'link',
        'icon',
        'color',
        'payload_json',
        'created_by',
        'bypass_preferences',
        'status',
        'total_recipients',
        'total_deliveries',
    ];

    public function createDispatch(array $data): int
    {
        return $this->create($data);
    }

    public function findWithSummary(int $dispatchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*,
                COALESCE(SUM(nd.status = "sent"), 0) AS sent_count,
                COALESCE(SUM(nd.status = "queued"), 0) AS queued_count,
                COALESCE(SUM(nd.status = "processing"), 0) AS processing_count,
                COALESCE(SUM(nd.status = "failed"), 0) AS failed_count,
                COALESCE(SUM(nd.status = "skipped"), 0) AS skipped_count
             FROM notification_dispatches d
             LEFT JOIN notification_deliveries nd ON nd.dispatch_id = d.id
             WHERE d.id = ?
             GROUP BY d.id'
        );
        $stmt->execute([$dispatchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Dispatch status counts.
     *
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT status, COUNT(*) AS cnt FROM notification_dispatches GROUP BY status'
        );

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[$row['status']] = (int) $row['cnt'];
        }
        return $map;
    }

    /**
     * Extract payload placeholder keys from recent dispatches.
     *
     * @return array{global:array<int,string>, per_event:array<string,array<int,string>>, sampled_dispatches:int}
     */
    public function getPayloadPlaceholderHints(int $limit = 300): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT event_slug, payload_json
             FROM notification_dispatches
             WHERE payload_json IS NOT NULL AND payload_json <> ? AND payload_json <> ?
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->execute(['', '{}', $limit]);

        $global = [];
        $perEvent = [];
        $count = 0;

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            $count++;
            $eventSlug = (string) ($row['event_slug'] ?? '');
            $keys = self::extractPayloadKeys($payload);

            foreach ($keys as $key) {
                $global[$key] = true;
                if ($eventSlug !== '') {
                    $perEvent[$eventSlug][$key] = true;
                }
            }
        }

        $globalKeys = array_keys($global);
        sort($globalKeys);

        $perEventKeys = [];
        foreach ($perEvent as $eventSlug => $eventKeysMap) {
            $eventKeys = array_keys($eventKeysMap);
            sort($eventKeys);
            $perEventKeys[$eventSlug] = $eventKeys;
        }
        ksort($perEventKeys);

        return [
            'global'             => $globalKeys,
            'per_event'          => $perEventKeys,
            'sampled_dispatches' => $count,
        ];
    }

    /**
     * @return string[]
     */
    private static function extractPayloadKeys(array $payload, string $prefix = ''): array
    {
        $keys = [];

        foreach ($payload as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalizedKey = trim($prefix . $key, '.');
            $keys[] = $normalizedKey;

            if (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
                $nested = self::extractPayloadKeys($value, $normalizedKey . '.');
                foreach ($nested as $nestedKey) {
                    $keys[] = $nestedKey;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    public function refreshStatus(int $dispatchId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS cnt
             FROM notification_deliveries
             WHERE dispatch_id = ?
             GROUP BY status'
        );
        $stmt->execute([$dispatchId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [
            'pending'    => 0,
            'queued'     => 0,
            'processing' => 0,
            'sent'       => 0,
            'failed'     => 0,
            'skipped'    => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['cnt'];
        }

        $totalDeliveries = array_sum($counts);
        $status = 'pending';

        if ($counts['queued'] > 0 || $counts['processing'] > 0 || $counts['pending'] > 0) {
            $status = 'queued';
        } elseif ($counts['failed'] > 0 && $counts['sent'] === 0) {
            $status = 'failed';
        } elseif ($counts['failed'] > 0 || $counts['skipped'] > 0) {
            $status = 'partial';
        } elseif ($counts['sent'] > 0) {
            $status = 'sent';
        }

        $update = $this->pdo->prepare(
            'UPDATE notification_dispatches SET status = ?, total_deliveries = ? WHERE id = ?'
        );
        $update->execute([$status, $totalDeliveries, $dispatchId]);
    }
}
