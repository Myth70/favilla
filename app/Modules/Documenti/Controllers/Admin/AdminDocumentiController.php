<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Services\DocumentiAdminService;
use App\Traits\ControllerHelpers;

class AdminDocumentiController extends Controller
{
    use ControllerHelpers;

    private DocumentiAdminService $adminSvc;

    public function __construct()
    {
        $this->adminSvc = app(DocumentiAdminService::class);
    }

    public function elenco(): void
    {
        $user    = auth();
        $clean   = $this->cleanGet(['q', 'stato', 'categoria_id', 'sort', 'dir', 'page']);
        $stati   = isset($_GET['stato']) ? (array) $_GET['stato'] : [];
        $filters = [
            'q'            => $clean['q']            ?? '',
            'categoria_id' => $clean['categoria_id'] ?? '',
            'stato'        => StatoHelper::filterStates($stati),
            'sort'         => $clean['sort']          ?? 'created_at',
            'dir'          => $clean['dir']           ?? 'DESC',
            'page'         => max(1, (int) ($clean['page'] ?? 1)),
        ];

        $data = $this->adminSvc->elencoAdmin($filters);

        $this->render('Documenti/Views/admin/documenti', [
            'title'     => t('documenti.admin.elenco_title'),
            'result'    => $data['result'],
            'filters'   => $filters,
            'categorie' => $data['categorie'],
            'users'     => $data['users'],
            'user'      => $user,
        ]);
    }

    public function riassegnaOwner(string $id): void
    {
        $id = (int) $id;
        $nuovoOwnerId = (int) ($_POST['owner_user_id'] ?? 0);

        try {
            $this->adminSvc->riassegnaOwner($id, $nuovoOwnerId, (int) (auth()['id'] ?? 0));
            flash_success(t('documenti.admin.owner_riassegnato'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.elenco'));
    }
}
