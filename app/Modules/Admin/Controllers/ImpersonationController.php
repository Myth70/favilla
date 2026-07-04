<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\ImpersonationService;
use App\Traits\ControllerHelpers;

class ImpersonationController extends Controller
{
    use ControllerHelpers;

    private ImpersonationService $service;

    public function __construct()
    {
        $this->service = app(ImpersonationService::class);
    }

    /**
     * Avvia impersonazione di un utente.
     * POST /admin/users/{id}/impersonate
     */
    public function start(string $id): void
    {
        $id = (int) $id;
        $adminId = (int) ($_SESSION['user_id'] ?? 0);

        $check = $this->service->canImpersonate($adminId, $id, $_SESSION);
        if (!$check['ok']) {
            flash_error($check['error']);
            $this->redirect(route('admin.users.show', ['id' => $id]));
            return;
        }

        $adminSessionBackup = $_SESSION;
        $payload = $this->service->start($adminId, $id, $adminSessionBackup);

        foreach ($payload['session_payload'] as $key => $value) {
            $_SESSION[$key] = $value;
        }

        $cookie = $payload['cookie'];
        setcookie($cookie['name'], $cookie['value'], $cookie['options']);

        flash_success(t('admin.impersonation.now_as', ['name' => $_SESSION['user_name'] ?? '']));
        $this->redirect(route('home'));
    }

    /**
     * Termina impersonazione e ripristina sessione admin.
     * POST /admin/impersonate/revert
     */
    public function revert(): void
    {
        $payload = $this->service->revert($_SESSION);
        if ($payload === null) {
            flash_error(t('admin.impersonation.no_active'));
            $this->redirect(route('home'));
            return;
        }

        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }

        foreach ($payload['session_replace'] as $key => $value) {
            $_SESSION[$key] = $value;
        }

        $cookie = $payload['cookie'];
        setcookie($cookie['name'], $cookie['value'], $cookie['options']);

        flash_success(t('admin.impersonation.back'));
        $this->redirect(route('admin.dashboard'));
    }
}
