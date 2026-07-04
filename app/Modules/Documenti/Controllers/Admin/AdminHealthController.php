<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentiHealthService;
use App\Traits\ControllerHelpers;

class AdminHealthController extends Controller
{
    use ControllerHelpers;

    private DocumentiHealthService $healthSvc;

    public function __construct()
    {
        $this->healthSvc = app(DocumentiHealthService::class);
    }

    public function index(): void
    {
        $user = auth();

        $this->render('Documenti/Views/admin/health', [
            'title'  => t('documenti.admin.health_title'),
            'checks' => $this->healthSvc->runChecks(),
            'user'   => $user,
        ]);
    }
}
