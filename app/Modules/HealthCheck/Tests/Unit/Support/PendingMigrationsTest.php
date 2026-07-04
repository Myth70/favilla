<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Tests\Unit\Support;

use App\Modules\HealthCheck\Support\PendingMigrations;
use PHPUnit\Framework\TestCase;

/**
 * Verifica il conteggio delle migrazioni pendenti, comprese le migrazioni di
 * modulo consolidate in una migrazione core. Legge i file reali su disco.
 */
class PendingMigrationsTest extends TestCase
{
    public function testIgnoresConsolidatedModuleFilesWhenCoreAliasExists(): void
    {
        $executedCore = [
            '011_backup_gdpr.sql',
            '014_database_structure_consolidation.sql',
            '015_modules_application_tables.sql',
        ];

        $pending = PendingMigrations::count($executedCore, []);

        $allFiles = count(glob(BASE_PATH . '/database/migrations/*.sql') ?: [])
            + count(glob(BASE_PATH . '/app/Modules/*/migrations/*.sql') ?: []);

        $this->assertLessThan($allFiles, $pending);
    }

    public function testCountsZeroWhenEverythingExecuted(): void
    {
        $executedCore = array_map(
            static fn (string $file): string => basename($file),
            glob(BASE_PATH . '/database/migrations/*.sql') ?: []
        );

        $executedModules = [];
        foreach (glob(BASE_PATH . '/app/Modules/*/migrations/*.sql') ?: [] as $file) {
            $moduleName = basename(dirname(dirname($file)));
            if ($moduleName === '_Template') {
                continue;
            }
            $executedModules[$moduleName][] = basename($file);
        }

        $pending = PendingMigrations::count($executedCore, $executedModules);

        $this->assertSame(0, $pending);
    }

    public function testCountsMissingCoreMigration(): void
    {
        // Nessuna migrazione registrata → tutte pendenti (almeno quelle core presenti).
        $coreOnDisk = count(glob(BASE_PATH . '/database/migrations/*.sql') ?: []);

        $pending = PendingMigrations::count([], []);

        $this->assertGreaterThanOrEqual($coreOnDisk, $pending);
    }
}
