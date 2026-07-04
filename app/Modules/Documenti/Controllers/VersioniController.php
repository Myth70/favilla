<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentiStorageService;
use App\Modules\Documenti\Services\DocumentoService;
use App\Modules\Documenti\Services\VersioningService;
use App\Traits\ControllerHelpers;

class VersioniController extends Controller
{
    use ControllerHelpers;

    private VersioningService       $versioning;
    private DocumentoService        $documenti;
    private DocumentiStorageService $storage;

    public function __construct()
    {
        $this->versioning = app(VersioningService::class);
        $this->documenti  = app(DocumentoService::class);
        $this->storage    = app(DocumentiStorageService::class);
    }

    /**
     * Carica una nuova versione del documento.
     */
    public function store(string $docId): void
    {
        $docId  = (int) $docId;
        $user   = auth();
        $userId = (int) $user['id'];

        $clean = $this->cleanPost(['note']);

        if (empty($_FILES['file']['name'])) {
            flash_error(t('documenti.flash.seleziona_file'));
            $this->redirect(route('documenti.show', ['id' => $docId]));
            return;
        }

        try {
            $this->versioning->caricaNuovaVersione(
                $docId,
                $_FILES['file'],
                $clean['note'] ?: null,
                $userId
            );
            if ($this->isHtmxRequest()) {
                $timeline = $this->documenti->versioniTimeline($docId);
                $this->hxToast(t('documenti.flash.nuova_versione_caricata'), 'success');
                $this->renderPartial('Documenti/Views/partials/timeline_versioni', [
                    'versioni'           => $timeline['versioni'],
                    'docId'              => $docId,
                    'versioneCorrenteId' => $timeline['versioneCorrenteId'],
                ]);
                return;
            }
            flash_success(t('documenti.flash.nuova_versione_caricata'));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.show', ['id' => $docId]));
    }

    /**
     * Download del file della versione (con controllo di visibilità del documento).
     */
    public function download(string $docId, string $versioneId): void
    {
        $docId      = (int) $docId;
        $versioneId = (int) $versioneId;
        $userId = (int) (auth()['id'] ?? 0);

        $fileRecord = $this->documenti->fileVersioneVisibile($docId, $versioneId, $userId);
        if (!$fileRecord) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }

        $physicalPath = $this->storage->physicalPath($fileRecord);
        if (!file_exists($physicalPath)) {
            http_response_code(404);
            $this->render('errors/404', ['message' => t('documenti.exception.file_fisico_non_trovato')]);
            return;
        }

        $this->sendFile($fileRecord, $physicalPath, 'attachment');
    }

    /**
     * Preview inline del file della versione (con controllo di visibilità del documento).
     */
    public function preview(string $docId, string $versioneId): void
    {
        $docId      = (int) $docId;
        $versioneId = (int) $versioneId;
        $userId = (int) (auth()['id'] ?? 0);

        $fileRecord = $this->documenti->fileVersioneVisibile($docId, $versioneId, $userId);
        if (!$fileRecord) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }

        $physicalPath = $this->storage->physicalPath($fileRecord);
        if (!file_exists($physicalPath)) {
            http_response_code(404);
            $this->render('errors/404', ['message' => t('documenti.exception.file_fisico_non_trovato')]);
            return;
        }

        $this->sendFile($fileRecord, $physicalPath, 'inline');
    }

    /**
     * Invia il file con header sicuri (RFC 5987 + CRLF stripping per evitare header injection).
     *
     * @param array  $fileRecord   Record da documenti_files (deve avere chiave mime_type)
     * @param string $physicalPath Path assoluto del file
     * @param string $disposition  "attachment" | "inline"
     */
    private function sendFile(array $fileRecord, string $physicalPath, string $disposition): void
    {
        // Verifica integrità opzionale (default off): rileva sostituzioni fuori banda
        // prima di servire il file. Attivabile per categorie/installazioni sensibili.
        if (config('app.documenti.verify_on_download', false)) {
            $status = DocumentiStorageService::verifyChecksum($physicalPath, $fileRecord['checksum_sha256'] ?? null);
            if ($status === 'mismatch') {
                \App\Services\AuditService::log(
                    'documento.integrita_violata',
                    'documento.file',
                    (int) ($fileRecord['id'] ?? 0),
                    [],
                    ['stored_name' => $fileRecord['stored_name'] ?? '', 'origine' => 'download']
                );
                http_response_code(409);
                header('Content-Type: text/plain; charset=UTF-8');
                echo t('documenti.exception.integrita_non_verificata');
                return;
            }
        }

        $mime     = $fileRecord['mime_type'] ?? 'application/octet-stream';
        $original = (string) ($fileRecord['original_name'] ?? basename($physicalPath));
        // ASCII fallback: rimuove non stampabili, CR/LF e doppi apici (anti header injection).
        $asciiFallback = preg_replace('/[^\x20-\x7E]|[\r\n"\\\\]/', '_', $original);
        $utf8Encoded   = rawurlencode($original);

        header('Content-Type: ' . $mime);
        header(sprintf(
            'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
            $disposition,
            $asciiFallback,
            $utf8Encoded
        ));
        header('Content-Length: ' . filesize($physicalPath));
        header('Cache-Control: private, no-cache');
        header('X-Content-Type-Options: nosniff');
        readfile($physicalPath);
        exit;
    }

    /**
     * Ripristina una versione precedente (crea una nuova versione con il file della sorgente).
     */
    public function ripristina(string $docId, string $versioneId): void
    {
        $docId      = (int) $docId;
        $versioneId = (int) $versioneId;
        $userId = (int) (auth()['id'] ?? 0);

        try {
            $this->versioning->ripristina($docId, $versioneId, $userId);
            if ($this->isHtmxRequest()) {
                $timeline = $this->documenti->versioniTimeline($docId);
                $this->hxToast(t('documenti.flash.versione_ripristinata'), 'success');
                $this->renderPartial('Documenti/Views/partials/timeline_versioni', [
                    'versioni'           => $timeline['versioni'],
                    'docId'              => $docId,
                    'versioneCorrenteId' => $timeline['versioneCorrenteId'],
                ]);
                return;
            }
            flash_success(t('documenti.flash.versione_ripristinata'));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.show', ['id' => $docId]));
    }
}
