<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Traits\ControllerHelpers;

class DevSimulatorController extends Controller
{
    use ControllerHelpers;

    public function index(): void
    {
        $this->render('Admin/Views/dev_simulator', [
            'pageTitle'   => t('admin.dev_simulator.page_title'),
            'breadcrumbs' => [
                ['label' => 'Admin',       'route' => 'admin.dashboard'],
                ['label' => t('admin.dev_simulator.breadcrumb')],
            ],
        ]);
    }

    public function errorPreview(string $code): void
    {
        $allowed = ['403', '404', '405', '500', 'maintenance'];
        if (!in_array($code, $allowed, true)) {
            header('Location: ' . route('admin.dev.simulator'));
            exit;
        }
        $file = BASE_PATH . '/app/Views/errors/' . $code . '.php';
        if (file_exists($file)) {
            include $file;
        }
        exit;
    }
}
