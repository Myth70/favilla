<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Documenti\Services\CategoryTreeService;
use App\Traits\ControllerHelpers;

class CategorieController extends Controller
{
    use ControllerHelpers;

    private CategoryTreeService $service;

    public function __construct()
    {
        $this->service = app(CategoryTreeService::class);
    }

    private function buildTree(): array
    {
        return $this->service->treeCompleto();
    }

    public function index(): void
    {
        $user   = auth();
        $errors = $_SESSION['_errors'] ?? [];
        $old    = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Documenti/Views/categorie', [
            'title'     => t('documenti.categorie.title'),
            'categorie' => $this->buildTree(),
            'user'      => $user,
            'errors'    => $errors,
            'old'       => $old,
        ]);
    }

    public function store(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];

        $clean = $this->cleanPost(['nome', 'descrizione', 'parent_id', 'codice', 'reminder_giorni_default']);

        $v  = new Validator();
        $ok = $v->validate($clean, [
            'nome'   => 'required|max:255',
            'codice' => 'required|max:20',
        ]);

        if (!$ok) {
            $this->flashErrors($v->errors(), $clean, 'documenti.categorie.index');
            return;
        }

        try {
            $data = [
                'nome'                   => $clean['nome'],
                'descrizione'            => $clean['descrizione'] ?: null,
                'parent_id'              => !empty($clean['parent_id']) ? (int) $clean['parent_id'] : null,
                'codice'                 => strtoupper(trim($clean['codice'])),
                'reminder_giorni_default' => $clean['reminder_giorni_default'] ?: null,
            ];
            $this->service->create($data, $userId);
            flash_success(t('documenti.flash.categoria_creata'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.categorie.index'));
    }

    public function update(string $id): void
    {
        $id     = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        $clean = $this->cleanPost(['nome', 'descrizione', 'codice', 'reminder_giorni_default']);

        $v  = new Validator();
        $ok = $v->validate($clean, ['nome' => 'required|max:255']);

        if (!$ok) {
            flash_error(implode(' ', array_merge(...array_values($v->errors()))));
            $this->redirect(route('documenti.categorie.index'));
            return;
        }

        try {
            $this->service->aggiorna($id, $clean, $userId);
            flash_success(t('documenti.flash.categoria_aggiornata'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.categorie.index'));
    }

    public function sposta(string $id): void
    {
        $id          = (int) $id;
        $user        = auth();
        $userId      = (int) $user['id'];
        $newParentId = !empty($_POST['new_parent_id']) ? (int) $_POST['new_parent_id'] : null;

        try {
            $this->service->sposta($id, $newParentId, $userId);
            if ($this->isHtmxRequest()) {
                $this->hxToast(t('documenti.flash.categoria_spostata'), 'success');
                $this->renderPartial('Documenti/Views/partials/albero_categorie', [
                    'categorie' => $this->buildTree(),
                ]);
                return;
            }
            flash_success(t('documenti.flash.categoria_spostata'));
        } catch (\Throwable $e) {
            if ($this->isHtmxRequest()) {
                http_response_code(422);
                $this->hxToast($e->getMessage(), 'danger');
                return;
            }
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.categorie.index'));
    }

    public function destroy(string $id): void
    {
        $id = (int) $id;
        try {
            $this->service->elimina($id);
            flash_success(t('documenti.flash.categoria_eliminata'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.categorie.index'));
    }

    /**
     * POST /documenti/categorie/quick — Quick-create for inline use in forms.
     * Returns JSON {success, id, nome} or {success, error}.
     */
    public function quickStore(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $user   = auth();
        $userId = (int) $user['id'];

        $clean = $this->cleanPost(['nome', 'descrizione', 'parent_id', 'codice']);

        $v  = new Validator();
        $ok = $v->validate($clean, [
            'nome'   => 'required|max:255',
            'codice' => 'required|max:20',
        ]);

        if (!$ok) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => implode(' ', array_merge(...array_values($v->errors())))]);
            return;
        }

        try {
            $data = [
                'nome'      => $clean['nome'],
                'descrizione' => $clean['descrizione'] ?: null,
                'parent_id' => !empty($clean['parent_id']) ? (int) $clean['parent_id'] : null,
                'codice'    => strtoupper(trim($clean['codice'])),
            ];
            $id = $this->service->create($data, $userId);
            echo json_encode(['success' => true, 'id' => $id, 'nome' => $clean['nome']]);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
