<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentiAdminService;
use App\Modules\Documenti\Services\ReminderService;
use App\Traits\ControllerHelpers;

class AdminDashboardController extends Controller
{
    use ControllerHelpers;

    private DocumentiAdminService $adminSvc;
    private ReminderService       $reminderSvc;

    public function __construct()
    {
        $this->adminSvc    = app(DocumentiAdminService::class);
        $this->reminderSvc = app(ReminderService::class);
    }

    public function index(): void
    {
        $user = auth();

        $this->render('Documenti/Views/admin/dashboard', [
            'title' => t('documenti.admin.dashboard_title'),
            'stats' => $this->adminSvc->kpiPerStato(),
            'user'  => $user,
        ]);
    }

    public function runReminders(): void
    {
        try {
            $sent = $this->reminderSvc->sendDueReminders();
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('documenti.admin.reminder_inviati', ['count' => $sent]), 'success');
                return;
            }
            flash_success(t('documenti.admin.reminder_inviati', ['count' => $sent]));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(500);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.dashboard'));
    }

    public function runExpire(): void
    {
        try {
            $count = $this->adminSvc->scadiPubblicati((int) (auth()['id'] ?? 0));
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('documenti.admin.documenti_scaduti', ['count' => $count]), 'success');
                return;
            }
            flash_success(t('documenti.admin.documenti_scaduti', ['count' => $count]));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(500);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.dashboard'));
    }
}
