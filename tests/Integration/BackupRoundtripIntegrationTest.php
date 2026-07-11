<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Container;
use App\Modules\Backup\Services\BackupFilesService;
use App\Modules\Backup\Services\BackupService;

/**
 * Roundtrip completo backup→ripristino su MariaDB reale (roadmap A1):
 * dump multi-tabella via SHOW CREATE TABLE (impossibile su SQLite), manifest
 * v2 con i file utente, ripristino di dati E file da un unico archivio.
 *
 * useTransaction=false: il ripristino esegue DDL (DROP/CREATE TABLE), che in
 * MariaDB committa implicitamente; il DB usa-e-getta viene comunque ricreato
 * da setUpBeforeClass alla prossima run.
 */
class BackupRoundtripIntegrationTest extends DatabaseIntegrationTestCase
{
    protected bool $useTransaction = false;

    private string $tmpAbs = '';
    private string $tmpRel = '';
    /** @var string[] */
    private array $createdBackups = [];
    /** @var array<string, string|false> */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Il backup legge la policy da env: neutralizza la configurazione
        // locale (chiave di cifratura, esclusioni) e disinnesca la rotazione,
        // che altrimenti potrebbe eliminare backup reali della macchina dev.
        $this->overrideEnv('BACKUP_ENCRYPTION_KEY', '');
        $this->overrideEnv('BACKUP_EXCLUDE_TABLES', '');
        $this->overrideEnv('BACKUP_INCLUDE_FILES', 'true');
        $this->overrideEnv('BACKUP_MAX_COUNT', '9999');
        $this->overrideEnv('BACKUP_FAIL_ON_MISSING_MODULE_DB', 'false');

        $name = 'favilla_bkproundtrip_' . uniqid();
        $this->tmpAbs = BASE_PATH . '/storage/tmp/' . $name;
        $this->tmpRel = 'storage/tmp/' . $name;
        mkdir($this->tmpAbs . '/uploads/nested', 0755, true);

        Container::getInstance()->instance(BackupFilesService::class, new BackupFilesService([
            ['key' => 'it_uploads', 'base' => $this->tmpRel . '/uploads'],
        ]));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdBackups as $filename) {
            @unlink(BASE_PATH . '/storage/backups/' . $filename);
        }

        if ($this->tmpAbs !== '' && is_dir($this->tmpAbs)) {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpAbs, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($this->tmpAbs);
        }

        foreach ($this->savedEnv as $key => $value) {
            if ($value === false) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }

        parent::tearDown();
    }

    public function testBackupAndRestoreRoundTripWithFiles(): void
    {
        // --- Fixture: una riga DB + due file utente -----------------------
        $rowId = $this->insertRow('backup_history', [
            'filename'    => 'backup_20260101_000000.zip',
            'format'      => 'zip',
            'size_bytes'  => 123,
            'table_count' => 1,
        ]);

        file_put_contents($this->tmpAbs . '/uploads/report.txt', 'contenuto originale');
        file_put_contents($this->tmpAbs . '/uploads/nested/logo.png', 'PNGDATA');

        // --- Backup -------------------------------------------------------
        $service = new BackupService();
        $result  = $service->createBackup();
        $this->createdBackups[] = $result['filename'];

        $this->assertTrue($service->isZipBackup($result['filename']));
        $this->assertSame(2, $result['file_count']);
        $this->assertGreaterThan(0, $result['table_count']);

        // Il manifest nell'archivio è v2 e censisce i file per radice.
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open(BASE_PATH . '/storage/backups/' . $result['filename']));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertSame(2, $manifest['manifest_version']);
        $this->assertSame('it_uploads', $manifest['files'][0]['key']);
        $this->assertSame(2, $manifest['files'][0]['file_count']);

        // --- Deriva post-backup: dati e file cambiano ----------------------
        self::$pdo->exec("UPDATE backup_history SET size_bytes = 999999 WHERE id = {$rowId}");
        file_put_contents($this->tmpAbs . '/uploads/report.txt', 'MODIFICATO dopo il backup');
        unlink($this->tmpAbs . '/uploads/nested/logo.png');
        file_put_contents($this->tmpAbs . '/uploads/extra.txt', 'caricato dopo il backup');

        // --- Ripristino (senza safety backup: è il backup stesso in test) --
        $restore = $service->restoreBackup($result['filename'], null, false);

        $this->assertContains('main', $restore['databases_restored']);
        $this->assertGreaterThan(0, $restore['statements_executed']);
        $this->assertSame(2, $restore['files_restored']);
        $this->assertSame(0, $restore['files_skipped']);

        // I dati DB tornano allo stato del backup.
        $size = self::$pdo->query("SELECT size_bytes FROM backup_history WHERE id = {$rowId}")->fetchColumn();
        $this->assertSame(123, (int) $size);

        // I file tornano allo stato del backup; quelli caricati dopo restano.
        $this->assertSame('contenuto originale', file_get_contents($this->tmpAbs . '/uploads/report.txt'));
        $this->assertSame('PNGDATA', file_get_contents($this->tmpAbs . '/uploads/nested/logo.png'));
        $this->assertFileExists($this->tmpAbs . '/uploads/extra.txt');
    }

    public function testBackupWithFilesDisabledProducesDbOnlyManifest(): void
    {
        $this->overrideEnv('BACKUP_INCLUDE_FILES', 'false');
        file_put_contents($this->tmpAbs . '/uploads/ignored.txt', 'non deve entrare nel backup');

        $service = new BackupService();
        $result  = $service->createBackup();
        $this->createdBackups[] = $result['filename'];

        $this->assertSame(0, $result['file_count']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open(BASE_PATH . '/storage/backups/' . $result['filename']));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $hasFileEntries = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'files/')) {
                $hasFileEntries = true;
            }
        }
        $zip->close();

        $this->assertSame([], $manifest['files']);
        $this->assertFalse($hasFileEntries);
    }

    private function overrideEnv(string $key, string $value): void
    {
        if (!array_key_exists($key, $this->savedEnv)) {
            $this->savedEnv[$key] = getenv($key);
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}
