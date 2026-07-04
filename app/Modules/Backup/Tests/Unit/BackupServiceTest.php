<?php

namespace App\Modules\Backup\Tests\Unit;

use App\Modules\Backup\Services\BackupEncryptionService;
use App\Modules\Backup\Services\BackupService;
use PHPUnit\Framework\TestCase;

/**
 * Test per BackupService — usa SQLite in-memory per dump data e file temporanei.
 */
class BackupServiceTest extends TestCase
{
    private BackupService $service;
    private \PDO          $pdo;
    private string        $tmpDir;
    private string        $hugeFilePath;
    private string|false  $originalEncryptionKey;
    private string|false  $originalAllowLarge;

    protected function setUp(): void
    {
        $this->service = new BackupService();
        $this->originalEncryptionKey = getenv('BACKUP_ENCRYPTION_KEY');
        $this->originalAllowLarge = getenv('BACKUP_ALLOW_UNENCRYPTED_LARGE');

        // PDO SQLite in-memory per test dump
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // Crea tabella di test
        $this->pdo->exec('CREATE TABLE test_items (
            id   INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            val  TEXT NULL
        )');
        $this->pdo->exec("INSERT INTO test_items (id, name, val) VALUES (1, 'Alpha', 'X'), (2, 'Beta', NULL)");

        // Directory temporanea per test file dentro un path consentito da open_basedir
        $baseTmp = defined('BASE_PATH') ? BASE_PATH . '/storage/tmp' : __DIR__ . '/../../../../../storage/tmp';
        if (!is_dir($baseTmp)) {
            mkdir($baseTmp, 0755, true);
        }
        $this->tmpDir = $baseTmp . '/favilla_backup_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->hugeFilePath = $this->tmpDir . '/huge_backup.sql.gz';
        $fp = fopen($this->hugeFilePath, 'wb');
        if (is_resource($fp)) {
            // Crea un file "grande" senza allocazione reale completa su disco.
            ftruncate($fp, (200 * 1024 * 1024) + 1);
            fclose($fp);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);

        $this->restoreEnvVar('BACKUP_ENCRYPTION_KEY', $this->originalEncryptionKey);
        $this->restoreEnvVar('BACKUP_ALLOW_UNENCRYPTED_LARGE', $this->originalAllowLarge);
    }

    // ------------------------------------------------------------------
    // validateFilename
    // ------------------------------------------------------------------

    public function testValidFilenamePattern(): void
    {
        $this->assertTrue($this->service->validateFilename('backup_20250115_143022.sql.gz'));
        $this->assertTrue($this->service->validateFilename('backup_20991231_235959.sql.gz'));
    }

    public function testInvalidFilenameRejectedPathTraversal(): void
    {
        $this->assertFalse($this->service->validateFilename('../etc/passwd'));
        $this->assertFalse($this->service->validateFilename('backup_20250115_143022.sql'));
        $this->assertFalse($this->service->validateFilename('backup_20250115.sql.gz'));
        $this->assertFalse($this->service->validateFilename(''));
        $this->assertFalse($this->service->validateFilename('backup_2025a115_143022.sql.gz'));
    }

    // ------------------------------------------------------------------
    // dumpTableDataToGz (streaming)
    // ------------------------------------------------------------------

    public function testDumpTableDataToGzGeneratesInsert(): void
    {
        $path = $this->tmpDir . '/test_dump.sql.gz';
        $gz   = $this->openGzipForWrite($path);
        $this->service->dumpTableDataToGz($this->pdo, 'test_items', $gz);
        gzclose($gz);

        $content = gzdecode(file_get_contents($path));
        $this->assertStringContainsString('INSERT INTO `test_items`', $content);
        $this->assertStringContainsString("'Alpha'", $content);
        $this->assertStringContainsString("'Beta'", $content);
    }

    public function testDumpTableDataToGzHandlesNull(): void
    {
        $path = $this->tmpDir . '/test_null.sql.gz';
        $gz   = $this->openGzipForWrite($path);
        $this->service->dumpTableDataToGz($this->pdo, 'test_items', $gz);
        gzclose($gz);

        $content = gzdecode(file_get_contents($path));
        $this->assertStringContainsString('NULL', $content);
    }

    public function testDumpTableDataToGzEmptyTable(): void
    {
        $this->pdo->exec('CREATE TABLE empty_table (id INTEGER PRIMARY KEY)');

        $path = $this->tmpDir . '/test_empty.sql.gz';
        $gz   = $this->openGzipForWrite($path);
        $this->service->dumpTableDataToGz($this->pdo, 'empty_table', $gz);
        gzclose($gz);

        $content = gzdecode(file_get_contents($path));
        // Tabella vuota → nessun INSERT
        $this->assertStringNotContainsString('INSERT', $content ?: '');
    }

    public function testDumpTableDataToGzChunks(): void
    {
        // Inserisci >1000 righe per testare il chunking
        $this->pdo->exec('CREATE TABLE big_table (id INTEGER PRIMARY KEY, val TEXT)');
        $stmt = $this->pdo->prepare('INSERT INTO big_table (val) VALUES (?)');
        for ($i = 0; $i < 1500; $i++) {
            $stmt->execute(["row_{$i}"]);
        }

        $path = $this->tmpDir . '/test_chunks.sql.gz';
        $gz   = $this->openGzipForWrite($path);
        $this->service->dumpTableDataToGz($this->pdo, 'big_table', $gz);
        gzclose($gz);

        $content = gzdecode(file_get_contents($path));
        // Deve avere 2 INSERT (1000 + 500)
        $this->assertSame(2, substr_count($content, 'INSERT INTO `big_table`'));
    }

    // ------------------------------------------------------------------
    // Formato filename
    // ------------------------------------------------------------------

    public function testFilenameFormatMatchesPattern(): void
    {
        $filename = 'backup_' . date('Ymd_His') . '.sql.gz';
        $this->assertTrue($this->service->validateFilename($filename));
    }

    // ------------------------------------------------------------------
    // getBackupPath / deleteBackup
    // ------------------------------------------------------------------

    public function testGetBackupPathReturnsNullForMissing(): void
    {
        $result = $this->service->getBackupPath('backup_20250115_143022.sql.gz');
        $this->assertNull($result);
    }

    public function testDeleteBackupReturnsFalseForMissing(): void
    {
        $deleted = $this->service->deleteBackup('backup_20250115_143022.sql.gz');
        $this->assertFalse($deleted);
    }

    // ------------------------------------------------------------------
    // verifyGzipIntegrity
    // ------------------------------------------------------------------

    public function testVerifyGzipIntegrityValid(): void
    {
        if (!function_exists('gzencode') || !function_exists('gzdecode')) {
            $this->markTestSkipped('Supporto gzip non disponibile nel runtime di test.');
        }

        $path = $this->tmpDir . '/valid.sql.gz';
        file_put_contents($path, gzencode('-- test SQL', 6));

        if (gzdecode((string) file_get_contents($path)) === false) {
            $this->markTestSkipped('Il runtime di test non riesce a decodificare payload gzip validi.');
        }

        $this->assertTrue($this->service->verifyGzipIntegrity($path));
    }

    public function testVerifyGzipIntegrityCorrupt(): void
    {
        $path = $this->tmpDir . '/corrupt.sql.gz';
        file_put_contents($path, 'this is not gzip data');

        $this->assertFalse($this->service->verifyGzipIntegrity($path));
    }

    public function testVerifyGzipIntegrityMissing(): void
    {
        $this->assertFalse($this->service->verifyGzipIntegrity($this->tmpDir . '/nonexistent.gz'));
    }

    // ------------------------------------------------------------------
    // getExcludedTables
    // ------------------------------------------------------------------

    public function testGetExcludedTablesEmpty(): void
    {
        // Senza BACKUP_EXCLUDE_TABLES in env, ritorna array vuoto
        $tables = $this->service->getExcludedTables();
        $this->assertIsArray($tables);
    }

    // ------------------------------------------------------------------
    // isBackupRunning
    // ------------------------------------------------------------------

    public function testIsBackupRunningNoLock(): void
    {
        // Senza lock file attivo, deve ritornare false
        $this->assertFalse($this->service->isBackupRunning());
    }

    // ------------------------------------------------------------------
    // dumpTableStructureToGz — SQLite non supporta SHOW CREATE TABLE
    // ------------------------------------------------------------------

    public function testDumpTableStructureToGzThrowsOnSqlite(): void
    {
        $this->expectException(\Exception::class);

        $path = $this->tmpDir . '/test_struct.sql.gz';
        $gz   = $this->openGzipForWrite($path);
        $this->service->dumpTableStructureToGz($this->pdo, 'test_items', $gz);
        gzclose($gz);
    }

    // ------------------------------------------------------------------
    // getTables — SHOW TABLES non supportato da SQLite
    // ------------------------------------------------------------------

    public function testGetTablesMethodExists(): void
    {
        $this->assertTrue(method_exists($this->service, 'getTables'));
    }

    public function testEncryptFileFailsSafeForLargeBackupByDefault(): void
    {
        $_ENV['BACKUP_ENCRYPTION_KEY'] = str_repeat('k', 64);
        $_ENV['BACKUP_ALLOW_UNENCRYPTED_LARGE'] = 'false';
        putenv('BACKUP_ENCRYPTION_KEY=' . $_ENV['BACKUP_ENCRYPTION_KEY']);
        putenv('BACKUP_ALLOW_UNENCRYPTED_LARGE=false');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Backup troppo grande per la cifratura in-memory');

        (new BackupEncryptionService())->encryptInPlace($this->hugeFilePath);
    }

    public function testEncryptFileAllowsLargeBackupOnlyWithExplicitOverride(): void
    {
        $_ENV['BACKUP_ENCRYPTION_KEY'] = str_repeat('k', 64);
        $_ENV['BACKUP_ALLOW_UNENCRYPTED_LARGE'] = 'true';
        putenv('BACKUP_ENCRYPTION_KEY=' . $_ENV['BACKUP_ENCRYPTION_KEY']);
        putenv('BACKUP_ALLOW_UNENCRYPTED_LARGE=true');

        $initialSize = filesize($this->hugeFilePath);

        (new BackupEncryptionService())->encryptInPlace($this->hugeFilePath);

        $this->assertSame($initialSize, filesize($this->hugeFilePath));

        unset($_ENV['BACKUP_ALLOW_UNENCRYPTED_LARGE']);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $_ENV['BACKUP_ENCRYPTION_KEY'] = str_repeat('k', 64);
        putenv('BACKUP_ENCRYPTION_KEY=' . $_ENV['BACKUP_ENCRYPTION_KEY']);

        $path = $this->tmpDir . '/roundtrip.sql.gz';
        $original = gzencode('SELECT 1; -- payload di prova', 6);
        file_put_contents($path, $original);

        $enc = new BackupEncryptionService();
        $enc->encryptInPlace($path);

        // On disk it must be the GCM format (PMT2 header), not the gzip magic.
        $onDisk = (string) file_get_contents($path);
        $this->assertStringStartsWith('PMT2', $onDisk);

        // And it must decrypt back to the exact original bytes.
        $this->assertSame($original, $enc->readDecrypted($path));

        @unlink($path);
        unset($_ENV['BACKUP_ENCRYPTION_KEY']);
    }

    // ------------------------------------------------------------------
    // splitSqlStatements — non deve spezzare dentro caratteri multibyte
    // (regressione: \R matcha il byte 0x85/NEL, 4° byte di emoji come 📅)
    // ------------------------------------------------------------------

    public function testSplitSqlStatementsPreservesMultibyteWithNelByte(): void
    {
        // 📅 = F0 9F 93 85: il 4° byte 0x85 è NEL; con \R verrebbe trattato come a-capo.
        $emoji = "\xF0\x9F\x93\x85";
        $sql = "INSERT INTO t (body) VALUES ('start {$emoji} end');\n"
             . "INSERT INTO t (body) VALUES ('second');\n";

        $ref    = new \ReflectionClass(BackupService::class);
        $method = $ref->getMethod('splitSqlStatements');
        $method->setAccessible(true);
        /** @var string[] $statements */
        $statements = $method->invoke($this->service, $sql);

        $this->assertCount(2, $statements, 'deve produrre 2 statement, non spezzare sull\'emoji');
        $this->assertStringContainsString($emoji, $statements[0], 'la sequenza emoji 4-byte deve restare intatta');
        // La sequenza NON deve risultare troncata a 3 byte seguiti da spazio.
        $this->assertStringNotContainsString("\xF0\x9F\x93\x20", $statements[0]);
    }

    /**
     * @return resource
     */
    private function openGzipForWrite(string $path)
    {
        if (!function_exists('gzopen')) {
            $this->markTestSkipped('Supporto gzip non disponibile nel runtime di test.');
        }

        $gz = @gzopen($path, 'wb6');
        if (!is_resource($gz)) {
            $this->markTestSkipped('Impossibile aprire uno stream gzip scrivibile nel runtime di test corrente.');
        }

        return $gz;
    }

    private function restoreEnvVar(string $name, string|false $value): void
    {
        if ($value === false) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
            return;
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}
