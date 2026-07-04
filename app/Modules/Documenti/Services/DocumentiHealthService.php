<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoFileRepository;

/**
 * Diagnostica di salute del modulo Documenti: directory di storage, file
 * orfani, integrità leggera. La connettività al database non è verificata
 * qui: Documenti condivide il DB dell'app, quindi un DB irraggiungibile
 * abbatte l'intera applicazione, non solo questo modulo.
 */
class DocumentiHealthService
{
    /**
     * Esegue tutti i check e restituisce un array di esiti keyed per chiave check.
     *
     * @return array<string,array{label:string,status:string,detail:string}>
     */
    public function runChecks(): array
    {
        $checks = [];

        // 1. Directory di storage (single source of truth: DocumentiStorageService::baseDir())
        $storageDir = DocumentiStorageService::baseDir();
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0750, true); // creazione lazy: la diagnostica non deve mai 500.
        }
        $exists   = is_dir($storageDir);
        $writable = $exists && is_writable($storageDir);
        $checks['storage_dir'] = [
            'label'  => t('documenti.health.storage_dir'),
            'status' => $writable ? 'ok' : 'warning',
            'detail' => $exists
                ? ($writable ? t('documenti.health.storage_dir_writable', ['path' => $storageDir]) : t('documenti.health.storage_dir_not_writable', ['path' => $storageDir]))
                : t('documenti.health.storage_dir_missing', ['path' => $storageDir]),
        ];

        // 2. File orfani
        try {
            $fileRepo = app(DocumentoFileRepository::class);
            $orphans  = $fileRepo->countOrphans();
            $checks['orphan_files'] = [
                'label'  => t('documenti.health.orphan_files'),
                'status' => $orphans > 0 ? 'warning' : 'ok',
                'detail' => t('documenti.health.orphan_files_detail', ['count' => $orphans]),
            ];
        } catch (\Throwable $e) {
            $checks['orphan_files'] = [
                'label'  => t('documenti.health.orphan_files'),
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }

        // 3. Integrità file (leggera: checksum mancanti + file fisici assenti).
        try {
            $fileRepo = $fileRepo ?? app(DocumentoFileRepository::class);
            $storage  = app(DocumentiStorageService::class);
            $nullSums = $fileRepo->countNullChecksum();
            $missing  = 0;
            foreach ($fileRepo->allForIntegrity() as $f) {
                if (!is_file($storage->physicalPath($f))) {
                    $missing++;
                }
            }
            $status = $missing > 0 ? 'error' : ($nullSums > 0 ? 'warning' : 'ok');
            $checks['file_integrity'] = [
                'label'  => t('documenti.health.file_integrity'),
                'status' => $status,
                'detail' => t('documenti.health.file_integrity_detail', ['missing' => $missing, 'null_checksum' => $nullSums]),
            ];
        } catch (\Throwable $e) {
            $checks['file_integrity'] = [
                'label'  => t('documenti.health.file_integrity'),
                'status' => 'error',
                'detail' => $e->getMessage(),
            ];
        }

        return $checks;
    }
}
