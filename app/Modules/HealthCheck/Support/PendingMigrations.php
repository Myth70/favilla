<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Support;

/**
 * Conteggio delle migrazioni ancora da eseguire.
 *
 * Confronta i file di migrazione su disco (core + moduli) con l'elenco di quelle
 * già registrate nella tabella `migrations`, tenendo conto delle migrazioni di
 * modulo che sono state consolidate in una migrazione core (mappa storica).
 *
 * Estratto dalla god class HealthCheckService per essere testabile senza DB:
 * le liste delle migrazioni eseguite vengono iniettate, il filesystem viene letto qui.
 */
final class PendingMigrations
{
    /**
     * Migrazioni di modulo risolte da una migrazione core consolidata.
     * [Modulo => [file_modulo => file_core_che_lo_risolve]].
     */
    private const CONSOLIDATED_MODULE_MIGRATIONS = [
        'Backup' => [
            '001_backup.sql' => '011_backup_gdpr.sql',
        ],
        'Files' => [
            '002_files_folders.sql' => '014_database_structure_consolidation.sql',
            '003_add_files_access_permission.sql' => '014_database_structure_consolidation.sql',
        ],
        'Tasks' => [
            '001_attivita.sql' => '015_modules_application_tables.sql',
        ],
        'Calendar' => [
            '001_calendario.sql' => '015_modules_application_tables.sql',
        ],
        'Contacts' => [
            '001_contatti.sql' => '015_modules_application_tables.sql',
        ],
        'HealthCheck' => [
            '001_healthcheck.sql' => '015_modules_application_tables.sql',
        ],
        'Reports' => [
            '001_reports.sql' => '015_modules_application_tables.sql',
        ],
    ];

    /**
     * Conta le migrazioni pendenti.
     *
     * @param string[]               $executedCore    Filename delle migrazioni core eseguite.
     * @param array<string,string[]> $executedModules Filename eseguiti per modulo.
     */
    public static function count(array $executedCore, array $executedModules): int
    {
        $executedCoreMap = array_fill_keys(array_map('strval', $executedCore), true);

        $executedModuleMap = [];
        foreach ($executedModules as $moduleName => $filenames) {
            foreach ($filenames as $filename) {
                $executedModuleMap[$moduleName . '::' . $filename] = true;
            }
        }

        $pending = 0;

        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
            $filename = basename($file);
            if (!isset($executedCoreMap[$filename])) {
                $pending++;
            }
        }

        foreach (glob(BASE_PATH . '/app/Modules/*/migrations/*.sql') ?: [] as $file) {
            $filename   = basename($file);
            $moduleName = basename(dirname(dirname($file)));

            if ($moduleName === '_Template') {
                continue;
            }

            if (self::isConsolidatedMigrationResolved($moduleName, $filename, $executedCoreMap)) {
                continue;
            }

            if (!isset($executedModuleMap[$moduleName . '::' . $filename])) {
                $pending++;
            }
        }

        return $pending;
    }

    /**
     * @param array<string,bool> $executedCoreMap
     */
    private static function isConsolidatedMigrationResolved(string $moduleName, string $filename, array $executedCoreMap): bool
    {
        $coreAlias = self::CONSOLIDATED_MODULE_MIGRATIONS[$moduleName][$filename] ?? null;
        if ($coreAlias === null) {
            return false;
        }

        return isset($executedCoreMap[$coreAlias]);
    }
}
