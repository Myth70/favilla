<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\MailAdminService;
use App\Services\MailService;
use App\Traits\ControllerHelpers;

class MailController extends Controller
{
    use ControllerHelpers;

    private MailAdminService $service;

    public function __construct()
    {
        $this->service = app(MailAdminService::class);
    }

    // ---------------------------------------------------------------
    // TEMPLATES
    // ---------------------------------------------------------------

    /**
     * HTMX partial: mail panel for embedding in the Configuration tab.
     */
    public function panel(): void
    {
        $this->renderPartial('Admin/Views/mail/partials/panel', [
            'templates' => $this->service->allTemplates(),
            'stats'     => $this->service->getLogStats(),
        ]);
    }

    public function index(): void
    {
        $this->render('Admin/Views/mail/index', [
            'pageTitle'   => t('admin.mail.title'),
            'templates'   => $this->service->allTemplates(),
            'stats'       => $this->service->getLogStats(),
            'activeTab'   => 'templates',
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.mail.breadcrumb')],
            ],
        ]);
    }

    public function templatesTable(): void
    {
        $this->renderPartial('Admin/Views/mail/partials/templates_table', [
            'templates' => $this->service->allTemplates(),
        ]);
    }

    public function create(): void
    {
        $this->render('Admin/Views/mail/partials/template_form', [
            'pageTitle'   => t('admin.mail.tpl.new_page_title'),
            'template'    => null,
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.mail.breadcrumb'), 'route' => 'admin.mail.index'],
                ['label' => t('admin.mail.tpl.bc_new')],
            ],
        ]);
    }

    public function store(): void
    {
        $data              = $this->cleanPost(['slug', 'name', 'subject', 'variables']);
        $data['body_html'] = trim($_POST['body_html'] ?? '');

        $errors = $this->service->validateTemplate($data);
        if ($errors) {
            $this->flashErrors($errors, $_POST, 'admin.mail.templates.create');
            return;
        }

        $this->service->createTemplate($data);

        flash_success(t('admin.mail.flash_created'));
        $this->redirect(route('admin.mail.index'));
    }

    public function edit(string $id): void
    {
        $id = (int) $id;
        $template = $this->service->findTemplate($id);
        if (!$template) {
            flash_error(t('admin.mail.flash_not_found'));
            $this->redirect(route('admin.mail.index'));
            return;
        }

        $this->render('Admin/Views/mail/partials/template_form', [
            'pageTitle'   => t('admin.mail.tpl.edit_page_title'),
            'template'    => $template,
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.mail.breadcrumb'), 'route' => 'admin.mail.index'],
                ['label' => t('admin.mail.tpl.bc_edit')],
            ],
        ]);
    }

    public function update(string $id): void
    {
        $id = (int) $id;
        $template = $this->service->findTemplate($id);
        if (!$template) {
            flash_error(t('admin.mail.flash_not_found'));
            $this->redirect(route('admin.mail.index'));
            return;
        }

        $data              = $this->cleanPost(['slug', 'name', 'subject', 'variables']);
        $data['body_html'] = trim($_POST['body_html'] ?? '');

        $errors = $this->service->validateTemplate($data, $id);
        if ($errors) {
            $this->flashErrors($errors, $_POST, 'admin.mail.templates.edit', ['id' => $id]);
            return;
        }

        $this->service->updateTemplate($id, $data);

        flash_success(t('admin.mail.flash_updated'));
        $this->redirect(route('admin.mail.index'));
    }

    public function destroy(string $id): void
    {
        $id = (int) $id;
        $template = $this->service->findTemplate($id);
        if (!$template) {
            flash_error(t('admin.mail.flash_not_found'));
            $this->redirect(route('admin.mail.index'));
            return;
        }

        $this->service->deleteTemplate($id);

        if ($this->isHtmxRequest()) {
            $this->hxToast(t('admin.mail.flash_deleted'));
            $this->templatesTable();
            return;
        }

        flash_success(t('admin.mail.flash_deleted'));
        $this->redirect(route('admin.mail.index'));
    }

    // ---------------------------------------------------------------
    // LOG
    // ---------------------------------------------------------------

    public function log(): void
    {
        $filters = $this->cleanGet(['q', 'status', 'sort', 'dir'], 255);
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->service->getLogsPaginated($page, 20, $filters);
        $stats  = $this->service->getLogStats();

        $data = [
            'pageTitle'   => t('admin.mail.log_page_title'),
            'logs'        => $result['data'],
            'total'       => $result['total'],
            'page'        => $result['page'],
            'pages'       => $result['lastPage'],
            'perPage'     => $result['perPage'],
            'filters'     => $filters,
            'stats'       => $stats,
            'activeTab'   => 'log',
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => t('admin.mail.breadcrumb'), 'route' => 'admin.mail.index'],
                ['label' => t('admin.mail.bc_log')],
            ],
        ];

        $this->htmxOrRender(
            'Admin/Views/mail/partials/log_table',
            'Admin/Views/mail/index',
            $data
        );
    }

    public function logTable(): void
    {
        $filters = $this->cleanGet(['q', 'status', 'sort', 'dir'], 255);
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = $this->service->getLogsPaginated($page, 20, $filters);

        $this->renderPartial('Admin/Views/mail/partials/log_table', [
            'logs'    => $result['data'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'pages'   => $result['lastPage'],
            'perPage' => $result['perPage'],
            'filters' => $filters,
        ]);
    }

    // ---------------------------------------------------------------
    // TEST SEND
    // ---------------------------------------------------------------

    public function sendTest(): void
    {
        $to = trim($_POST['test_email'] ?? '');

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('admin.mail.flash_invalid_email'), 'danger');
                return;
            }
            flash_error(t('admin.mail.flash_invalid_email'));
            $this->redirect(route('admin.mail.index'));
            return;
        }

        $mailService = app(MailService::class);
        $success     = $mailService->sendTest($to);

        $msg  = $success ? t('admin.mail.flash_test_sent', ['to' => $to]) : t('admin.mail.flash_test_error');
        $type = $success ? 'success' : 'danger';

        if ($this->isHtmxRequest()) {
            $this->hxToast($msg, $type);
            return;
        }

        $_SESSION[$success ? '_flash_success' : '_flash_error'] = $msg;
        $this->redirect(route('admin.mail.index'));
    }
}
