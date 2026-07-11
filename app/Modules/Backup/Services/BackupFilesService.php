<?php

declare(strict_types=1);

namespace App\Modules\Backup\Services;

/**
 * Enumerazione e ripristino dei file utente inclusi nei set di backup.
 *
 * Le radici sono percorsi RELATIVI a BASE_PATH (portabili tra installazioni):
 * di default `public/uploads` (avatar, contatti, Files, report) e
 * `storage/uploads` (storage Documenti, fuori webroot). Le directory
 * transitorie (storage/backups, cache, logs, sessions, tmp) restano fuori
 * per costruzione, non essendo sotto le radici.
 *
 * Dentro l'archivio ogni file vive sotto `files/<key>/<percorso relativo>`;
 * in fase di ripristino la mappa key→base è SEMPRE quella locale (mai letta
 * dal manifest), così un archivio manomesso non può redirigere le scritture.
 */
class BackupFilesService
{
    /** @var array<array{key: string, base: string}> */
    private array $roots;

    /**
     * @param array<array{key: string, base: string}>|null $roots Override per i test
     *        (base relativa a BASE_PATH). Null = radici standard.
     */
    public function __construct(?array $roots = null)
    {
        $this->roots = $roots ?? [
            ['key' => 'public_uploads', 'base' => 'public/uploads'],
            ['key' => 'storage_uploads', 'base' => 'storage/uploads'],
        ];
    }

    /**
     * Vero se l'inclusione dei file nel backup è attiva (default: sì).
     */
    public function isEnabled(): bool
    {
        return filter_var((string) env('BACKUP_INCLUDE_FILES', true), FILTER_VALIDATE_BOOL);
    }

    /**
     * Radici configurate (key + base relativa a BASE_PATH).
     *
     * @return array<array{key: string, base: string}>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    /**
     * Enumera i file da includere nel backup.
     *
     * Symlink e file non leggibili vengono saltati (i secondi con warning):
     * un link può uscire dalle radici e un backup non deve mai seguirlo.
     *
     * @return array{
     *     roots: array<array{key: string, base: string, file_count: int, total_size: int}>,
     *     files: array<array{path: string, entry: string}>,
     *     warnings: string[]
     * }
     */
    public function enumerate(): array
    {
        $rootSummaries = [];
        $files         = [];
        $warnings      = [];

        foreach ($this->roots as $root) {
            $absBase   = $this->absoluteBase($root['base']);
            $count     = 0;
            $bytes     = 0;

            if (is_dir($absBase)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $absBase,
                        \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO
                    ),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                /** @var \SplFileInfo $info */
                foreach ($iterator as $info) {
                    if ($info->isLink() || !$info->isFile()) {
                        continue;
                    }
                    $path = $info->getPathname();
                    if (!$info->isReadable()) {
                        $warnings[] = "File non leggibile escluso dal backup: {$path}";
                        continue;
                    }

                    $relative = substr($path, strlen($absBase) + 1);
                    $relative = str_replace('\\', '/', $relative);

                    $files[] = [
                        'path'  => $path,
                        'entry' => 'files/' . $root['key'] . '/' . $relative,
                    ];
                    $count++;
                    $bytes += (int) $info->getSize();
                }
            }

            $rootSummaries[] = [
                'key'        => $root['key'],
                'base'       => $root['base'],
                'file_count' => $count,
                'total_size' => $bytes,
            ];
        }

        return ['roots' => $rootSummaries, 'files' => $files, 'warnings' => $warnings];
    }

    /**
     * Ripristina i file contenuti in un archivio di backup già aperto.
     *
     * Sovrascrive i file esistenti ma NON elimina quelli assenti dall'archivio
     * (nessuna perdita di dati caricati dopo il backup; eventuali orfani sono
     * gestibili con i comandi di cleanup dei moduli). Ogni entry è validata
     * contro path traversal e mappata solo su radici note.
     *
     * @return array{restored: int, bytes: int, skipped: int, warnings: string[]}
     */
    public function restoreFromZip(\ZipArchive $zip): array
    {
        $baseByKey = [];
        foreach ($this->roots as $root) {
            $baseByKey[$root['key']] = $this->absoluteBase($root['base']);
        }

        $restored = 0;
        $bytes    = 0;
        $skipped  = 0;
        $warnings = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_starts_with($name, 'files/') || str_ends_with($name, '/')) {
                continue;
            }

            if (!preg_match('#^files/([a-z0-9_]+)/(.+)$#', $name, $m)) {
                $skipped++;
                $warnings[] = "Entry non valida ignorata: {$name}";
                continue;
            }

            [, $key, $relative] = $m;

            if (!isset($baseByKey[$key])) {
                $skipped++;
                $warnings[] = "Radice sconosciuta '{$key}' ignorata: {$name}";
                continue;
            }

            if (!$this->isSafeRelativePath($relative)) {
                $skipped++;
                $warnings[] = "Percorso non sicuro ignorato: {$name}";
                continue;
            }

            $target = $baseByKey[$key] . '/' . $relative;
            $dir    = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $skipped++;
                $warnings[] = "Impossibile creare la directory per: {$name}";
                continue;
            }

            $source = $zip->getStream($name);
            if (!is_resource($source)) {
                $skipped++;
                $warnings[] = "Entry illeggibile nell'archivio: {$name}";
                continue;
            }

            $dest = fopen($target, 'wb');
            if (!is_resource($dest)) {
                fclose($source);
                $skipped++;
                $warnings[] = "Impossibile scrivere il file: {$name}";
                continue;
            }

            $written = stream_copy_to_stream($source, $dest);
            fclose($source);
            fclose($dest);

            $restored++;
            $bytes += (int) $written;
        }

        return ['restored' => $restored, 'bytes' => $bytes, 'skipped' => $skipped, 'warnings' => $warnings];
    }

    /**
     * Un archivio contiene file da ripristinare se il manifest dichiara almeno
     * una radice con file (manifest v1 legacy: nessuna chiave 'files').
     *
     * @param array<string,mixed> $manifest
     */
    public function manifestHasFiles(array $manifest): bool
    {
        if (empty($manifest['files']) || !is_array($manifest['files'])) {
            return false;
        }
        foreach ($manifest['files'] as $root) {
            if (is_array($root) && (int) ($root['file_count'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    private function absoluteBase(string $relativeBase): string
    {
        return rtrim(BASE_PATH, '/\\') . '/' . trim(str_replace('\\', '/', $relativeBase), '/');
    }

    /**
     * Rifiuta traversal (`..`), percorsi assoluti, backslash, drive letter e NUL.
     */
    private function isSafeRelativePath(string $relative): bool
    {
        if ($relative === '' || str_starts_with($relative, '/')) {
            return false;
        }
        if (str_contains($relative, '\\') || str_contains($relative, ':') || str_contains($relative, "\0")) {
            return false;
        }
        return !preg_match('#(^|/)\.\.(/|$)#', $relative);
    }
}
