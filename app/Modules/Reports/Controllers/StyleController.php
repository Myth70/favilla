<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Services\StyleService;
use App\Traits\ControllerHelpers;

class StyleController extends Controller
{
    use ControllerHelpers;

    private StyleService $styleService;

    public function __construct()
    {
        $this->styleService = app(StyleService::class);
    }

    /**
     * Anteprima logo stile — GET /reports/styles/{id}/logo/{slot}.
     * uploads/reports non è servita da Apache (contiene anche i PDF generati):
     * i loghi del form passano da qui, dietro il permesso reports.styles.
     */
    public function logo(string $id, string $slot): void
    {
        $style = $this->styleService->find((int) $id);
        $field = $slot === 'secondary' ? 'logo_secondary_path' : 'logo_path';

        if (!$style || empty($style[$field])) {
            http_response_code(404);
            return;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $filePath = $basePath . '/public/uploads/reports/' . basename((string) $style[$field]);
        if (!is_file($filePath)) {
            http_response_code(404);
            return;
        }

        // Solo immagini (i loghi sono validati in upload; difesa in profondità).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($filePath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, max-age=300');
        readfile($filePath);
        exit;
    }

    // ── create — Form for new style ─────────────────────────────────────────

    public function create(): void
    {
        $errors = $_SESSION['_errors'] ?? [];
        $old    = $_SESSION['_old']    ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Reports/Views/styles/form', [
            'style'      => null,
            'errors'     => $errors,
            'old'        => $old,
            'pageTitle'  => t('reports.style.title_new'),
            'breadcrumbs' => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.templates'), 'route' => 'reports.templates.index'],
                ['label' => t('reports.breadcrumb.style_new')],
            ],
        ]);
    }

    // ── store — Save new style ──────────────────────────────────────────────

    public function store(): void
    {
        $clean = $this->cleanPost([
            'name', 'description', 'primary_color', 'secondary_color',
            'accent_color', 'header_bg_color', 'header_text_color',
            'zebra_color', 'font_family', 'font_size_base',
        ]);

        // Validate
        $errors = [];
        if (empty(trim($clean['name'] ?? ''))) {
            $errors['name'] = [t('reports.flash.style_name_required')];
        }

        if ($errors) {
            if ($this->isAjaxRequest()) {
                $this->jsonStyleError($errors);
                return;
            }
            $this->flashErrors($errors, $_POST, 'reports.styles.create');
            return;
        }

        // Build data
        $data = [
            'name'              => $clean['name'],
            'description'       => $clean['description'] ?? '',
            'primary_color'     => $clean['primary_color'] ?: '#3b82f6',
            'secondary_color'   => $clean['secondary_color'] ?: '#64748b',
            'accent_color'      => $clean['accent_color'] ?: '#f97316',
            'header_bg_color'   => $clean['header_bg_color'] ?: '#1e293b',
            'header_text_color' => $clean['header_text_color'] ?: '#ffffff',
            'zebra_color'       => $clean['zebra_color'] ?: '#f8fafc',
            'font_family'       => $clean['font_family'] ?: 'dejavusans',
            'font_size_base'    => max(6, min(16, (int) ($clean['font_size_base'] ?? 9))),
            'is_default'        => !empty($_POST['is_default']) ? 1 : 0,
            'created_by'        => (int) auth()['id'],
        ];

        try {
            $id = $this->styleService->create($data, $_FILES);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonStyleError(['logo' => [$e->getMessage()]]);
                return;
            }
            $errors['logo'] = [$e->getMessage()];
            $this->flashErrors($errors, $_POST, 'reports.styles.create');
            return;
        }

        if ($this->isAjaxRequest()) {
            $preset = $this->styleService->find((int) $id);
            $this->jsonStyleOk($preset);
            return;
        }

        flash_success(t('reports.flash.style_created'));
        header('Location: ' . route('reports.templates.index'));
        exit;
    }

    // ── edit — Form for existing style ──────────────────────────────────────

    public function edit(string $id): void
    {
        $id = (int) $id;
        $style = $this->styleService->find($id);
        if (!$style) {
            if ($this->isAjaxRequest()) {
                $this->jsonStyleError(['_' => [t('reports.flash.style_not_found')]], 404);
                return;
            }
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }

        if ($this->isAjaxRequest()) {
            $this->jsonStyleOk($style);
            return;
        }

        $errors = $_SESSION['_errors'] ?? [];
        $old    = $_SESSION['_old']    ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Reports/Views/styles/form', [
            'style'      => $style,
            'errors'     => $errors,
            'old'        => $old,
            'pageTitle'  => t('reports.style.title_edit') . ' — ' . e($style['name']),
            'breadcrumbs' => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.templates'), 'route' => 'reports.templates.index'],
                ['label' => e($style['name'])],
            ],
        ]);
    }

    // ── update — Update existing style ──────────────────────────────────────

    public function update(string $id): void
    {
        $id = (int) $id;
        $style = $this->styleService->find($id);
        if (!$style) {
            if ($this->isAjaxRequest()) {
                $this->jsonStyleError(['_' => [t('reports.flash.style_not_found')]], 404);
                return;
            }
            http_response_code(404);
            exit;
        }

        $clean = $this->cleanPost([
            'name', 'description', 'primary_color', 'secondary_color',
            'accent_color', 'header_bg_color', 'header_text_color',
            'zebra_color', 'font_family', 'font_size_base',
        ]);

        // Validate
        $errors = [];
        if (empty(trim($clean['name'] ?? ''))) {
            $errors['name'] = [t('reports.flash.style_name_required')];
        }

        if ($errors) {
            if ($this->isAjaxRequest()) {
                $this->jsonStyleError($errors);
                return;
            }
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            header('Location: ' . route('reports.styles.edit', ['id' => $id]));
            exit;
        }

        $data = [
            'name'              => $clean['name'],
            'description'       => $clean['description'] ?? '',
            'primary_color'     => $clean['primary_color'] ?: '#3b82f6',
            'secondary_color'   => $clean['secondary_color'] ?: '#64748b',
            'accent_color'      => $clean['accent_color'] ?: '#f97316',
            'header_bg_color'   => $clean['header_bg_color'] ?: '#1e293b',
            'header_text_color' => $clean['header_text_color'] ?: '#ffffff',
            'zebra_color'       => $clean['zebra_color'] ?: '#f8fafc',
            'font_family'       => $clean['font_family'] ?: 'dejavusans',
            'font_size_base'    => max(6, min(16, (int) ($clean['font_size_base'] ?? 9))),
            'is_default'        => !empty($_POST['is_default']) ? 1 : 0,
        ];

        try {
            $this->styleService->update($id, $data, $_FILES);
        } catch (\RuntimeException $e) {
            if ($this->isAjaxRequest()) {
                $this->jsonStyleError(['logo' => [$e->getMessage()]]);
                return;
            }
            $_SESSION['_errors'] = ['logo' => [$e->getMessage()]];
            $_SESSION['_old']    = $_POST;
            header('Location: ' . route('reports.styles.edit', ['id' => $id]));
            exit;
        }

        if ($this->isAjaxRequest()) {
            $this->jsonStyleOk($this->styleService->find($id));
            return;
        }

        flash_success(t('reports.flash.style_updated'));
        header('Location: ' . route('reports.templates.index'));
        exit;
    }

    // ── AJAX response helpers ───────────────────────────────────────────────

    private function jsonStyleOk(?array $preset): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'     => true,
            'preset' => $preset ? [
                'id'                => (int) $preset['id'],
                'name'              => $preset['name'],
                'description'       => $preset['description'] ?? '',
                'primary_color'     => $preset['primary_color'] ?? '#3b82f6',
                'secondary_color'   => $preset['secondary_color'] ?? '#64748b',
                'accent_color'      => $preset['accent_color'] ?? '#f97316',
                'header_bg_color'   => $preset['header_bg_color'] ?? '#1e293b',
                'header_text_color' => $preset['header_text_color'] ?? '#ffffff',
                'zebra_color'       => $preset['zebra_color'] ?? '#f8fafc',
                'font_family'       => $preset['font_family'] ?? 'dejavusans',
                'font_size_base'    => (int) ($preset['font_size_base'] ?? 9),
                'is_default'        => (int) ($preset['is_default'] ?? 0),
            ] : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonStyleError(array $errors, int $status = 422): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $message = '';
        foreach ($errors as $fieldErrors) {
            foreach ((array) $fieldErrors as $msg) {
                $message .= ($message === '' ? '' : ' ') . (string) $msg;
            }
        }
        echo json_encode([
            'ok'      => false,
            'errors'  => $errors,
            'message' => $message !== '' ? $message : t('reports.flash.style_save_error'),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── destroy — Delete style ──────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $id = (int) $id;
        $result = $this->styleService->delete($id);

        if (is_string($result)) {
            // Error message
            if ($this->isHtmxRequest()) {
                $this->hxToast($result, 'danger');
                return;
            }
            flash_error($result);
            header('Location: ' . route('reports.templates.index'));
            exit;
        }

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('reports.flash.style_deleted'), 'warning');
            header('HX-Redirect: ' . route('reports.templates.index'));
            return;
        }

        flash_success(t('reports.flash.style_deleted'));
        header('Location: ' . route('reports.templates.index'));
        exit;
    }
}
