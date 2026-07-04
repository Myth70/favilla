<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Discovers dashboard widget providers and aggregates dashboard data.
 *
 * Loading is split in two phases so widgets render in parallel without lag:
 *  - getWidgetCatalog() builds the cheap metadata list (skeleton / settings).
 *  - renderWidget() computes a single widget's body on demand (one HTTP request
 *    per widget), with a short server-side cache (WidgetDataCache).
 */
class DashboardService
{
    /** Default seconds a widget payload stays cached (providers may override via meta 'cache_ttl'). */
    private const DEFAULT_CACHE_TTL = 120;

    private DashboardColorPalette $colorPalette;
    private WidgetDataCache $widgetCache;

    /** @var array<int, DashboardWidgetProvider>|null */
    private ?array $providers = null;

    public function __construct()
    {
        $this->colorPalette = app(DashboardColorPalette::class);
        $this->widgetCache = app(WidgetDataCache::class);
    }

    /**
     * Get all discovered dashboard widget providers.
     *
     * @return array<int, DashboardWidgetProvider>
     */
    public function getProviders(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $this->providers = app(\App\Services\ProviderDiscovery::class)->discoverFlat(
            'dashboard_provider',
            'DashboardProvider',
            [DashboardWidgetProvider::class]
        );

        return $this->providers;
    }

    /**
     * Build the dashboard for rendering: an ordered list of items where each is
     * either a ready-to-render widget (fast widgets, computed inline in one
     * request) or a lazy placeholder (slow widgets that do external I/O, e.g.
     * weather — loaded in a separate request so they don't block the grid).
     *
     * This keeps the whole grid to a single backend request regardless of how
     * many widgets exist (one framework bootstrap), instead of one HTTP request
     * — and one full bootstrap — per widget.
     *
     * @return array<int, array{lazy: bool, meta?: array<string,mixed>, widget?: array<string,mixed>}>
     */
    public function buildDashboard(int $userId): array
    {
        $items = [];
        foreach ($this->orderedEntries($userId) as $entry) {
            $meta = $entry['meta'];

            if (!empty($meta['lazy'])) {
                $items[] = ['lazy' => true, 'meta' => $meta];
                continue;
            }

            $widget = $this->renderFromMeta($userId, $entry['provider'], $meta);
            if ($widget !== null) {
                $items[] = ['lazy' => false, 'widget' => $widget];
            }
            // null = nothing to show → omit the column entirely.
        }

        return $items;
    }

    /**
     * Lightweight widget catalog (metadata only), filtered by permissions and
     * ordered/filtered by user preferences.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWidgetCatalog(int $userId): array
    {
        return array_map(
            static fn (array $entry): array => $entry['meta'],
            $this->orderedEntries($userId)
        );
    }

    /**
     * Permission-filtered, preference-ordered provider+metadata entries
     * (visible widgets only). Shared by getWidgetCatalog() and buildDashboard().
     *
     * @return array<int, array{provider: DashboardWidgetProvider, meta: array<string, mixed>}>
     */
    private function orderedEntries(int $userId): array
    {
        $entries = array_values(array_filter(
            $this->collectEntries($userId),
            function (array $entry): bool {
                $perm = $entry['meta']['permission'] ?? null;
                return empty($perm) || has_permission($perm);
            }
        ));

        $prefs = app(WidgetPreferencesService::class)->getUserPrefs($userId);

        if (!empty($prefs)) {
            // Filter out hidden widgets
            $entries = array_filter($entries, function (array $entry) use ($prefs) {
                $id = $entry['meta']['id'];
                if (isset($prefs[$id])) {
                    return $prefs[$id]['visible'];
                }
                return true; // No preference = visible by default
            });

            // Sort: widgets with prefs first (by sort_order), then the rest in original order.
            // usort reindexes, so $entries stays a list in both branches.
            usort($entries, function (array $a, array $b) use ($prefs) {
                $aOrder = $prefs[$a['meta']['id']]['sort_order'] ?? 9999;
                $bOrder = $prefs[$b['meta']['id']]['sort_order'] ?? 9999;
                return $aOrder <=> $bOrder;
            });
        }

        return $entries;
    }

    /**
     * Get ALL available widgets for user (permission-filtered but ignoring
     * visibility prefs). Used by the widget settings UI. Metadata only.
     *
     * @return array<int, array<string, mixed>> metadata with '_visible'/'_sort_order' added
     */
    public function getAllAvailableWidgets(int $userId): array
    {
        $metas = $this->filterByPermissions($this->collectMeta($userId));

        $prefs = app(WidgetPreferencesService::class)->getUserPrefs($userId);

        foreach ($metas as &$widget) {
            $widget['_visible'] = true;
            $widget['_sort_order'] = 9999;
            if (isset($prefs[$widget['id']])) {
                $widget['_visible'] = $prefs[$widget['id']]['visible'];
                $widget['_sort_order'] = $prefs[$widget['id']]['sort_order'];
            }
        }
        unset($widget);

        usort($metas, function (array $a, array $b) {
            return $a['_sort_order'] <=> $b['_sort_order'];
        });

        return $metas;
    }

    /**
     * Render a single widget on demand: locate its provider, enforce the
     * permission, compute (and cache) its payload, merge over the metadata and
     * decorate colors. Returns null when the widget is forbidden or has nothing
     * to show (so the caller can drop the placeholder).
     *
     * @return array<string, mixed>|null
     */
    public function renderWidget(int $userId, string $widgetId): ?array
    {
        foreach ($this->collectEntries($userId) as $entry) {
            if (($entry['meta']['id'] ?? null) !== $widgetId) {
                continue;
            }

            $meta = $entry['meta'];
            if (!empty($meta['permission']) && !has_permission($meta['permission'])) {
                return null;
            }

            return $this->renderFromMeta($userId, $entry['provider'], $meta);
        }

        return null;
    }

    /**
     * Compute (and cache) a widget's payload from its metadata, then merge and
     * decorate it into a full widget array. Returns null when there is nothing
     * to show. Assumes the permission was already checked by the caller.
     *
     * @param  array<string, mixed> $meta
     * @return array<string, mixed>|null
     */
    private function renderFromMeta(int $userId, DashboardWidgetProvider $provider, array $meta): ?array
    {
        $widgetId = (string) $meta['id'];
        $ttl = (int) ($meta['cache_ttl'] ?? self::DEFAULT_CACHE_TTL);
        // Locale is part of the key: widget payloads embed translated labels
        // (t()), so a language switch must not serve another locale's cached
        // fragment until its own TTL naturally expires.
        $key = 'u' . $userId . '_' . locale() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $widgetId);

        $fragment = $this->widgetCache->remember($key, $ttl, static function () use ($provider, $userId, $widgetId) {
            try {
                return $provider->getWidgetData($userId, $widgetId);
            } catch (\Throwable) {
                return null;
            }
        });

        if ($fragment === null) {
            return null;
        }

        // getWidgetData returns at least 'data', and may override 'label'/'icon'.
        $widget = array_merge($meta, $fragment);
        unset($widget['cache_ttl'], $widget['lazy']);

        return $this->decorateWidgetColors($widget);
    }

    /**
     * Collect widget metadata (no data) from every provider, de-duplicated by id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectMeta(int $userId): array
    {
        return array_map(
            static fn (array $entry): array => $entry['meta'],
            $this->collectEntries($userId)
        );
    }

    /**
     * Collect provider+metadata entries (ordered, de-duplicated by widget id).
     *
     * @return array<int, array{provider: DashboardWidgetProvider, meta: array<string, mixed>}>
     */
    private function collectEntries(int $userId): array
    {
        $entries = [];
        $seen = [];

        foreach ($this->getProviders() as $provider) {
            try {
                $metas = $provider->getWidgets($userId);
            } catch (\Throwable) {
                continue; // A broken provider must not take down the dashboard.
            }

            foreach ($metas as $meta) {
                $id = $meta['id'] ?? null;
                if (!is_string($id) || $id === '' || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $entries[] = ['provider' => $provider, 'meta' => $meta];
            }
        }

        return $entries;
    }

    /**
     * Filter widgets by user permissions.
     *
     * @param  array<int, array<string, mixed>> $widgets
     * @return array<int, array<string, mixed>>
     */
    private function filterByPermissions(array $widgets): array
    {
        return array_values(array_filter($widgets, function (array $widget) {
            if (empty($widget['permission'])) {
                return true;
            }
            return has_permission($widget['permission']);
        }));
    }

    private function decorateWidgetColors(array $widget): array
    {
        $type = $widget['type'] ?? 'stat';
        $themeColor = $this->colorPalette->resolveWidgetThemeColor($widget);
        $assignedColor = match ($type) {
            'stat' => $widget['data']['color'] ?? 'primary',
            'list', 'chart' => $widget['data']['iconColor'] ?? 'primary',
            default => null,
        };

        $displayColor = $this->colorPalette->resolveDisplayColor(
            is_string($assignedColor) ? $assignedColor : null,
            $themeColor
        );

        $widget['_themeColor'] = $themeColor;
        $widget['_displayColor'] = $displayColor;
        $widget['_displayTone'] = $this->colorPalette->getTone($displayColor);

        return $widget;
    }

    /**
     * Get unread notification count.
     */
    public function getUnreadCount(int $userId): int
    {
        if (!isModuleEnabled('Notifications')) {
            return 0;
        }

        try {
            return NotificationService::getUnreadCount($userId);
        } catch (\Throwable) {
            return 0;
        }
    }
}
