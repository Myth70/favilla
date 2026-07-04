<?php

declare(strict_types=1);

namespace App\Modules\Scheduler\Controllers;

use App\Core\Controller;
use App\Modules\Scheduler\Services\SchedulerService;
use App\Traits\ControllerHelpers;

class SchedulerController extends Controller
{
    use ControllerHelpers;

    private SchedulerService $service;

    public function __construct()
    {
        $this->service = app(SchedulerService::class);
    }

    /**
     * Panoramica job e log recente.
     */
    public function index(): void
    {
        $jobs      = $this->service->getJobs();
        $recentLog = $this->service->getRecentLog(30);

        $this->render('Scheduler/Views/index', [
            'pageTitle'   => t('scheduler.title'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'admin.dashboard', 'Admin'), 'route' => 'admin.dashboard'],
                ['label' => t('scheduler.title')],
            ],
            'jobs'        => $jobs,
            'recentLog'   => $recentLog,
        ]);
    }

    /**
     * Form creazione nuovo job.
     */
    public function create(): void
    {
        $this->render('Scheduler/Views/form', [
            'pageTitle'   => t('scheduler.new_job'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'admin.dashboard', 'Admin'), 'route' => 'admin.dashboard'],
                ['label' => t('scheduler.title'), 'route' => 'scheduler.index'],
                ['label' => t('scheduler.new_job')],
            ],
            'job'             => null,
            'isEdit'          => false,
            'allowedCommands' => $this->service->getAllowedCommandsList(),
        ]);
    }

    /**
     * Salva un nuovo job.
     */
    public function store(): void
    {
        $data = $this->cleanPost(['name', 'slug', 'command', 'interval_minutes']);
        $data['args_json'] = (string) ($_POST['args_raw'] ?? '');
        $data['enabled']   = isset($_POST['enabled']) ? 1 : 0;

        try {
            $this->service->createJob($data);
            flash_success(t('scheduler.flash.job_created'));
            header('Location: ' . route('scheduler.index'));
            exit;
        } catch (\InvalidArgumentException $e) {
            flash_error($e->getMessage());
            $_SESSION['_old']         = $_POST;
            header('Location: ' . route('scheduler.create'));
            exit;
        }
    }

    /**
     * Form modifica job esistente.
     */
    public function edit(int $id): void
    {
        $job = $this->service->getJob($id);
        if (!$job) {
            flash_error(t('scheduler.flash.job_not_found'));
            header('Location: ' . route('scheduler.index'));
            exit;
        }

        $this->render('Scheduler/Views/form', [
            'pageTitle'   => t('scheduler.edit_job'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'admin.dashboard', 'Admin'), 'route' => 'admin.dashboard'],
                ['label' => t('scheduler.title'), 'route' => 'scheduler.index'],
                ['label' => t('scheduler.edit_prefix', ['name' => $job['name']])],
            ],
            'job'             => $job,
            'isEdit'          => true,
            'allowedCommands' => $this->service->getAllowedCommandsList(),
        ]);
    }

    /**
     * Aggiorna un job esistente.
     */
    public function update(int $id): void
    {
        $data = $this->cleanPost(['name', 'slug', 'command', 'interval_minutes']);
        $data['args_json'] = (string) ($_POST['args_raw'] ?? '');
        $data['enabled']   = isset($_POST['enabled']) ? 1 : 0;

        try {
            $this->service->updateJob($id, $data);
            flash_success(t('scheduler.flash.job_updated'));
            header('Location: ' . route('scheduler.index'));
            exit;
        } catch (\InvalidArgumentException $e) {
            flash_error($e->getMessage());
            $_SESSION['_old']         = $_POST;
            header('Location: ' . route('scheduler.edit', ['id' => $id]));
            exit;
        }
    }

    /**
     * Elimina un job.
     */
    public function destroy(int $id): void
    {
        $this->service->deleteJob($id);
        flash_success(t('scheduler.flash.job_deleted'));
        header('Location: ' . route('scheduler.index'));
        exit;
    }

    /**
     * Esegue un job manualmente in background (HTMX POST).
     * Ritorna subito con stato 'queued'; la tabella si aggiorna via polling.
     */
    public function runNow(int $id): void
    {
        $job = $this->service->getJob($id);
        $jobName = (string) ($job['display_name'] ?? $job['name'] ?? ('Job #' . $id));

        try {
            $result = $this->service->runNow($id);
            if ($result['status'] === 'queued') {
                $this->notifyCurrentUser(
                    t('scheduler.notif.started_title', ['name' => $jobName]),
                    t('scheduler.notif.started_body'),
                    'info',
                    route('scheduler.index'),
                    'fa-solid fa-clock-rotate-left'
                );
                $this->hxToast(t('scheduler.toast.queued'), 'info');
            } elseif ($result['status'] === 'success') {
                $this->notifyCurrentUser(
                    t('scheduler.notif.completed_title', ['name' => $jobName]),
                    t('scheduler.notif.completed_body'),
                    'success',
                    route('scheduler.index'),
                    'fa-solid fa-check'
                );
                $this->hxToast(t('scheduler.toast.success'), 'success');
            } else {
                $this->notifyCurrentUser(
                    t('scheduler.notif.warning_title', ['name' => $jobName]),
                    t('scheduler.notif.warning_body'),
                    'warning',
                    route('scheduler.index'),
                    'fa-solid fa-triangle-exclamation'
                );
                $this->hxToast(t('scheduler.toast.warning'), 'warning');
            }

            $this->hxSyncNotificationBadge();
        } catch (\RuntimeException $e) {
            app_log('error', '[Scheduler] runNow error: ' . $e->getMessage());
            $this->notifyCurrentUser(
                t('scheduler.notif.error_title', ['name' => $jobName]),
                t('scheduler.notif.error_body', ['error' => $e->getMessage()]),
                'danger',
                route('scheduler.index'),
                'fa-solid fa-circle-xmark'
            );
            $this->hxToast(t('scheduler.toast.error'), 'danger');
            $this->hxSyncNotificationBadge();
        }

        $jobs = $this->service->getJobs();
        $this->renderPartial('Scheduler/Views/partials/jobs-table', ['jobs' => $jobs]);
    }

    /**
     * Reimposta un job bloccato in stato 'running' (HTMX POST).
     */
    public function resetJob(int $id): void
    {
        $job = $this->service->getJob($id);
        $jobName = (string) ($job['display_name'] ?? $job['name'] ?? ('Job #' . $id));

        try {
            $this->service->resetJob($id);
            $this->notifyCurrentUser(
                t('scheduler.notif.reset_title', ['name' => $jobName]),
                t('scheduler.notif.reset_body'),
                'info',
                route('scheduler.index'),
                'fa-solid fa-arrows-rotate'
            );
            $this->hxToast(t('scheduler.toast.reset'), 'info');
            $this->hxSyncNotificationBadge();
        } catch (\RuntimeException $e) {
            app_log('error', '[Scheduler] resetJob error: ' . $e->getMessage());
            $this->notifyCurrentUser(
                t('scheduler.notif.reset_error_title', ['name' => $jobName]),
                t('scheduler.notif.reset_error_body', ['error' => $e->getMessage()]),
                'danger',
                route('scheduler.index'),
                'fa-solid fa-circle-xmark'
            );
            $this->hxToast(t('scheduler.toast.reset_error'), 'danger');
            $this->hxSyncNotificationBadge();
        }

        $jobs = $this->service->getJobs();
        $this->renderPartial('Scheduler/Views/partials/jobs-table', ['jobs' => $jobs]);
    }

    /**
     * Endpoint di polling — restituisce solo la tabella job (HTMX GET).
     */
    public function pollTable(): void
    {
        $jobs = $this->service->getJobs();
        $this->renderPartial('Scheduler/Views/partials/jobs-table', ['jobs' => $jobs]);
    }

    /**
     * Log di esecuzione per un singolo job (HTMX GET).
     */
    public function jobLog(int $id): void
    {
        $job = $this->service->getJob($id);
        if (!$job) {
            echo '<div class="p-3 text-muted">' . e(t('scheduler.flash.job_not_found')) . '</div>';
            return;
        }
        $log = $this->service->getLogForJob($id, 50);
        $this->renderPartial('Scheduler/Views/partials/job-log', [
            'job' => $job,
            'log' => $log,
        ]);
    }

    /**
     * Abilita o disabilita un job (POST HTMX).
     */
    public function toggle(): void
    {
        $data    = $this->cleanPost(['id', 'enabled']);
        $id      = (int) ($data['id'] ?? 0);
        $enabled = (bool) ($data['enabled'] ?? false);

        if ($id > 0) {
            $this->service->setEnabled($id, $enabled);
        }

        $jobs = $this->service->getJobs();
        $this->renderPartial('Scheduler/Views/partials/jobs-table', ['jobs' => $jobs]);
    }

    /**
     * Svuota log vecchi.
     */
    public function pruneLog(): void
    {
        $data = $this->cleanPost(['days']);
        $days = max(1, (int) ($data['days'] ?? 30));
        $deleted = $this->service->pruneLog($days);
        flash_success(t('scheduler.flash.log_pruned', ['deleted' => $deleted, 'days' => $days]));
        header('Location: ' . route('scheduler.index'));
        exit;
    }

    /**
     * Visualizza il contenuto di un file di log output del job.
     * Sicurezza: il filename è validato dal service; path traversal bloccato.
     */
    public function viewLogFile(string $filename): void
    {
        $path = $this->service->getOutputFilePath($filename);
        if (!$path) {
            http_response_code(404);
            echo e(t('scheduler.flash.log_file_not_found'));
            return;
        }

        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }
}
