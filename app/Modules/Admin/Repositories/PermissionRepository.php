<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use App\Repositories\BaseRepository;

class PermissionRepository extends BaseRepository
{
    protected string $table = 'permissions';
    protected array $fillable = ['name', 'slug', 'module'];

    /**
     * Importa le permission dichiarate da un modulo (INSERT IGNORE per idempotenza).
     * Restituisce il numero di nuovi record inseriti.
     */
    public function importFromModule(string $moduleName, array $permissions): int
    {
        $imported = 0;
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO permissions (name, slug, module) VALUES (?, ?, ?)'
        );
        foreach ($permissions as $perm) {
            $stmt->execute([$perm['name'], $perm['slug'], $moduleName]);
            $imported += $stmt->rowCount();
        }
        return $imported;
    }

    /**
     * Restituisce tutti i permessi raggruppati per modulo,
     * escludendo i moduli core non gestibili (senza permissions_manageable).
     */
    public function getAllGroupedExcludingUnmanageable(): array
    {
        $all = $this->pdo->query(
            'SELECT id, slug, name, module FROM permissions ORDER BY module, name'
        )->fetchAll();

        $coreModules = array_map('strtolower', array_column(
            array_filter(
                config('modules', []),
                fn ($m) => !empty($m['core']) && empty($m['permissions_manageable'])
            ),
            'name'
        ));

        $grouped = [];
        foreach ($all as $perm) {
            $mod = $perm['module'] ?? 'Altro';
            if (!in_array(strtolower($mod), $coreModules, true)) {
                $grouped[$mod][] = $perm;
            }
        }
        return $grouped;
    }
}
