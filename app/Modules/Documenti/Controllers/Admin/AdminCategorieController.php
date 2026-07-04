<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\CategoryTreeService;
use App\Traits\ControllerHelpers;

class AdminCategorieController extends Controller
{
    use ControllerHelpers;

    private CategoryTreeService $treeService;

    public function __construct()
    {
        $this->treeService = app(CategoryTreeService::class);
    }

    public function index(): void
    {
        $user = auth();

        $this->render('Documenti/Views/admin/categorie', [
            'title'     => t('documenti.admin.categorie_title'),
            'categorie' => $this->treeService->treeCompleto(),
            'flat'      => $this->treeService->flat(),
            'user'      => $user,
        ]);
    }
}
