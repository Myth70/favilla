<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use PDO;

class NotificationModuleCatalogService
{
    private PDO $pdo;
    private ?array $cachedModules = null;
    private ?array $cachedActiveMap = null;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    /**
     * Discover all modules that participate in notifications.
     *
     * Sources:
     *  1. module.json files declaring NotificationService in services
     *  2. notification_event_types table (modules already registered)
     *
     * Filters by active modules, sorted by label.
     *
     * @return array<int, array{name:string,slug:string,label:string,description:string,icon:string}>
     */
    public function getBaselineModules(): array
    {
        if ($this->cachedModules !== null) {
            return $this->cachedModules;
        }

        $registeredSlugs = $this->getRegisteredModuleSlugs();
        $jsonModules = $this->scanModuleJsonFiles($registeredSlugs);

        // Include any slugs from DB not found via module.json
        foreach ($registeredSlugs as $slug) {
            if (!isset($jsonModules[$slug])) {
                $jsonModules[$slug] = [
                    'name'        => ucfirst($slug),
                    'slug'        => $slug,
                    'label'       => ucfirst(str_replace('_', ' ', $slug)),
                    'description' => '',
                    'icon'        => 'fa-solid fa-bell',
                ];
            }
        }

        $result = [];
        foreach ($jsonModules as $module) {
            if ($this->isModuleActive($module['name'])) {
                $result[] = $module;
            }
        }

        usort($result, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        $this->cachedModules = $result;
        return $result;
    }

    public function isModuleActive(string $moduleName): bool
    {
        if ($this->cachedActiveMap === null) {
            $this->cachedActiveMap = $this->buildActiveMap();
        }

        return $this->cachedActiveMap[$moduleName] ?? false;
    }

    /**
     * Convert PascalCase module name to snake_case slug.
     *
     * Examples: HealthCheck → health_check, GDPR → gdpr, Teams → teams
     */
    public static function moduleNameToSlug(string $name): string
    {
        $normalized = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $name) ?? $name;
        $normalized = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $normalized) ?? $normalized;
        $normalized = strtolower(str_replace(['-', ' '], '_', $normalized));
        $trimmed = trim($normalized, '_');
        return $trimmed !== '' ? $trimmed : 'system';
    }

    /**
     * @return string[]
     */
    private function getRegisteredModuleSlugs(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT module_slug FROM notification_event_types ORDER BY module_slug ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param string[] $registeredSlugs
     * @return array<string, array{name:string,slug:string,label:string,description:string,icon:string}>
     */
    private function scanModuleJsonFiles(array $registeredSlugs): array
    {
        $registeredMap = array_flip($registeredSlugs);
        $modules = [];
        $jsonFiles = glob(BASE_PATH . '/app/Modules/*/module.json') ?: [];

        foreach ($jsonFiles as $jsonFile) {
            $dirName = basename(dirname($jsonFile));
            if ($dirName === '_Template') {
                continue;
            }

            $json = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($json)) {
                continue;
            }

            $slug = self::moduleNameToSlug($dirName);
            $declaresEvents = !empty($json['notification_events']) && is_array($json['notification_events']);
            $usesNotifications = in_array('NotificationService', $json['services'] ?? [], true)
                || isset($registeredMap[$slug])
                || $declaresEvents;

            if (!$usesNotifications) {
                continue;
            }

            $modules[$slug] = [
                'name'        => $dirName,
                'slug'        => $slug,
                'label'       => $json['name'] ?? $dirName,
                'description' => $json['description'] ?? '',
                'icon'        => self::resolveModuleIcon($json),
            ];
        }

        return $modules;
    }

    private static function resolveModuleIcon(array $json): string
    {
        $menuIcon = $json['menu'][0]['icon'] ?? null;
        if ($menuIcon !== null && $menuIcon !== '') {
            // Ensure full FA6 class: fa-comments → fa-solid fa-comments
            if (str_starts_with($menuIcon, 'fa-') && !str_contains($menuIcon, ' ')) {
                return 'fa-solid ' . $menuIcon;
            }
            return $menuIcon;
        }

        return 'fa-solid fa-bell';
    }

    private function buildActiveMap(): array
    {
        $map = [];

        foreach (glob(BASE_PATH . '/app/Modules/*/module.json') ?: [] as $jsonFile) {
            $name = basename(dirname($jsonFile));
            if ($name === '_Template') {
                continue;
            }
            $map[$name] = isModuleEnabled($name);
        }

        return $map;
    }
}
