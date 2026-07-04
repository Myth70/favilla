<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers\Admin;

use App\Core\Controller;
use App\Modules\Documenti\Services\DocumentiAdminService;
use App\Traits\ControllerHelpers;

class AdminTrashController extends Controller
{
    use ControllerHelpers;

    private DocumentiAdminService $adminSvc;

    public function __construct()
    {
        $this->adminSvc = app(DocumentiAdminService::class);
    }

    public function index(): void
    {
        $user  = auth();
        $clean = $this->cleanGet(['q', 'page']);
        $page  = max(1, (int) ($clean['page'] ?? 1));
        $q     = (string) ($clean['q'] ?? '');

        $data = $this->adminSvc->cestino($q, $page);

        $this->render('Documenti/Views/admin/trash', [
            'title'   => t('documenti.admin.trash_title'),
            'items'   => $data['items'],
            'total'   => $data['total'],
            'page'    => $data['page'],
            'perPage' => $data['perPage'],
            'q'       => $q,
            'user'    => $user,
        ]);
    }

    /**
     * Ripristina un documento dal cestino. Audit log via DocumentoRepository ($auditable=true).
     */
    public function restore(string $id): void
    {
        $id = (int) $id;
        try {
            $this->adminSvc->ripristinaDalCestino($id, (int) (auth()['id'] ?? 0));
            flash_success(t('documenti.admin.documento_ripristinato'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.trash'));
    }

    /**
     * Cancella definitivamente il documento, le versioni e i file fisici.
     */
    public function purge(string $id): void
    {
        $id = (int) $id;
        try {
            $this->adminSvc->purgaDefinitivo($id);
            flash_success(t('documenti.admin.documento_eliminato_definitivamente'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.admin.trash'));
    }
}
