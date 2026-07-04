<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Modules\Contacts\Services\ContactFileImportService;
use App\Traits\ControllerHelpers;

/**
 * Wizard a 3 step per importare contatti da file CSV o vCard.
 *
 * 1) GET  /contacts/import/file               → upload()
 * 2) POST /contacts/import/file/upload        → store()      sposta in temp + redirect a preview
 * 3) GET  /contacts/import/file/preview       → preview()    anteprima + mapping (CSV) / lista (vCard)
 * 4) POST /contacts/import/file/commit        → commit()     import effettivo + flash summary
 * 5) GET  /contacts/import/file/template.csv  → template()   download CSV template
 *
 * File caricato in storage/imports/<userId>/<token>.<ext>, riferito via token.
 * Permesso: contatti.import (gating al livello router).
 */
class FileImportController extends Controller
{
    use ControllerHelpers;

    private const MAX_BYTES        = 2 * 1024 * 1024;
    private const TOKEN_TTL_SEC    = 1800;            // 30 minuti
    private const ALLOWED_EXT      = ['csv', 'vcf', 'txt'];
    private const ALLOWED_MIMES    = [
        'text/csv', 'text/plain', 'application/csv',
        'text/vcard', 'text/x-vcard', 'text/directory',
        'application/octet-stream',                    // alcuni .vcf da iOS
    ];

    private ContactFileImportService $importer;

    public function __construct()
    {
        $this->importer = app(ContactFileImportService::class);
    }

    // ── STEP 1: pagina di upload ────────────────────────────────────────────

    public function upload(): void
    {
        $this->render('Contacts/Views/import/file/upload', [
            'pageTitle'   => 'Importa contatti da file',
            'breadcrumbs' => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Importa', 'route' => 'contacts.import.index'],
                ['label' => 'Da file'],
            ],
        ]);
    }

    // ── STEP 2: ricezione file, validazione, redirect a preview ─────────────

    public function store(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $file   = $_FILES['file'] ?? null;

        if (!$file || ($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
            flash_error($this->describeUploadError($file['error'] ?? \UPLOAD_ERR_NO_FILE));
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            flash_error('Il file supera la dimensione massima di 2 MB.');
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        $origName = (string) ($file['name'] ?? '');
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            flash_error('Estensione non supportata. Usa .csv o .vcf.');
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        // MIME via finfo (magic bytes) — più affidabile del Content-Type del client.
        $tmpPath = (string) $file['tmp_name'];
        $mime    = $this->detectMime($tmpPath);
        if ($mime !== null && !in_array($mime, self::ALLOWED_MIMES, true)) {
            flash_error('Tipo di file non valido (rilevato: ' . $mime . ').');
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        $format = $this->importer->detectFormat($origName);
        if ($format === null) {
            flash_error('Formato non riconosciuto.');
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        // Sposta in storage/imports/<userId>/<token>.<ext>
        $dir = $this->userImportDir($userId);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            flash_error('Impossibile preparare la directory di upload.');
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        $token  = bin2hex(random_bytes(16));
        $stored = $dir . DIRECTORY_SEPARATOR . $token . '.' . $ext;

        if (!@move_uploaded_file($tmpPath, $stored)) {
            flash_error('Errore nel salvataggio temporaneo del file.');
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        $this->cleanupExpiredTokens($userId);

        $_SESSION['_contatti_import'] = [
            'token'     => $token,
            'format'    => $format,
            'ext'       => $ext,
            'orig_name' => $origName,
            'expires'   => time() + self::TOKEN_TTL_SEC,
        ];

        $this->redirect(route('contacts.import.file.preview'));
    }

    // ── STEP 3: pagina di anteprima / mapping ───────────────────────────────

    public function preview(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $state  = $this->getValidState($userId);
        if ($state === null) {
            return;
        }

        try {
            $info = $this->importer->preview($state['filepath'], $state['format'], $userId);
        } catch (\Throwable $e) {
            flash_error('Impossibile leggere il file: ' . $e->getMessage());
            $this->redirect(route('contacts.import.file.upload'));
            return;
        }

        $this->render('Contacts/Views/import/file/preview', [
            'pageTitle'   => 'Anteprima importazione',
            'token'       => $state['token'],
            'format'      => $state['format'],
            'origName'    => $state['orig_name'],
            'info'        => $info,
            'targets'     => ContactFileImportService::TARGET_FIELDS,
            'breadcrumbs' => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Importa', 'route' => 'contacts.import.index'],
                ['label' => 'Da file', 'route' => 'contacts.import.file.upload'],
                ['label' => 'Anteprima'],
            ],
        ]);
    }

    // ── STEP 4: commit dell'import ──────────────────────────────────────────

    public function commit(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $state  = $this->getValidState($userId);
        if ($state === null) {
            return;
        }

        $mapping = [];
        if ($state['format'] === ContactFileImportService::FORMAT_CSV) {
            // POST['mapping'] = [colIdx => fieldKey]; sanitize.
            $raw = $_POST['mapping'] ?? [];
            if (!is_array($raw)) {
                $raw = [];
            }
            foreach ($raw as $k => $v) {
                $mapping[(int) $k] = is_string($v) ? trim($v) : '';
            }
        }

        try {
            $summary = $this->importer->import($state['filepath'], $state['format'], $userId, $mapping);
        } catch (\Throwable $e) {
            flash_error('Errore durante l\'importazione: ' . $e->getMessage());
            $this->redirect(route('contacts.import.file.preview'));
            return;
        }

        // Cleanup file e state.
        @unlink($state['filepath']);
        unset($_SESSION['_contatti_import']);

        $_SESSION['_contatti_import_result'] = $summary;
        $_SESSION['_flash_success'] = sprintf(
            'Import completato: %d creati, %d saltati (duplicati), %d rifiutati.',
            $summary['created'],
            $summary['skipped'],
            count($summary['rejected'])
        );
        $this->redirect(route('contacts.import.file.result'));
    }

    // ── STEP 5: pagina risultato ────────────────────────────────────────────

    public function result(): void
    {
        $summary = $_SESSION['_contatti_import_result'] ?? null;
        unset($_SESSION['_contatti_import_result']);

        if (!is_array($summary)) {
            $this->redirect(route('contacts.import.index'));
            return;
        }

        $this->render('Contacts/Views/import/file/result', [
            'pageTitle'   => 'Esito importazione',
            'summary'     => $summary,
            'breadcrumbs' => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Importa', 'route' => 'contacts.import.index'],
                ['label' => 'Da file', 'route' => 'contacts.import.file.upload'],
                ['label' => 'Esito'],
            ],
        ]);
    }

    // ── Download del template CSV ───────────────────────────────────────────

    public function template(): void
    {
        $headers = [
            'nome', 'cognome', 'azienda', 'ruolo',
            'email', 'telefono', 'telefono_alt',
            'indirizzo', 'sito_web', 'linkedin',
            'tags', 'note',
        ];
        $sample = [
            'Mario', 'Rossi', 'Acme S.r.l.', 'Sales Manager',
            'mario.rossi@example.com', '+39 333 1234567', '',
            'Via Roma 1, 20100 Milano', 'https://example.com', 'https://linkedin.com/in/mariorossi',
            'cliente, milano', 'Importato da template',
        ];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contatti-template.csv"');
        // BOM per Excel italiano
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, ';');
        fputcsv($out, $sample, ';');
        fclose($out);
        exit;
    }

    // ── Helpers privati ─────────────────────────────────────────────────────

    /**
     * Verifica che ci sia uno state valido in sessione e che il file esista.
     * Restituisce uno stato arricchito di `filepath`, o null dopo aver impostato
     * il flash error e fatto redirect.
     *
     * @return array{token:string,format:string,ext:string,orig_name:string,expires:int,filepath:string}|null
     */
    private function getValidState(int $userId): ?array
    {
        $state = $_SESSION['_contatti_import'] ?? null;
        if (!is_array($state) || empty($state['token']) || empty($state['format'])) {
            flash_error('Nessuna importazione in corso. Carica prima un file.');
            $this->redirect(route('contacts.import.file.upload'));
            return null;
        }

        if (($state['expires'] ?? 0) < time()) {
            unset($_SESSION['_contatti_import']);
            flash_error('L\'importazione è scaduta. Carica di nuovo il file.');
            $this->redirect(route('contacts.import.file.upload'));
            return null;
        }

        $path = $this->userImportDir($userId) . DIRECTORY_SEPARATOR
              . $state['token'] . '.' . $state['ext'];
        if (!is_file($path)) {
            unset($_SESSION['_contatti_import']);
            flash_error('File temporaneo non trovato. Caricalo di nuovo.');
            $this->redirect(route('contacts.import.file.upload'));
            return null;
        }

        $state['filepath'] = $path;
        return $state;
    }

    private function userImportDir(int $userId): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        return $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
             . 'imports' . DIRECTORY_SEPARATOR . $userId;
    }

    private function cleanupExpiredTokens(int $userId): void
    {
        $dir = $this->userImportDir($userId);
        if (!is_dir($dir)) {
            return;
        }
        $now = time();
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            if (is_file($f) && ($now - @filemtime($f)) > self::TOKEN_TTL_SEC) {
                @unlink($f);
            }
        }
    }

    private function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) {
            return null;
        }
        $finfo = finfo_open(\FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime ?: null;
    }

    private function describeUploadError(int $code): string
    {
        return match ($code) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Il file è troppo grande.',
            \UPLOAD_ERR_PARTIAL    => 'Upload interrotto. Riprova.',
            \UPLOAD_ERR_NO_FILE    => 'Nessun file selezionato.',
            \UPLOAD_ERR_NO_TMP_DIR => 'Server mal configurato (tmp dir).',
            \UPLOAD_ERR_CANT_WRITE => 'Impossibile scrivere il file sul server.',
            default                => 'Errore di upload.',
        };
    }
}
