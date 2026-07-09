<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class WebhookEndpointRepository extends BaseRepository
{
    protected string $table = 'webhook_endpoints';
    protected bool $timestamps = true;
    protected bool $softDelete = true;
    protected bool $auditable = true;
    protected array $fillable = [
        'url',
        'secret',
        'event_types',
        'description',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allOrdered(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM webhook_endpoints WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Endpoint attivi sottoscritti a un evento. Il match su event_types (JSON
     * array di slug) è fatto in PHP: gli array JSON sono piccoli e questo evita
     * dipendenze da JSON_CONTAINS (assente su SQLite nei test).
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeForEvent(string $eventSlug): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM webhook_endpoints WHERE deleted_at IS NULL AND is_active = 1'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matched = [];
        foreach ($rows as $row) {
            $events = json_decode((string) $row['event_types'], true);
            if (is_array($events) && in_array($eventSlug, $events, true)) {
                $matched[] = $row;
            }
        }
        return $matched;
    }
}
