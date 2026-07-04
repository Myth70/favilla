<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ModuleLoader;
use PDO;
use Throwable;

/**
 * Sincronizza i permessi dichiarati dai moduli verso la tabella `permissions`.
 *
 * Sorgenti (in ordine di priorita'):
 *   1. module.json -> chiave "permissions" (array di {slug, name})
 *   2. permissions.php (array di {slug, name})
 *
 * Idempotente e safe-by-default:
 *   - Insert portabile check-then-insert (MariaDB + SQLite)
 *   - UPDATE solo se il modulo dichiarante rinomina un permesso
 *   - Orphan (slug in DB non piu' dichiarato) RIPORTATI ma MAI cancellati
 *   - Collisioni (stesso slug da 2+ moduli) riportate, vince il primo dichiarante
 *
 * Usage:
 *   $report = app(PermissionSyncService::class)->sync();
 *   // $report => ['added' => [...], 'renamed' => [...], 'collisions' => [...], 'orphaned' => [...], 'existing' => int]
 */
class PermissionSyncService
{
    public function __construct(
        private PDO $pdo,
        private ModuleLoader $loader,
    ) {
    }

    /**
     * Esegue il sync completo leggendo i moduli dal ModuleLoader.
     *
     * @return array{added:array,renamed:array,collisions:array,orphaned:array,existing:int}
     */
    public function sync(): array
    {
        $declared = $this->collectDeclarations();
        return $this->syncFromDeclarations($declared);
    }

    /**
     * Esegue il sync partendo da una struttura gia' raccolta.
     * Utile nei test: accetta input deterministico senza toccare il filesystem.
     *
     * $declared ha forma:
     *   [
     *     'slug' => [
     *       'name'        => 'Visualizza X',
     *       'module'      => 'Nome',          // primo dichiarante (vince)
     *       'declared_by' => ['Nome', ...],   // tutti i dichiaranti (>1 = collisione)
     *     ], ...
     *   ]
     *
     * @param array<string,array{name:string,module:string,declared_by:array<int,string>}> $declared
     * @return array{added:array,renamed:array,collisions:array,orphaned:array,existing:int}
     */
    public function syncFromDeclarations(array $declared): array
    {
        $collisions = $this->detectCollisions($declared);
        $existing   = $this->readExistingFromDb();

        $added   = [];
        $renamed = [];

        $insertStmt = $this->pdo->prepare(
            'INSERT INTO permissions (name, slug, module) VALUES (?, ?, ?)'
        );
        $updateStmt = $this->pdo->prepare(
            'UPDATE permissions SET name = ?, module = ? WHERE slug = ?'
        );

        foreach ($declared as $slug => $decl) {
            if (!isset($existing[$slug])) {
                $insertStmt->execute([$decl['name'], $slug, $decl['module']]);
                $added[] = [
                    'slug'   => $slug,
                    'name'   => $decl['name'],
                    'module' => $decl['module'],
                ];
                continue;
            }

            $curr = $existing[$slug];
            if ($curr['name'] !== $decl['name'] || $curr['module'] !== $decl['module']) {
                $updateStmt->execute([$decl['name'], $decl['module'], $slug]);
                $renamed[] = [
                    'slug'       => $slug,
                    'old_name'   => $curr['name'],
                    'new_name'   => $decl['name'],
                    'old_module' => $curr['module'],
                    'new_module' => $decl['module'],
                ];
            }
        }

        $orphaned = [];
        foreach ($existing as $slug => $curr) {
            if (!isset($declared[$slug])) {
                $orphaned[] = [
                    'slug'   => $slug,
                    'name'   => $curr['name'],
                    'module' => $curr['module'],
                ];
            }
        }

        $this->markSyncTimestamp();

        return [
            'added'      => $added,
            'renamed'    => $renamed,
            'collisions' => $collisions,
            'orphaned'   => $orphaned,
            'existing'   => count($existing) - count($orphaned),
        ];
    }

    /**
     * Raccoglie le dichiarazioni di permessi da tutti i moduli abilitati.
     * Il primo modulo che dichiara uno slug ne diventa owner; i successivi
     * aggiungono solo la propria presenza in `declared_by`.
     *
     * @return array<string,array{name:string,module:string,declared_by:array<int,string>}>
     */
    public function collectDeclarations(): array
    {
        $result = [];

        foreach ($this->loader->getModules() as $module) {
            if (!($module['enabled'] ?? true)) {
                continue;
            }
            $name = $module['name'] ?? '';
            if ($name === '' || $name === '_Template') {
                continue;
            }

            foreach ($this->readModulePermissions($name) as $perm) {
                $slug = isset($perm['slug']) ? (string) $perm['slug'] : '';
                if ($slug === '') {
                    continue;
                }
                $pname = isset($perm['name']) && $perm['name'] !== ''
                    ? (string) $perm['name']
                    : $slug;

                if (isset($result[$slug])) {
                    if (!in_array($name, $result[$slug]['declared_by'], true)) {
                        $result[$slug]['declared_by'][] = $name;
                    }
                    continue;
                }

                $result[$slug] = [
                    'name'        => $pname,
                    'module'      => $name,
                    'declared_by' => [$name],
                ];
            }
        }

        return $result;
    }

    /**
     * Legge i permessi di un modulo. module.json prioritario, permissions.php fallback.
     */
    private function readModulePermissions(string $moduleName): array
    {
        $meta = $this->loader->readModuleJson($moduleName);
        if ($meta !== null && isset($meta['permissions']) && is_array($meta['permissions'])) {
            return $meta['permissions'];
        }
        return $this->loader->scanPermissions($moduleName);
    }

    private function detectCollisions(array $declared): array
    {
        $collisions = [];
        foreach ($declared as $slug => $decl) {
            if (count($decl['declared_by']) > 1) {
                $collisions[] = [
                    'slug'          => $slug,
                    'winner_module' => $decl['module'],
                    'declared_by'   => $decl['declared_by'],
                ];
            }
        }
        return $collisions;
    }

    /**
     * @return array<string,array{id:int,name:string,module:string}>
     */
    private function readExistingFromDb(): array
    {
        $result = [];
        $stmt = $this->pdo->query('SELECT id, name, slug, module FROM permissions');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['slug']] = [
                'id'     => (int) $row['id'],
                'name'   => (string) $row['name'],
                'module' => (string) ($row['module'] ?? ''),
            ];
        }
        return $result;
    }

    /**
     * Registra il timestamp dell'ultimo sync in app_settings.
     * Usato per lo staleness-check delle sessioni (refresh permessi senza logout).
     * Silent-fail: se la tabella non esiste (es. test isolato) non blocca il sync.
     */
    private function markSyncTimestamp(): void
    {
        $now = date('Y-m-d H:i:s');
        try {
            // Portabile: DELETE + INSERT invece di ON DUPLICATE KEY UPDATE (non SQLite)
            $stmt = $this->pdo->prepare('DELETE FROM app_settings WHERE `key` = ?');
            $stmt->execute(['permissions_last_sync_at']);

            $stmt = $this->pdo->prepare(
                'INSERT INTO app_settings (`key`, `value`, `type`, `group`, `label`)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                'permissions_last_sync_at',
                $now,
                'string',
                'internal',
                'Ultimo sync permessi dai moduli',
            ]);
        } catch (Throwable) {
            // app_settings non disponibile (es. test isolato): silent
        }
    }
}
