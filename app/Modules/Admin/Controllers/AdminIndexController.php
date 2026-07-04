<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Modules\Admin\Services\AdminIndexService;

class AdminIndexController extends Controller
{
    private AdminIndexService $service;

    public function __construct()
    {
        $this->service = app(AdminIndexService::class);
    }

    public function index(): void
    {
        $user = auth() ?? [];
        $catalog = $this->service->getCatalog(
            (array) ($user['permissions'] ?? []),
            (array) ($user['roles'] ?? []),
        );

        $this->render('Admin/Views/index', [
            'pageTitle'   => t('admin.index.page_title'),
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.index'],
                ['label' => t('admin.index.title')],
            ],
            'sections'    => $catalog['sections'],
            'summary'     => $catalog['summary'],
        ]);
    }
}
