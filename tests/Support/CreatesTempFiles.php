<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Crea file temporanei per i test in modo portabile.
 *
 * `tempnam(sys_get_temp_dir(), ...)` fallisce sotto `open_basedir` (configurazione
 * XAMPP tipica), perché la temp dir di sistema (es. C:\Users\...\AppData\Local\Temp)
 * è fuori dai percorsi consentiti. Questo trait sceglie sempre una directory
 * scrivibile e dentro la root del progetto, e ripulisce i file in tearDown.
 *
 * Sostituisce gli helper ad-hoc duplicati nei test (makeTmp/makeTempFile/tempnam).
 *
 * Uso:
 *   class MioTest extends TestCase {
 *       use CreatesTempFiles;
 *       protected function tearDown(): void {
 *           $this->cleanupTempFiles();
 *           parent::tearDown();
 *       }
 *   }
 */
trait CreatesTempFiles
{
    /** @var string[] */
    private array $createdTempFiles = [];

    /**
     * Scrive $content in un file temporaneo e ne restituisce il percorso assoluto.
     */
    protected function makeTempFile(string $content = '', string $prefix = 'test_', string $suffix = '.tmp'): string
    {
        $path = $this->tempDir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8)) . $suffix;
        file_put_contents($path, $content);
        $this->createdTempFiles[] = $path;

        return $path;
    }

    /**
     * Directory temporanea scrivibile e compatibile con open_basedir.
     * Preferisce storage/tmp del progetto; ricade su sys_get_temp_dir solo se
     * la prima non è disponibile.
     */
    protected function tempDir(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $projectTmp = $base . '/storage/tmp';

        if (is_dir($projectTmp) || @mkdir($projectTmp, 0777, true) || is_dir($projectTmp)) {
            return $projectTmp;
        }

        return sys_get_temp_dir();
    }

    /**
     * Elimina tutti i file temporanei creati. Chiamare in tearDown().
     */
    protected function cleanupTempFiles(): void
    {
        foreach ($this->createdTempFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        $this->createdTempFiles = [];
    }
}
