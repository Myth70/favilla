<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

class DashboardColorPalette
{
    private const EXPLICIT_ROUTE_COLORS = [
        'home' => 'primary',
        'calendar' => 'success',
        'tasks' => 'purple',
        'contacts' => 'teal',
        'files' => 'secondary',
        'reports' => 'orange',
        'admin' => 'dark',
    ];

    private const FALLBACK_CYCLE = ['primary', 'success', 'warning', 'danger', 'info', 'secondary', 'dark'];

    private const TONES = [
        'primary' => ['value' => 'var(--bs-primary)', 'rgb' => 'var(--bs-primary-rgb)'],
        'success' => ['value' => 'var(--bs-success)', 'rgb' => 'var(--bs-success-rgb)'],
        'warning' => ['value' => 'var(--bs-warning)', 'rgb' => 'var(--bs-warning-rgb)'],
        'danger' => ['value' => 'var(--bs-danger)', 'rgb' => 'var(--bs-danger-rgb)'],
        'info' => ['value' => 'var(--bs-info)', 'rgb' => 'var(--bs-info-rgb)'],
        'secondary' => ['value' => 'var(--bs-secondary)', 'rgb' => 'var(--bs-secondary-rgb)'],
        'dark' => ['value' => 'var(--bs-dark)', 'rgb' => 'var(--bs-dark-rgb)'],
        'light' => ['value' => 'var(--bs-secondary)', 'rgb' => 'var(--bs-light-rgb)'],
        'purple' => ['value' => '#6f42c1', 'rgb' => '111,66,193'],
        'teal' => ['value' => '#20c997', 'rgb' => '32,201,151'],
        'orange' => ['value' => '#fd7e14', 'rgb' => '253,126,20'],
        'indigo' => ['value' => '#6610f2', 'rgb' => '102,16,242'],
    ];

    /** @var array<string, string>|null */
    private ?array $quickAccessPalette = null;

    public function assignQuickAccessColors(array $items): array
    {
        $coloredItems = [];
        $cycleIndex = 0;

        foreach ($items as $item) {
            $item['color'] = $this->resolveQuickAccessItemColor($item, $cycleIndex);
            $coloredItems[] = $item;
        }

        return $coloredItems;
    }

    public function lookupPaletteColor(?string $routePrefix = null, ?string $moduleKey = null): ?string
    {
        $palette = $this->getQuickAccessPalette();

        foreach ([$routePrefix, $moduleKey] as $key) {
            $normalized = $this->normalizeKey($key);
            if ($normalized === null) {
                continue;
            }
            if (isset($palette[$normalized])) {
                return $palette[$normalized];
            }
            if (isset(self::EXPLICIT_ROUTE_COLORS[$normalized])) {
                return self::EXPLICIT_ROUTE_COLORS[$normalized];
            }
        }

        return null;
    }

    public function resolveWidgetThemeColor(array $widget): ?string
    {
        $palette = $this->getQuickAccessPalette();

        foreach ($this->extractWidgetKeys($widget) as $key) {
            if (isset($palette[$key])) {
                return $palette[$key];
            }
            if (isset(self::EXPLICIT_ROUTE_COLORS[$key])) {
                return self::EXPLICIT_ROUTE_COLORS[$key];
            }
        }

        return null;
    }

    public function resolveDisplayColor(?string $assignedColor, ?string $themeColor): string
    {
        $normalizedAssignedColor = $this->normalizeKey($assignedColor) ?? 'primary';
        if (!isset(self::TONES[$normalizedAssignedColor])) {
            $normalizedAssignedColor = 'primary';
        }

        $normalizedThemeColor = $this->normalizeKey($themeColor);
        if ($normalizedThemeColor !== null && isset(self::TONES[$normalizedThemeColor])) {
            return $normalizedThemeColor;
        }

        return $normalizedAssignedColor;
    }

    /**
     * @return array{value: string, rgb: string}
     */
    public function getTone(string $color): array
    {
        $normalizedColor = $this->normalizeKey($color) ?? 'primary';
        return self::TONES[$normalizedColor] ?? self::TONES['primary'];
    }

    /**
     * @return array<string, string>
     */
    private function getQuickAccessPalette(): array
    {
        if ($this->quickAccessPalette !== null) {
            return $this->quickAccessPalette;
        }

        $palette = [];

        try {
            foreach ($this->assignQuickAccessColors(navigation('quick_access')) as $item) {
                $color = $this->normalizeKey($item['color'] ?? null);
                if ($color === null) {
                    continue;
                }

                $routePrefix = $this->extractRoutePrefix((string) ($item['route'] ?? ''));
                $moduleKey = $this->normalizeKey($item['module'] ?? null);

                if ($routePrefix !== null && !isset($palette[$routePrefix])) {
                    $palette[$routePrefix] = $color;
                }
                if ($moduleKey !== null && !isset($palette[$moduleKey])) {
                    $palette[$moduleKey] = $color;
                }
            }
        } catch (\Throwable) {
            $palette = [];
        }

        $this->quickAccessPalette = $palette;

        return $this->quickAccessPalette;
    }

    private function resolveQuickAccessItemColor(array $item, int &$cycleIndex): string
    {
        $routePrefix = $this->extractRoutePrefix((string) ($item['route'] ?? ''));
        if ($routePrefix !== null && isset(self::EXPLICIT_ROUTE_COLORS[$routePrefix])) {
            return self::EXPLICIT_ROUTE_COLORS[$routePrefix];
        }

        $color = self::FALLBACK_CYCLE[$cycleIndex % count(self::FALLBACK_CYCLE)];
        $cycleIndex++;

        return $color;
    }

    /**
     * @return list<string>
     */
    private function extractWidgetKeys(array $widget): array
    {
        $keys = [];

        $linkPrefix = $this->extractPathPrefix((string) (($widget['data']['link'] ?? '') ?: ''));
        if ($linkPrefix !== null) {
            $keys[] = $linkPrefix;
        }

        $idPrefix = $this->prefixBeforeDelimiter((string) ($widget['id'] ?? ''), '.');
        if ($idPrefix !== null) {
            $keys[] = $idPrefix;
        }

        $permissionPrefix = $this->prefixBeforeDelimiter((string) ($widget['permission'] ?? ''), '.');
        if ($permissionPrefix !== null) {
            $keys[] = $permissionPrefix;
        }

        return array_values(array_unique($keys));
    }

    private function extractRoutePrefix(string $routeName): ?string
    {
        return $this->prefixBeforeDelimiter($routeName, '.');
    }

    private function extractPathPrefix(string $url): ?string
    {
        if ($url === '' || $url === '#') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $trimmedPath = trim($path, '/');
        if ($trimmedPath === '') {
            return null;
        }

        $basePath = trim((string) config('app.base_path', ''), '/');
        $pathSegments = array_values(array_filter(explode('/', $trimmedPath), static fn (string $segment): bool => $segment !== ''));

        if ($basePath !== '') {
            $baseSegments = array_values(array_filter(explode('/', $basePath), static fn (string $segment): bool => $segment !== ''));
            if ($baseSegments !== [] && array_slice($pathSegments, 0, count($baseSegments)) === $baseSegments) {
                $pathSegments = array_slice($pathSegments, count($baseSegments));
            }
        }

        if ($pathSegments === []) {
            return null;
        }

        return $this->normalizeKey($pathSegments[0]);
    }

    private function prefixBeforeDelimiter(string $value, string $delimiter): ?string
    {
        if ($value === '') {
            return null;
        }

        $parts = explode($delimiter, $value, 2);
        return $this->normalizeKey($parts[0] ?? null);
    }

    private function normalizeKey(mixed $key): ?string
    {
        if (!is_string($key) && !is_numeric($key)) {
            return null;
        }

        $normalized = strtolower(trim((string) $key));
        return $normalized === '' ? null : $normalized;
    }
}
