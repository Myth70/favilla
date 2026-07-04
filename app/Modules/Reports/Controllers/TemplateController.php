<?php

declare(strict_types=1);

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Modules\Reports\Services\BundledTemplateService;
use App\Modules\Reports\Services\ReportsDocumentBindingService;
use App\Modules\Reports\Services\ReportsTemplateQueryService;
use App\Modules\Reports\Services\TemplateHtmlSanitizer;
use App\Modules\Reports\Services\TemplateService;
use App\Traits\ControllerHelpers;

class TemplateController extends Controller
{
    use ControllerHelpers;

    private TemplateService $templateService;
    private BundledTemplateService $bundledService;
    private ReportsTemplateQueryService $queryService;
    private \App\Modules\Reports\Services\ExportProviderService $providerService;
    private ReportsDocumentBindingService $bindingService;
    private TemplateHtmlSanitizer $htmlSanitizer;

    public function __construct()
    {
        $this->templateService = app(TemplateService::class);
        $this->bundledService = app(BundledTemplateService::class);
        $this->queryService = app(ReportsTemplateQueryService::class);
        $this->providerService = app(\App\Modules\Reports\Services\ExportProviderService::class);
        $this->bindingService = app(ReportsDocumentBindingService::class);
        $this->htmlSanitizer = app(TemplateHtmlSanitizer::class);
    }

    /**
     * Broken-access-control guard sui template indirizzati per id.
     *
     * findTemplate() risolve per id SENZA scoping per proprietario: senza questo
     * check chiunque abbia reports.edit/reports.delete potrebbe modificare o
     * eliminare i template altrui (anche 'private') indovinando l'id. Solo il
     * proprietario o chi ha reports.admin (override elevato dedicato) può gestire
     * il template. 403 + stop in caso contrario.
     */
    private function assertCanManageTemplate(array $template): void
    {
        if (has_permission('reports.admin')) {
            return;
        }
        if ((int) ($template['created_by'] ?? 0) === (int) auth()['id']) {
            return;
        }
        http_response_code(403);
        exit;
    }

    // ── index — List templates ──────────────────────────────────────────────

    public function index(): void
    {
        $user      = auth();
        $userId    = (int) $user['id'];
        $userRoles = $user['roles'] ?? [];

        $clean   = $this->cleanGet(['q', 'module', 'format', 'sort', 'dir', 'page'], 255);
        $filters = [
            'q'      => $clean['q'] ?? '',
            'module' => $clean['module'] ?? '',
            'format' => $clean['format'] ?? '',
            'sort'   => $clean['sort'] ?? 'created_at',
            'dir'    => $clean['dir'] ?? 'DESC',
        ];
        $page = max(1, (int) ($clean['page'] ?? 1));

        $result = $this->queryService->getIndexData($userId, $userRoles, $filters, $page);

        $data = [
            'items'       => $result['items'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'per_page'    => $result['per_page'],
            'total_pages' => $result['total_pages'],
            'filters'     => $result['filters'],
            'modules'     => $result['modules'],
            'pageTitle'   => t('reports.templates.title'),
            'breadcrumbs' => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.templates')],
            ],
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Reports/Views/templates/partials/template_cards', $data);
            return;
        }

        $this->render('Reports/Views/templates/index', $data);
    }

    // ── wizard — 3-step guided flow to create a new template ────────────────

    public function wizard(): void
    {
        $designerData = $this->queryService->getDesignerData(auth());

        // Flatten sources for the wizard cards.
        $sourcesByModule = [];
        foreach ($designerData['sources'] as $group) {
            $mod = $group['module'] ?? 'Altro';
            foreach ($group['sources'] ?? [] as $src) {
                $src['module'] = $mod;
                $sourcesByModule[$mod][] = $src;
            }
        }
        ksort($sourcesByModule);

        $prefill = [
            'source_type'   => $_GET['source_type'] ?? 'list',
            'output_format' => $_GET['output_format'] ?? 'pdf',
            'module'        => $_GET['module'] ?? '',
            'source_key'    => $_GET['source_key'] ?? '',
        ];

        $this->render('Reports/Views/templates/wizard', [
            'sourcesByModule' => $sourcesByModule,
            'styles'          => $designerData['styles'],
            'prefill'         => $prefill,
            'pageTitle'       => t('reports.wizard.title'),
            'breadcrumbs'     => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.templates'), 'route' => 'reports.templates.index'],
                ['label' => t('reports.breadcrumb.template_new')],
            ],
        ]);
    }

    // ── create — Unified GrapeJS designer for a new template ────────────────

    public function create(): void
    {
        $this->showDesigner(null);
    }

    /**
     * Render the unified designer view for create or edit.
     */
    private function showDesigner(?int $templateId): void
    {
        $designerData = $this->queryService->getDesignerData(auth(), $templateId);
        if ($templateId !== null && $designerData === null) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }
        $template = $designerData['template'] ?? null;

        $preModule    = $_GET['module']        ?? null;
        $preSource    = $_GET['source_key']    ?? null;
        $preFormat    = $_GET['output_format'] ?? null;
        $preType      = $_GET['source_type']   ?? null;

        $title = $template
            ? t('reports.designer.title_edit') . ' — ' . e($template['name'])
            : t('reports.designer.title_new');

        $documentBindings = [];
        if ($template && ($template['source_type'] ?? '') === 'document' && !empty($template['id'])) {
            $documentBindings = $this->bindingService->listBindingsForTemplate((int) $template['id']);
        }

        $this->render('Reports/Views/templates/grapesjs-designer', [
            'template'         => $template,
            'sources'          => $designerData['sources'],
            'stylePresets'     => $designerData['styles'],
            'documentBindings' => $documentBindings,
            'preModule'        => $preModule,
            'preSource'        => $preSource,
            'preFormat'        => $preFormat,
            'preType'          => $preType,
            'pageTitle'        => $title,
            'breadcrumbs'  => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.templates'), 'route' => 'reports.templates.index'],
                ['label' => $template ? e($template['name']) : t('reports.breadcrumb.template_new')],
            ],
        ]);
    }

    // ── store — Save new template ───────────────────────────────────────────

    public function store(): void
    {
        $clean = $this->cleanPost([
            'name', 'description', 'module', 'source_key', 'output_format',
            'source_type', 'visibility', 'max_rows',
        ]);

        // Validate required
        $errors = [];
        if (empty(trim($clean['name'] ?? ''))) {
            $errors['name'] = [t('reports.flash.tpl_name_required')];
        }
        if (empty(trim($clean['module'] ?? ''))) {
            $errors['module'] = [t('reports.flash.tpl_module_required')];
        }
        if (empty(trim($clean['source_key'] ?? ''))) {
            $errors['source_key'] = [t('reports.flash.tpl_source_required')];
        }
        $module = trim((string) ($clean['module'] ?? ''));
        $sourceKey = trim((string) ($clean['source_key'] ?? ''));
        if ($module !== '' && $sourceKey !== '' && !$this->providerService->sourceExists($module, $sourceKey)) {
            $errors['source_key'] = [t('reports.flash.tpl_source_unavailable')];
        }

        if ($errors) {
            $this->flashErrors($errors, $_POST, 'reports.templates.create');
            return;
        }

        // Build data
        $data = [
            'name'            => $clean['name'],
            'description'     => $clean['description'] ?? '',
            'module'          => $clean['module'] ?? '',
            'source_key'      => $clean['source_key'] ?? '',
            'output_format'   => in_array($clean['output_format'] ?? '', ['csv', 'excel', 'pdf'], true)
                                    ? $clean['output_format'] : 'pdf',
            'source_type'     => in_array($clean['source_type'] ?? '', ['list', 'document'], true)
                                    ? $clean['source_type'] : 'list',
            'visibility'      => in_array($clean['visibility'] ?? '', ['private', 'role', 'global'], true)
                                    ? $clean['visibility'] : 'private',
            'max_rows'        => max(1, (int) ($clean['max_rows'] ?? 10000)),
            'created_by'      => (int) auth()['id'],
        ];

        $filtersConfig = json_decode($_POST['filters_config'] ?? 'null', true);
        $data['filters_config'] = $filtersConfig !== null ? json_encode($filtersConfig) : null;

        $sortingConfig = json_decode($_POST['sorting_config'] ?? 'null', true);
        $data['sorting_config'] = $sortingConfig !== null ? json_encode($sortingConfig) : null;

        $data['template_html'] = $this->htmlSanitizer->sanitize($_POST['template_html'] ?? null);

        // Style preset
        $stylePresetId = (int) ($_POST['style_preset_id'] ?? 0);
        $data['style_preset_id'] = $stylePresetId > 0 ? $stylePresetId : null;

        // Visible to roles (JSON array)
        $visibleToRoles = json_decode($_POST['visible_to_roles'] ?? 'null', true);
        $data['visible_to_roles'] = is_array($visibleToRoles) ? json_encode($visibleToRoles) : null;

        $id = $this->queryService->createTemplate($data);

        flash_success(t('reports.flash.tpl_created'));
        header('Location: ' . route('reports.templates.index'));
        exit;
    }

    // ── edit — Show designer for existing template ──────────────────────────

    public function edit(string $id): void
    {
        $template = $this->queryService->findTemplate((int) $id);
        if (!$template) {
            http_response_code(404);
            exit;
        }
        $this->assertCanManageTemplate($template);

        $this->showDesigner((int) $id);
    }

    // ── update — Update existing template ───────────────────────────────────

    public function update(string $id): void
    {
        $id = (int) $id;
        $template = $this->queryService->findTemplate($id);
        if (!$template) {
            http_response_code(404);
            exit;
        }
        $this->assertCanManageTemplate($template);

        $clean = $this->cleanPost([
            'name', 'description', 'module', 'source_key', 'output_format',
            'source_type', 'visibility', 'max_rows',
        ]);

        $errors = [];
        if (empty(trim($clean['name'] ?? ''))) {
            $errors['name'] = [t('reports.flash.tpl_name_required')];
        }
        if (empty(trim($clean['module'] ?? ''))) {
            $errors['module'] = [t('reports.flash.tpl_module_required')];
        }
        if (empty(trim($clean['source_key'] ?? ''))) {
            $errors['source_key'] = [t('reports.flash.tpl_source_required')];
        }
        $module = trim((string) ($clean['module'] ?? ''));
        $sourceKey = trim((string) ($clean['source_key'] ?? ''));
        if ($module !== '' && $sourceKey !== '' && !$this->providerService->sourceExists($module, $sourceKey)) {
            $errors['source_key'] = [t('reports.flash.tpl_source_unavailable')];
        }

        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            header('Location: ' . route('reports.templates.edit', ['id' => $id]));
            exit;
        }

        $data = [
            'name'            => $clean['name'],
            'description'     => $clean['description'] ?? '',
            'module'          => $clean['module'] ?? '',
            'source_key'      => $clean['source_key'] ?? '',
            'output_format'   => in_array($clean['output_format'] ?? '', ['csv', 'excel', 'pdf'], true)
                                    ? $clean['output_format'] : 'pdf',
            'source_type'     => in_array($clean['source_type'] ?? '', ['list', 'document'], true)
                                    ? $clean['source_type'] : 'list',
            'visibility'      => in_array($clean['visibility'] ?? '', ['private', 'role', 'global'], true)
                                    ? $clean['visibility'] : 'private',
            'max_rows'        => max(1, (int) ($clean['max_rows'] ?? 10000)),
        ];

        $filtersConfig = json_decode($_POST['filters_config'] ?? 'null', true);
        $data['filters_config'] = $filtersConfig !== null ? json_encode($filtersConfig) : null;

        $sortingConfig = json_decode($_POST['sorting_config'] ?? 'null', true);
        $data['sorting_config'] = $sortingConfig !== null ? json_encode($sortingConfig) : null;

        // Template HTML: keep existing if the form did not send one
        $postedHtml = $_POST['template_html'] ?? null;
        $data['template_html'] = is_string($postedHtml)
            ? $this->htmlSanitizer->sanitize($postedHtml)
            : ($template['template_html'] ?? null);

        // Style preset
        $stylePresetId = (int) ($_POST['style_preset_id'] ?? 0);
        $data['style_preset_id'] = $stylePresetId > 0 ? $stylePresetId : null;

        // Visible to roles
        $visibleToRoles = json_decode($_POST['visible_to_roles'] ?? 'null', true);
        $data['visible_to_roles'] = is_array($visibleToRoles) ? json_encode($visibleToRoles) : null;

        $this->queryService->updateTemplate($id, $data);

        flash_success(t('reports.flash.tpl_updated'));
        header('Location: ' . route('reports.templates.index'));
        exit;
    }

    // ── destroy — Delete template ───────────────────────────────────────────

    public function destroy(string $id): void
    {
        $id = (int) $id;
        $template = $this->queryService->findTemplate($id);
        if (!$template) {
            http_response_code(404);
            exit;
        }
        $this->assertCanManageTemplate($template);

        $this->queryService->deleteTemplate($id);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('reports.flash.tpl_deleted'), 'warning');
            header('HX-Redirect: ' . route('reports.templates.index'));
            return;
        }

        flash_success(t('reports.flash.tpl_deleted'));
        header('Location: ' . route('reports.templates.index'));
        exit;
    }

    // ── duplicate — Clone template ──────────────────────────────────────────

    public function duplicate(string $id): void
    {
        $id = (int) $id;
        $newId = $this->queryService->duplicateTemplate($id, (int) auth()['id']);
        if ($newId === null) {
            http_response_code(404);
            exit;
        }

        flash_success(t('reports.flash.tpl_duplicated'));
        header('Location: ' . route('reports.templates.edit', ['id' => $newId]));
        exit;
    }

    // ── preview — Preview template data ─────────────────────────────────────

    public function preview(string $id): void
    {
        $id = (int) $id;
        $template = $this->queryService->getTemplatePreviewData($id);
        if (!$template) {
            http_response_code(404);
            $this->json(['error' => t('reports.flash.tpl_not_found')], 404);
            return;
        }

        try {
            $previewData = $this->templateService->previewData($template);

            $this->renderPartial('Reports/Views/templates/partials/preview_table', [
                'template' => $template,
                'rows'     => $previewData['rows'] ?? [],
                'columns'  => $previewData['columns'] ?? [],
                'total'    => $previewData['total'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->renderPartial('Reports/Views/templates/partials/preview_table', [
                'template' => $template,
                'rows'     => [],
                'columns'  => [],
                'total'    => 0,
                'error'    => t('reports.flash.preview_error'),
            ]);
        }
    }

    // ── bundled — Manage module-bundled templates ─────────────────────────

    public function bundled(): void
    {
        $data = $this->queryService->getBundledData($this->bundledService);

        $this->render('Reports/Views/templates/bundled', [
            'available'     => $data['available'],
            'bundledCounts' => $data['bundledCounts'],
            'pageTitle'     => t('reports.bundled.title'),
            'breadcrumbs'   => [
                ['label' => t('reports.breadcrumb.report'), 'route' => 'reports.index'],
                ['label' => t('reports.breadcrumb.templates'), 'route' => 'reports.templates.index'],
                ['label' => t('reports.breadcrumb.bundled')],
            ],
        ]);
    }

    public function importBundled(): void
    {
        $clean = $this->cleanPost(['module_name', 'overwrite']);
        $moduleName = $clean['module_name'] ?? '';
        $overwrite = !empty($_POST['overwrite']);

        if (empty($moduleName)) {
            // Import all
            $result = $this->bundledService->importAll($overwrite);
            $msg = t('reports.flash.import_all_done', [
                'imported' => $result['imported'],
                'updated'  => $result['updated'],
                'skipped'  => $result['skipped'],
            ]);
        } else {
            $result = $this->bundledService->importFromModule($moduleName, $overwrite);
            $msg = t('reports.flash.import_module_done', [
                'module'   => $moduleName,
                'imported' => $result['imported'],
                'updated'  => $result['updated'],
                'skipped'  => $result['skipped'],
            ]);
        }

        if (!empty($result['errors'])) {
            $msg .= t('reports.flash.import_errors', ['errors' => implode('; ', $result['errors'])]);
            flash_error($msg);
        } else {
            flash_success($msg);
        }

        header('Location: ' . route('reports.templates.bundled'));
        exit;
    }
}
