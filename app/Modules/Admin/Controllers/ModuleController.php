<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\ModuleLoader;
use App\Modules\Admin\Services\ModuleExportService;
use App\Modules\Admin\Services\ModuleImportService;
use App\Modules\Admin\Services\ModuleManagementService;
use App\Modules\Admin\Services\ModuleUninstallService;

class ModuleController extends Controller
{
    private ModuleLoader              $loader;
    private ModuleManagementService   $moduleService;
    private ?array $cachedModuleList = null;

    public function __construct()
    {
        $this->loader        = app(ModuleLoader::class);
        $this->moduleService = app(ModuleManagementService::class);
    }

    public function index(): void
    {
        $modules     = $this->moduleService->getModulesWithStatus();
        $coreModules = $this->moduleService->getCoreModules();

        $this->render('Admin/Views/modules/index', [
            'modules'     => $modules,
            'coreModules' => $coreModules,
            'pageTitle'   => t('admin.modules.title'),
            'breadcrumbs' => [['label' => 'Admin', 'route' => 'admin.dashboard'], ['label' => t('admin.modules.breadcrumb')]],
        ]);
    }

    public function toggle(string $name): void
    {
        if (!$this->isValidModuleName($name)) {
            http_response_code(404);
            return;
        }

        // Core modules cannot be disabled/enabled via UI
        $coreModules = array_column(
            array_filter($this->loader->getModules(), fn ($m) => ($m['core'] ?? false) === true),
            'name'
        );
        if (in_array($name, $coreModules, true)) {
            $coreMsg = t('admin.modules.flash_core_no_disable');
            if ($this->isHtmxRequest()) {
                header('HX-Trigger: ' . json_encode(['notify' => ['message' => $coreMsg, 'type' => 'warning']]));
                http_response_code(403);
                return;
            }
            flash_error($coreMsg);
            $this->redirect(route('admin.modules.index'));
            return;
        }

        $action = $_POST['action'] ?? '';

        // Dependency impact analysis: block disable if other enabled modules depend on this one
        if ($action === 'disable') {
            $dependents = ModuleUninstallService::getDependentModules($name);
            // Filter to only currently enabled dependents
            $enabledDependents = array_filter($dependents, fn ($dep) => isModuleEnabled($dep));
            if (!empty($enabledDependents)) {
                $depList = implode(', ', $enabledDependents);
                $msg = t('admin.modules.flash_cannot_disable_deps', ['name' => $name, 'deps' => $depList]);
                if ($this->isHtmxRequest()) {
                    header('HX-Trigger: ' . json_encode(['notify' => ['message' => $msg, 'type' => 'warning']]));
                    http_response_code(409);
                    return;
                }
                flash_error($msg);
                $this->redirect(route('admin.modules.index'));
                return;
            }
        }

        $userId = $_SESSION['user_id'] ?? null;

        $row     = $this->moduleService->findStateByName($name);
        $enabled = (int) ($row['enabled'] ?? 1);
        $testing = (int) ($row['testing'] ?? 0);

        if ($action === 'enable') {
            $enabled = 1;
        }
        if ($action === 'disable') {
            $enabled = 0;
        }
        if ($action === 'testing-on') {
            $testing = 1;
        }
        if ($action === 'testing-off') {
            $testing = 0;
        }

        $this->moduleService->upsertState($name, $enabled, $testing, $userId ? (int) $userId : null);

        ModuleLoader::invalidateDbOverridesCache();

        $updatedMsg = t('admin.modules.flash_updated', ['name' => $name]);
        if ($this->isHtmxRequest()) {
            header('HX-Trigger: ' . json_encode(['notify' => ['message' => $updatedMsg, 'type' => 'success']]));
            http_response_code(204);
            return;
        }

        flash_success($updatedMsg);
        $this->redirect(route('admin.modules.index'));
    }

    public function importPermissions(string $name): void
    {
        if (!$this->isValidModuleName($name)) {
            http_response_code(404);
            return;
        }

        $declared = $this->moduleService->scanPermissions($name);
        $imported = $declared ? $this->moduleService->importPermissions($name, $declared) : 0;
        $total    = count($declared);
        flash_success(t('admin.modules.flash_perms_imported', ['imported' => $imported, 'total' => $total, 'name' => $name]));
        $this->redirect(route('admin.modules.index'));
    }

    // ── Export ─────────────────────────────────────────────────────

    public function export(string $name): void
    {
        if (!$this->isValidModuleName($name)) {
            http_response_code(404);
            return;
        }

        $includeData    = isset($_GET['data']) && $_GET['data'] === '1';
        $includeUploads = isset($_GET['uploads']) && $_GET['uploads'] === '1';

        try {
            $zipPath = ModuleExportService::build($name, $includeData, $includeUploads);
        } catch (\RuntimeException $e) {
            app_log('error', '[Admin] module export error: ' . $e->getMessage());
            flash_error(t('admin.modules.flash_export_failed'));
            $this->redirect(route('admin.modules.index'));
            return;
        }

        $suffix = '';
        if ($includeData) {
            $suffix .= '_data';
        }
        if ($includeUploads) {
            $suffix .= '_uploads';
        }
        $filename = $name . '_favilla_export' . $suffix . '_' . date('Ymd') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    // ── Import ─────────────────────────────────────────────────────

    public function importForm(): void
    {
        $this->render('Admin/Views/modules/import', [
            'pageTitle'   => t('admin.modules.import.page_title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.modules.breadcrumb'), 'route' => 'admin.modules.index'],
                ['label' => t('admin.modules.import.breadcrumb')],
            ],
            'result' => null,
        ]);
    }

    public function import(): void
    {
        if (empty($_FILES['module_zip']) || $_FILES['module_zip']['error'] !== UPLOAD_ERR_OK) {
            $errorMap = [
                UPLOAD_ERR_INI_SIZE   => t('admin.modules.import.err_ini_size'),
                UPLOAD_ERR_FORM_SIZE  => t('admin.modules.import.err_form_size'),
                UPLOAD_ERR_PARTIAL    => t('admin.modules.import.err_partial'),
                UPLOAD_ERR_NO_FILE    => t('admin.modules.import.err_no_file'),
                UPLOAD_ERR_NO_TMP_DIR => t('admin.modules.import.err_no_tmp_dir'),
                UPLOAD_ERR_CANT_WRITE => t('admin.modules.import.err_cant_write'),
            ];
            $code = $_FILES['module_zip']['error'] ?? UPLOAD_ERR_NO_FILE;
            flash_error($errorMap[$code] ?? t('admin.modules.import.err_unknown'));
            $this->redirect(route('admin.modules.import'));
            return;
        }

        $tmpPath = $_FILES['module_zip']['tmp_name'];
        $originalName = $_FILES['module_zip']['name'] ?? 'module.zip';

        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            flash_error(t('admin.modules.import.err_not_zip'));
            $this->redirect(route('admin.modules.import'));
            return;
        }

        $importData    = isset($_POST['import_data']) && $_POST['import_data'] === '1';
        $reuseExisting = isset($_POST['reuse_existing']) && $_POST['reuse_existing'] === '1';

        $dbOverride = trim($_POST['db_name_override'] ?? '');
        if ($dbOverride === '') {
            $dbOverride = null;
        } elseif (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $dbOverride)) {
            flash_error(t('admin.modules.import.err_invalid_db'));
            $this->redirect(route('admin.modules.import'));
            return;
        }

        $result = ModuleImportService::import($tmpPath, $importData, $dbOverride, $reuseExisting);

        $this->render('Admin/Views/modules/import', [
            'pageTitle'   => t('admin.modules.import.page_title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.modules.breadcrumb'), 'route' => 'admin.modules.index'],
                ['label' => t('admin.modules.import.breadcrumb')],
            ],
            'result' => $result,
        ]);
    }

    // ── Uninstall ─────────────────────────────────────────────────

    public function uninstallConfirm(string $name): void
    {
        if (!$this->isValidModuleName($name)) {
            http_response_code(404);
            return;
        }

        $preview = ModuleUninstallService::preview($name);

        $this->render('Admin/Views/modules/uninstall_confirm', [
            'pageTitle'   => t('admin.modules.uninstall.page_title', ['name' => $name]),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.modules.breadcrumb'), 'route' => 'admin.modules.index'],
                ['label' => t('admin.modules.uninstall.breadcrumb')],
            ],
            'preview' => $preview,
        ]);
    }

    public function uninstallDo(string $name): void
    {
        if (!$this->isValidModuleName($name)) {
            http_response_code(404);
            return;
        }

        $confirmName = trim($_POST['confirm_name'] ?? '');
        if ($confirmName !== $name) {
            flash_error(t('admin.modules.uninstall.flash_name_mismatch', ['name' => $name]));
            $this->redirect(route('admin.modules.uninstall', ['name' => $name]));
            return;
        }

        $dropTables    = isset($_POST['drop_tables']) && $_POST['drop_tables'] === '1';
        $deleteUploads = isset($_POST['delete_uploads']) && $_POST['delete_uploads'] === '1';

        $result = ModuleUninstallService::uninstall($name, $dropTables, $deleteUploads);

        if ($result->success) {
            $msg = t('admin.modules.uninstall.flash_done', ['name' => $name]);
            if (!empty($result->warnings)) {
                $msg .= t('admin.modules.uninstall.warnings_prefix') . implode(' ', $result->warnings);
            }
            flash_success($msg);
        } else {
            flash_error(t('admin.modules.uninstall.flash_failed', ['error' => $result->error]));
        }

        $this->redirect(route('admin.modules.index'));
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function isValidModuleName(string $name): bool
    {
        $this->cachedModuleList ??= $this->moduleService->getModulesWithStatus();
        foreach ($this->cachedModuleList as $module) {
            if ($module['name'] === $name) {
                return true;
            }
        }
        return false;
    }
}
