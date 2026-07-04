<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Exceptions\DocumentNotFoundException;
use App\Modules\Reports\Services\DocumentService;
use App\Modules\Reports\Services\ReportsDocumentBindingService;
use App\Traits\ControllerHelpers;

class DocumentController extends Controller
{
    use ControllerHelpers;

    private ReportsDocumentBindingService $bindingService;
    private DocumentService $documentService;

    public function __construct()
    {
        $this->bindingService = app(ReportsDocumentBindingService::class);
        $this->documentService = app(DocumentService::class);
    }

    // ── edit — Show form to update an existing binding ─────────────────────

    public function edit(string $id): void
    {
        $bindingId = (int) $id;
        $binding = $this->bindingService->findBinding($bindingId);
        if (!$binding) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => t('reports.flash.binding_not_found')]);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'binding' => $this->bindingPayload($binding)]);
        exit;
    }

    // ── storeBind — Save new binding ────────────────────────────────────────

    public function storeBind(): void
    {
        $clean = $this->cleanPost(['module', 'operation', 'label']);
        $templateId = (int) ($_POST['template_id'] ?? 0);

        // Validate
        $errors = [];
        if (empty(trim($clean['module'] ?? ''))) {
            $errors['module'] = [t('reports.flash.bind_module_required')];
        }
        if (empty(trim($clean['operation'] ?? ''))) {
            $errors['operation'] = [t('reports.flash.bind_operation_required')];
        }
        if (empty(trim($clean['label'] ?? ''))) {
            $errors['label'] = [t('reports.flash.bind_label_required')];
        }
        if ($templateId <= 0) {
            $errors['template_id'] = [t('reports.flash.bind_template_required')];
        }

        if ($errors) {
            $this->jsonBindingError($errors);
        }

        $data = [
            'module'      => $clean['module'],
            'operation'   => $clean['operation'],
            'label'       => $clean['label'],
            'template_id' => $templateId,
            'created_by'  => (int) auth()['id'],
        ];

        try {
            $newId = $this->bindingService->createBinding($data);
        } catch (\PDOException $e) {
            if ($e->errorInfo[1] === 1062) {
                $this->jsonBindingError(['operation' => [t('reports.flash.bind_duplicate')]]);
            }
            throw $e;
        }

        $created = $this->bindingService->findBinding($newId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'binding' => $this->bindingPayload($created ?: array_merge($data, ['id' => $newId]))]);
        exit;
    }

    // ── update — Save an existing binding ──────────────────────────────────

    public function update(string $id): void
    {
        $bindingId = (int) $id;
        $binding = $this->bindingService->findBinding($bindingId);
        if (!$binding) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => t('reports.flash.binding_not_found')]);
            exit;
        }

        $clean = $this->cleanPost(['module', 'operation', 'label']);
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $errors = [];
        if (empty(trim($clean['module'] ?? ''))) {
            $errors['module'] = [t('reports.flash.bind_module_required')];
        }
        if (empty(trim($clean['operation'] ?? ''))) {
            $errors['operation'] = [t('reports.flash.bind_operation_required')];
        }
        if (empty(trim($clean['label'] ?? ''))) {
            $errors['label'] = [t('reports.flash.bind_label_required')];
        }
        if ($templateId <= 0) {
            $errors['template_id'] = [t('reports.flash.bind_template_required')];
        }

        if ($errors) {
            $this->jsonBindingError($errors);
        }

        $data = [
            'module'      => $clean['module'],
            'operation'   => $clean['operation'],
            'label'       => $clean['label'],
            'template_id' => $templateId,
        ];

        try {
            $this->bindingService->updateBinding($bindingId, $data);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                $this->jsonBindingError(['operation' => [t('reports.flash.bind_duplicate')]]);
            }
            throw $e;
        }

        $updated = $this->bindingService->findBinding($bindingId);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'binding' => $this->bindingPayload($updated ?: array_merge($data, ['id' => $bindingId]))]);
        exit;
    }

    // ── destroyBind — Delete a binding ──────────────────────────────────────

    public function destroyBind(string $id): void
    {
        $id = (int) $id;
        $binding = $this->bindingService->findBinding($id);
        if (!$binding) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => t('reports.flash.binding_not_found')]);
            exit;
        }

        $this->bindingService->deleteBinding($id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── generate — Generate PDF document from binding ───────────────────────

    public function generate(string $module, string $operation, int $recordId): void
    {
        try {
            $result = $this->documentService->generate($module, $operation, $recordId);
        } catch (DocumentNotFoundException $e) {
            http_response_code(404);
            flash_error($e->getMessage());
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? route('reports.templates.index')));
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            app_log('error', '[Reports] document generate error: ' . $e->getMessage());
            flash_error(t('reports.flash.doc_generate_error'));
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? route('reports.templates.index')));
            exit;
        }

        $tempFile = $result['path'];
        $filename = $result['filename'];

        // Inline display or download
        $disposition = isset($_GET['download']) && $_GET['download'] === '1'
            ? 'attachment'
            : 'inline';

        header('Content-Type: application/pdf');
        header("Content-Disposition: {$disposition}; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . filesize($tempFile));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($tempFile);
        exit;
    }

    private function bindingPayload(array $binding): array
    {
        return [
            'id'          => isset($binding['id']) ? (int) $binding['id'] : 0,
            'module'      => $binding['module'] ?? '',
            'operation'   => $binding['operation'] ?? '',
            'label'       => $binding['label'] ?? '',
            'template_id' => isset($binding['template_id']) ? (int) $binding['template_id'] : 0,
        ];
    }

    private function jsonBindingError(array $errors, int $status = 422): void
    {
        $flat = [];
        foreach ($errors as $msgs) {
            foreach ((array) $msgs as $m) {
                $flat[] = (string) $m;
            }
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => false,
            'errors'  => $errors,
            'message' => implode(' ', $flat) ?: t('reports.flash.bind_invalid'),
        ]);
        exit;
    }
}
