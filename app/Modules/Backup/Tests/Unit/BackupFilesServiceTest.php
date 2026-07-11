<?php

declare(strict_types=1);

namespace App\Modules\Backup\Tests\Unit;

use App\Modules\Backup\Services\BackupFilesService;
use PHPUnit\Framework\TestCase;

/**
 * Test per BackupFilesService — enumerazione, ripristino da zip e validazione
 * anti-traversal. Usa radici temporanee sotto storage/tmp (dentro open_basedir),
 * iniettate via costruttore con base RELATIVA a BASE_PATH come da contratto.
 */
class BackupFilesServiceTest extends TestCase
{
    private string $tmpAbs;
    private string $tmpRel;
    private string|false $originalIncludeFiles;

    protected function setUp(): void
    {
        $this->originalIncludeFiles = getenv('BACKUP_INCLUDE_FILES');

        $baseTmp = BASE_PATH . '/storage/tmp';
        if (!is_dir($baseTmp)) {
            mkdir($baseTmp, 0755, true);
        }
        $name = 'favilla_bkpfiles_test_' . uniqid();
        $this->tmpAbs = $baseTmp . '/' . $name;
        $this->tmpRel = 'storage/tmp/' . $name;
        mkdir($this->tmpAbs, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmpAbs);

        if ($this->originalIncludeFiles === false) {
            unset($_ENV['BACKUP_INCLUDE_FILES'], $_SERVER['BACKUP_INCLUDE_FILES']);
            putenv('BACKUP_INCLUDE_FILES');
        } else {
            $_ENV['BACKUP_INCLUDE_FILES'] = $this->originalIncludeFiles;
            putenv('BACKUP_INCLUDE_FILES=' . $this->originalIncludeFiles);
        }
    }

    // ------------------------------------------------------------------
    // isEnabled
    // ------------------------------------------------------------------

    public function testEnabledByDefault(): void
    {
        unset($_ENV['BACKUP_INCLUDE_FILES']);
        putenv('BACKUP_INCLUDE_FILES');

        $this->assertTrue((new BackupFilesService())->isEnabled());
    }

    public function testDisabledViaEnv(): void
    {
        $_ENV['BACKUP_INCLUDE_FILES'] = 'false';
        putenv('BACKUP_INCLUDE_FILES=false');

        $this->assertFalse((new BackupFilesService())->isEnabled());
    }

    // ------------------------------------------------------------------
    // enumerate
    // ------------------------------------------------------------------

    public function testEnumerateWalksRootsRecursively(): void
    {
        $root = $this->makeRoot('uploads');
        file_put_contents($root . '/a.txt', 'alpha');
        mkdir($root . '/sub/deep', 0755, true);
        file_put_contents($root . '/sub/deep/b.bin', str_repeat('x', 100));

        $service = new BackupFilesService([
            ['key' => 'test_uploads', 'base' => $this->tmpRel . '/uploads'],
        ]);
        $result = $service->enumerate();

        $this->assertSame([], $result['warnings']);
        $this->assertCount(1, $result['roots']);
        $this->assertSame('test_uploads', $result['roots'][0]['key']);
        $this->assertSame(2, $result['roots'][0]['file_count']);
        $this->assertSame(105, $result['roots'][0]['total_size']);

        $entries = array_column($result['files'], 'entry');
        sort($entries);
        $this->assertSame([
            'files/test_uploads/a.txt',
            'files/test_uploads/sub/deep/b.bin',
        ], $entries);
    }

    public function testEnumerateMissingRootYieldsZeroFiles(): void
    {
        $service = new BackupFilesService([
            ['key' => 'ghost', 'base' => $this->tmpRel . '/does_not_exist'],
        ]);
        $result = $service->enumerate();

        $this->assertSame(0, $result['roots'][0]['file_count']);
        $this->assertSame([], $result['files']);
        $this->assertSame([], $result['warnings']);
    }

    // ------------------------------------------------------------------
    // restoreFromZip
    // ------------------------------------------------------------------

    public function testRestoreFromZipRoundTrip(): void
    {
        // Sorgente con 2 file, impacchettata come farebbe il backup.
        $src = $this->makeRoot('src');
        file_put_contents($src . '/doc.txt', 'contenuto originale');
        mkdir($src . '/nested', 0755, true);
        file_put_contents($src . '/nested/photo.jpg', 'JPEGDATA');

        $zipPath = $this->tmpAbs . '/set.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFile($src . '/doc.txt', 'files/test_root/doc.txt');
        $zip->addFile($src . '/nested/photo.jpg', 'files/test_root/nested/photo.jpg');
        $zip->addFromString('manifest.json', '{}');
        $zip->close();

        // Destinazione: file uno sovrascritto, uno mancante, uno extra.
        $dest = $this->makeRoot('dest');
        file_put_contents($dest . '/doc.txt', 'contenuto MODIFICATO dopo il backup');
        file_put_contents($dest . '/extra.txt', 'caricato dopo il backup');

        $service = new BackupFilesService([
            ['key' => 'test_root', 'base' => $this->tmpRel . '/dest'],
        ]);

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $result = $service->restoreFromZip($zip);
        $zip->close();

        $this->assertSame(2, $result['restored']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([], $result['warnings']);

        // Sovrascritto, ricreato, e l'extra NON viene eliminato.
        $this->assertSame('contenuto originale', file_get_contents($dest . '/doc.txt'));
        $this->assertSame('JPEGDATA', file_get_contents($dest . '/nested/photo.jpg'));
        $this->assertFileExists($dest . '/extra.txt');
    }

    public function testRestoreFromZipRejectsTraversalAndUnknownRoots(): void
    {
        $zipPath = $this->tmpAbs . '/evil.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('files/test_root/../evil.txt', 'traversal');
        $zip->addFromString('files/test_root/ok/../../evil2.txt', 'traversal');
        $zip->addFromString('files/unknown_root/file.txt', 'radice non registrata');
        $zip->addFromString('files/test_root/c:drive.txt', 'windows drive');
        $zip->addFromString('files/test_root/legit.txt', 'legittimo');
        $zip->close();

        $dest = $this->makeRoot('safe');
        $service = new BackupFilesService([
            ['key' => 'test_root', 'base' => $this->tmpRel . '/safe'],
        ]);

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $result = $service->restoreFromZip($zip);
        $zip->close();

        $this->assertSame(1, $result['restored']);
        $this->assertSame(4, $result['skipped']);
        $this->assertCount(4, $result['warnings']);
        $this->assertSame('legittimo', file_get_contents($dest . '/legit.txt'));

        // Niente deve essere stato scritto fuori dalla radice di destinazione.
        $this->assertFileDoesNotExist($this->tmpAbs . '/evil.txt');
        $this->assertFileDoesNotExist($this->tmpAbs . '/evil2.txt');
        $this->assertFileDoesNotExist($this->tmpAbs . '/safe/../evil.txt');
    }

    public function testRestoreFromZipIgnoresNonFileEntries(): void
    {
        $zipPath = $this->tmpAbs . '/mixed.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('manifest.json', '{}');
        $zip->addFromString('main.sql.gz', 'not-a-user-file');
        $zip->addEmptyDir('files/test_root/emptydir');
        $zip->close();

        $service = new BackupFilesService([
            ['key' => 'test_root', 'base' => $this->tmpRel . '/ignored'],
        ]);

        $zip = new \ZipArchive();
        $zip->open($zipPath);
        $result = $service->restoreFromZip($zip);
        $zip->close();

        $this->assertSame(0, $result['restored']);
        $this->assertSame(0, $result['skipped']);
    }

    // ------------------------------------------------------------------
    // manifestHasFiles
    // ------------------------------------------------------------------

    public function testManifestHasFiles(): void
    {
        $service = new BackupFilesService();

        // Manifest v1 legacy: nessuna chiave 'files'.
        $this->assertFalse($service->manifestHasFiles(['manifest_version' => 1]));
        // v2 ma senza file.
        $this->assertFalse($service->manifestHasFiles(['files' => [
            ['key' => 'public_uploads', 'file_count' => 0],
        ]]));
        // v2 con file.
        $this->assertTrue($service->manifestHasFiles(['files' => [
            ['key' => 'public_uploads', 'file_count' => 3],
        ]]));
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function makeRoot(string $name): string
    {
        $path = $this->tmpAbs . '/' . $name;
        mkdir($path, 0755, true);
        return $path;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
