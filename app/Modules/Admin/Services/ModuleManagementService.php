<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Core\ModuleLoader;
use App\Modules\Admin\Repositories\ModuleStateRepository;
use App\Modules\Admin\Repositories\PermissionRepository;
use App\Services\ModuleDatabaseResolver;
use PDO;

class ModuleManagementService
{
    private ModuleLoader $loader;
    private ModuleStateRepository $moduleStateRepo;
    private PermissionRepository $permissionRepo;
    private PDO $pdo;

    public function __construct()
    {
        $this->loader = app(ModuleLoader::class);
        $this->moduleStateRepo = app(ModuleStateRepository::class);
        $this->permissionRepo = app(PermissionRepository::class);
        $this->pdo = app(PDO::class);
    }

    public function getModulesWithStatus(): array
    {
        $modules = $this->loader->getAllModulesWithStatus($this->pdo);

        // Enrich with module_databases mapping (no-op for shared modules).
        try {
            $resolver = app(ModuleDatabaseResolver::class);
        } catch (\Throwable $e) {
            $resolver = null;
        }

        foreach ($modules as $idx => $mod) {
            if ($resolver !== null) {
                $modules[$idx]['db_mapping'] = $resolver->getMapping($mod['name']);
            }

            // Pick the first admin_panel link as the canonical "admin page" of the module.
            $adminLink = $this->resolveAdminLink($mod['name']);
            $modules[$idx]['admin_route']      = $adminLink['route'] ?? null;
            $modules[$idx]['admin_permission'] = $adminLink['permission'] ?? null;
        }

        return $modules;
    }

    /**
     * Read module.json and return the first link declared under admin_panel.groups[].links[],
     * which is the canonical entry point for the module's admin area.
     */
    private function resolveAdminLink(string $moduleName): ?array
    {
        $meta = $this->loader->readModuleJson($moduleName);
        if (!is_array($meta)) {
            return null;
        }
        $groups = $meta['admin_panel']['groups'] ?? [];
        foreach ($groups as $group) {
            foreach (($group['links'] ?? []) as $link) {
                if (!empty($link['route'])) {
                    return [
                        'route'      => (string) $link['route'],
                        'permission' => isset($link['permission']) ? (string) $link['permission'] : null,
                    ];
                }
            }
        }
        return null;
    }

    public function getCoreModules(): array
    {
        return array_values(array_filter(
            $this->loader->getModules(),
            fn ($m) => ($m['core'] ?? false) === true
        ));
    }

    public function findStateByName(string $name): ?array
    {
        return $this->moduleStateRepo->findByName($name);
    }

    public function upsertState(string $name, int $enabled, int $testing, ?int $updatedBy): void
    {
        $this->moduleStateRepo->upsert($name, $enabled, $testing, $updatedBy);

        // Seeding/disattivazione dei job scheduler dichiarati dal modulo (best-effort).
        try {
            app(\App\Modules\Scheduler\Services\SchedulerService::class)
                ->syncModuleJobs($name, $enabled === 1);
        } catch (\Throwable $e) {
            // Lo scheduler potrebbe non essere disponibile: non bloccare il cambio stato.
        }
    }

    public function scanPermissions(string $name): array
    {
        return $this->loader->scanPermissions($name);
    }

    public function importPermissions(string $moduleName, array $permissions): int
    {
        return $this->permissionRepo->importFromModule($moduleName, $permissions);
    }
}
