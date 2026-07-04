<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\WidgetPreferencesRepository;

/**
 * WidgetPreferencesService — gestisce le preferenze widget della dashboard.
 *
 * Cache per-request privata (non in `$_SESSION`): evita query DB ripetute
 * nella stessa richiesta, ma non sopravvive tra request — il chiamante
 * non deve assumere consistenza cross-request.
 */
class WidgetPreferencesService
{
    private WidgetPreferencesRepository $repo;

    /** @var array<int, array<string, array{sort_order: int, visible: bool}>> */
    private array $cache = [];

    public function __construct()
    {
        $this->repo = app(WidgetPreferencesRepository::class);
    }

    /**
     * Get user widget preferences as a keyed map.
     *
     * @return array<string, array{sort_order: int, visible: bool}>  keyed by widget_id
     */
    public function getUserPrefs(int $userId): array
    {
        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $rows = $this->repo->getByUserId($userId);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['widget_id']] = [
                'sort_order' => (int) $row['sort_order'],
                'visible'    => (bool) $row['visible'],
            ];
        }

        $this->cache[$userId] = $map;
        return $map;
    }

    /**
     * Save full widget layout (order + visibility).
     * Replaces the entire set of preferences for the user, removing any
     * orphan entries left by previously removed modules.
     *
     * @param array<int, array{id: string, visible: bool}> $widgets  in display order
     */
    public function saveLayout(int $userId, array $widgets): void
    {
        $items = [];
        foreach ($widgets as $index => $w) {
            $items[] = [
                'widget_id'  => $w['id'],
                'sort_order' => $index,
                'visible'    => $w['visible'] ? 1 : 0,
            ];
        }

        $this->repo->replaceAll($userId, $items);
        $this->refreshCache($userId);
    }

    /**
     * Toggle a single widget's visibility.
     */
    public function toggleWidget(int $userId, string $widgetId, bool $visible): void
    {
        $this->repo->upsertBatch($userId, [
            [
                'widget_id'  => $widgetId,
                'sort_order' => $this->getNextSortOrder($userId, $widgetId),
                'visible'    => $visible ? 1 : 0,
            ],
        ]);
        $this->refreshCache($userId);
    }

    /**
     * Reset to provider defaults (delete all user preferences).
     */
    public function resetToDefaults(int $userId): void
    {
        $this->repo->deleteByUserId($userId);
        unset($this->cache[$userId]);
    }

    private function refreshCache(int $userId): void
    {
        unset($this->cache[$userId]);
        $this->getUserPrefs($userId);
    }

    private function getNextSortOrder(int $userId, string $widgetId): int
    {
        $prefs = $this->getUserPrefs($userId);
        if (isset($prefs[$widgetId])) {
            return $prefs[$widgetId]['sort_order'];
        }
        $max = 0;
        foreach ($prefs as $p) {
            if ($p['sort_order'] > $max) {
                $max = $p['sort_order'];
            }
        }
        return $max + 1;
    }
}
