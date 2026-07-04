<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories;

use App\Repositories\BaseRepository;
use PDO;

class NotificationEventRepository extends BaseRepository
{
    protected string $table = 'notification_event_types';
    protected array $fillable = [
        'slug',
        'module_slug',
        'name',
        'description',
        'context_schema',
        'source',
        'default_level',
        'icon',
        'color',
        'is_system',
    ];
    protected bool $timestamps = true;
    /** @var array<string, bool> */
    private array $eventTypeColumnCache = [];

    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }

    public function findOrCreateLegacyEvent(string $moduleSlug, string $moduleName, string $type = 'info', ?string $icon = null): array
    {
        $slug = 'legacy.' . $moduleSlug . '.direct';
        return $this->findOrCreateEvent(
            $slug,
            $moduleSlug,
            'Legacy notification - ' . $moduleName,
            'Evento legacy deprecato per compatibilita test',
            $type,
            $icon,
            null,
            false,
            'dynamic',
            null
        );
    }

    public function findOrCreateEvent(
        string $slug,
        string $moduleSlug,
        string $name,
        ?string $description,
        string $defaultLevel = 'info',
        ?string $icon = null,
        ?string $color = null,
        bool $isSystem = false,
        ?string $source = null,
        mixed $contextSchema = null
    ): array {
        $event = $this->findBySlug($slug);

        if (!$event) {
            $insertData = [
                'slug'          => $slug,
                'module_slug'   => $moduleSlug,
                'name'          => $name,
                'description'   => $description,
                'default_level' => $defaultLevel,
                'icon'          => $icon,
                'color'         => $color,
                'is_system'     => $isSystem ? 1 : 0,
            ];

            if ($this->hasEventTypeColumn('source')) {
                $insertData['source'] = $source ?? 'dynamic';
            }
            if ($this->hasEventTypeColumn('context_schema')) {
                $insertData['context_schema'] = $this->normalizeContextSchema($contextSchema);
            }

            $eventId = $this->create($insertData);
            $event = $this->find($eventId);
        } else {
            // Preserve admin-customized icon/color: only apply module.json
            // defaults when the DB value is NULL (never been set by admin)
            $updateData = [
                'module_slug'    => $moduleSlug,
                'name'           => $name,
                'description'    => $description,
                'default_level'  => $defaultLevel,
                'icon'           => $event['icon'] ?? $icon,
                'color'          => $event['color'] ?? $color,
                'is_system'      => $isSystem ? 1 : 0,
            ];

            if ($this->hasEventTypeColumn('source')) {
                $updateData['source'] = $source ?? ($event['source'] ?? 'dynamic');
            }
            if ($this->hasEventTypeColumn('context_schema')) {
                $updateData['context_schema'] = $this->normalizeContextSchema($contextSchema) ?? ($event['context_schema'] ?? null);
            }

            $this->update((int) $event['id'], $updateData);
            $event = $this->find((int) $event['id']);
        }

        if (!$event) {
            throw new \RuntimeException('Impossibile inizializzare l\'evento notifiche: ' . $slug);
        }

        $this->ensureDefaultBindings((int) $event['id']);
        return $event;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllEventsOrdered(): array
    {
        $stmt = $this->pdo->query(
            'SELECT ' . $this->eventSelectColumns() . '
             FROM notification_event_types
             ORDER BY module_slug ASC, name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ensureDefaultBindings(int $eventTypeId): void
    {
        $this->ensureChannelBinding($eventTypeId, 'in_app', true, null, null, null);
        $this->ensureChannelBinding($eventTypeId, 'email', false, null, null, null);
        $this->ensureChannelBinding($eventTypeId, 'telegram', false, null, null, null);
    }

    public function ensureChannelBinding(
        int $eventTypeId,
        string $channelSlug,
        bool $isEnabled,
        ?string $subjectTemplate,
        ?string $bodyTemplate,
        ?string $layoutConfig
    ): void {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM notification_event_channel_bindings WHERE event_type_id = ? AND channel_slug = ? LIMIT 1'
        );
        $stmt->execute([$eventTypeId, $channelSlug]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO notification_event_channel_bindings
             (event_type_id, channel_slug, is_enabled, subject_template, body_template, layout_config)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $eventTypeId,
            $channelSlug,
            $isEnabled ? 1 : 0,
            $subjectTemplate,
            $bodyTemplate,
            $layoutConfig,
        ]);
    }

    /**
     * Non-legacy events grouped by module_slug.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getExplicitEventCatalog(): array
    {
        $stmt = $this->pdo->query(
            'SELECT ' . $this->eventSelectColumns() . "
             FROM notification_event_types
               WHERE slug NOT LIKE 'legacy.%'
             ORDER BY module_slug ASC, is_system DESC, name ASC"
        );

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[$row['module_slug']][] = $row;
        }

        return $grouped;
    }

    /**
     * Upsert a channel binding for an event type.
     */
    public function upsertChannelBinding(
        int $eventTypeId,
        string $channelSlug,
        bool $isEnabled,
        ?string $subjectTemplate,
        ?string $bodyTemplate,
        ?string $layoutConfig
    ): void {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM notification_event_channel_bindings WHERE event_type_id = ? AND channel_slug = ? LIMIT 1'
        );
        $stmt->execute([$eventTypeId, $channelSlug]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $update = $this->pdo->prepare(
                'UPDATE notification_event_channel_bindings
                 SET is_enabled = ?, subject_template = ?, body_template = ?, layout_config = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([
                $isEnabled ? 1 : 0,
                $subjectTemplate,
                $bodyTemplate,
                $layoutConfig,
                (int) $existingId,
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO notification_event_channel_bindings
             (event_type_id, channel_slug, is_enabled, subject_template, body_template, layout_config)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $eventTypeId,
            $channelSlug,
            $isEnabled ? 1 : 0,
            $subjectTemplate,
            $bodyTemplate,
            $layoutConfig,
        ]);
    }

    public function getChannelBindingsForEvent(string $eventSlug): array
    {
        $contextSchemaSelect = $this->hasEventTypeColumn('context_schema')
            ? 'e.context_schema'
            : 'NULL AS context_schema';
        $sourceSelect = $this->hasEventTypeColumn('source')
            ? 'e.source'
            : "'dynamic' AS source";

        $stmt = $this->pdo->prepare(
            'SELECT
                e.id AS event_type_id,
                e.slug AS event_slug,
                e.module_slug,
                ' . $contextSchemaSelect . ',
                ' . $sourceSelect . ',
                e.default_level,
                e.icon AS default_icon,
                e.color AS default_color,
                e.is_system,
                b.channel_slug,
                b.is_enabled,
                b.subject_template,
                b.body_template,
                b.layout_config,
                c.name AS channel_name,
                c.is_enabled AS channel_active,
                c.sort_order
             FROM notification_event_types e
             JOIN notification_event_channel_bindings b ON b.event_type_id = e.id
             JOIN notification_channels c ON c.slug = b.channel_slug
             WHERE e.slug = ?
             ORDER BY c.sort_order ASC, b.id ASC'
        );
        $stmt->execute([$eventSlug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normalizeContextSchema(mixed $contextSchema): ?string
    {
        if ($contextSchema === null) {
            return null;
        }

        if (is_string($contextSchema)) {
            $trimmed = trim($contextSchema);
            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_array($contextSchema)) {
            return json_encode($contextSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
        }

        return null;
    }

    private function hasEventTypeColumn(string $column): bool
    {
        // Difensivo: il metodo è privato e oggi chiamato solo con letterali, ma
        // non costruiamo MAI SQL da una stringa fuori da questa whitelist.
        if (!in_array($column, ['context_schema', 'source'], true)) {
            return false;
        }

        if (array_key_exists($column, $this->eventTypeColumnCache)) {
            return $this->eventTypeColumnCache[$column];
        }

        try {
            $stmt = $this->pdo->query('SELECT ' . $column . ' FROM notification_event_types LIMIT 1');
            $stmt->fetch();
            $this->eventTypeColumnCache[$column] = true;
        } catch (\Throwable) {
            $this->eventTypeColumnCache[$column] = false;
        }

        return $this->eventTypeColumnCache[$column];
    }

    private function eventSelectColumns(): string
    {
        $columns = [
            'id',
            'slug',
            'module_slug',
            'name',
            'description',
        ];

        $columns[] = $this->hasEventTypeColumn('context_schema') ? 'context_schema' : 'NULL AS context_schema';
        $columns[] = $this->hasEventTypeColumn('source') ? 'source' : "'dynamic' AS source";

        $columns[] = 'default_level';
        $columns[] = 'icon';
        $columns[] = 'color';
        $columns[] = 'is_system';

        return implode(', ', $columns);
    }
}
