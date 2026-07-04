<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Services\CategoryTreeService;
use App\Modules\Documenti\Services\DocumentoService;
use App\Traits\ControllerHelpers;

class DocumentiController extends Controller
{
    use ControllerHelpers;

    private DocumentoService   $service;
    private CategoryTreeService $tree;

    public function __construct()
    {
        $this->service = app(DocumentoService::class);
        $this->tree    = app(CategoryTreeService::class);
    }

    public function index(): void
    {
        $user    = auth();
        $userId  = (int) $user['id'];
        $clean   = $this->cleanGet(['q', 'categoria_id', 'scadenza', 'sort', 'dir', 'page']);
        $stati   = isset($_GET['stato']) ? (array) $_GET['stato'] : [];
        $filters = [
            'q'            => $clean['q']            ?? '',
            'categoria_id' => $clean['categoria_id'] ?? '',
            'stato'        => StatoHelper::filterStates($stati),
            'scadenza'     => $clean['scadenza']      ?? '',
            'sort'         => $clean['sort']          ?? 'created_at',
            'dir'          => $clean['dir']           ?? 'DESC',
            'page'         => max(1, (int) ($clean['page'] ?? 1)),
        ];

        $result    = $this->service->listPaginated($filters, $userId);
        $categorie = $this->tree->alberoOrdinato();

        $this->render('Documenti/Views/index', [
            'title'     => t('documenti.title'),
            'result'    => $result,
            'filters'   => $filters,
            'categorie' => $categorie,
            'user'      => $user,
        ]);
    }

    public function create(): void
    {
        $user      = auth();
        $categorie = $this->tree->alberoOrdinato();

        $errors = $_SESSION['_errors'] ?? [];
        $old    = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Documenti/Views/create', [
            'title'     => t('documenti.create.title'),
            'categorie' => $categorie,
            'user'      => $user,
            'errors'    => $errors,
            'old'       => $old,
        ]);
    }

    public function store(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];

        $clean = $this->cleanPost(['titolo', 'descrizione', 'categoria_id', 'scade_il', 'tag', 'approvazione_richiesta']);
        $clean['reminder_giorni'] = $this->parseReminderGiorni((string) ($_POST['reminder_giorni'] ?? ''));

        $v  = new Validator();
        $ok = $v->validate($clean, [
            'titolo'       => 'required|max:500',
            'categoria_id' => 'required',
        ]);

        if (!$ok) {
            $this->flashErrors($v->errors(), $clean, 'documenti.create');
            return;
        }

        try {
            $docId = $this->service->create($clean, $_FILES['file'] ?? null, $userId);
        } catch (\Throwable $e) {
            $this->flashErrors(['_general' => $e->getMessage()], $clean, 'documenti.create');
            return;
        }

        flash_success(t('documenti.flash.documento_creato'));
        $this->redirect(route('documenti.show', ['id' => $docId]));
    }

    public function show(string $id): void
    {
        $id     = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        $bundle = $this->service->dettaglioVisibile($id, $userId);
        if (!$bundle) {
            http_response_code(404);
            $this->render('errors/404', ['message' => t('documenti.exception.documento_non_trovato_o_accesso_negato')]);
            return;
        }

        $this->render('Documenti/Views/show', [
            'title'        => $bundle['doc']['titolo'],
            'doc'          => $bundle['doc'],
            'versioni'     => $bundle['versioni'],
            'collegamenti' => $bundle['collegamenti'],
            'approvazioni' => $bundle['approvazioni'],
            'categoria'    => $bundle['categoria'],
            'user'         => $user,
        ]);
    }

    public function edit(string $id): void
    {
        $id     = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        $doc = $this->service->findVisible($id, $userId);
        if (!$doc) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }
        if (!has_permission('documenti.admin') && (int) $doc['owner_user_id'] !== $userId) {
            http_response_code(403);
            $this->render('errors/403', []);
            return;
        }

        $errors = $_SESSION['_errors'] ?? [];
        $old    = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);

        $this->render('Documenti/Views/edit', [
            'title'     => t('documenti.edit.title'),
            'doc'       => $doc,
            'categorie' => $this->tree->alberoOrdinato(),
            'user'      => $user,
            'errors'    => $errors,
            'old'       => $old,
        ]);
    }

    public function update(string $id): void
    {
        $id     = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        $doc = $this->service->findVisible($id, $userId);
        if (!$doc) {
            http_response_code(404);
            $this->render('errors/404', []);
            return;
        }
        if (!has_permission('documenti.admin') && (int) $doc['owner_user_id'] !== $userId) {
            http_response_code(403);
            $this->render('errors/403', []);
            return;
        }

        $clean = $this->cleanPost(['titolo', 'descrizione', 'scade_il', 'tag', 'approvazione_richiesta']);
        $clean['reminder_giorni'] = $this->parseReminderGiorni((string) ($_POST['reminder_giorni'] ?? ''));

        $v  = new Validator();
        $ok = $v->validate($clean, ['titolo' => 'required|max:500']);

        if (!$ok) {
            $this->flashErrors($v->errors(), $clean, 'documenti.edit', ['id' => $id]);
            return;
        }

        try {
            $this->service->update($id, $clean, $userId);
        } catch (\Throwable $e) {
            $this->flashErrors(['_general' => $e->getMessage()], $clean, 'documenti.edit', ['id' => $id]);
            return;
        }

        flash_success(t('documenti.flash.documento_aggiornato'));
        $this->redirect(route('documenti.show', ['id' => $id]));
    }

    public function destroy(string $id): void
    {
        $id     = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        try {
            $this->service->destroy($id, $userId);
            flash_success(t('documenti.flash.documento_eliminato'));
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }

        $this->redirect(route('documenti.index'));
    }

    public function inbox(): void
    {
        $user   = auth();
        $result = $this->service->inboxFor();

        $this->render('Documenti/Views/approvazioni_inbox', [
            'title'  => t('documenti.inbox.title'),
            'result' => $result,
            'user'   => $user,
        ]);
    }

    public function scadenze(): void
    {
        $user    = auth();
        $filters = ['scadenza' => 'prossimi_30', 'sort' => 'scade_il', 'dir' => 'ASC', 'page' => 1];
        $result  = $this->service->listPaginated($filters, (int) $user['id']);

        $this->render('Documenti/Views/scadenze', [
            'title'  => t('documenti.scadenze.title'),
            'result' => $result,
            'user'   => $user,
        ]);
    }

    public function tree(): void
    {
        $categorie = $this->tree->alberoOrdinato();
        $this->renderPartial('Documenti/Views/partials/albero_categorie', ['categorie' => $categorie]);
    }

    /**
     * Parse e valida reminder_giorni: CSV → array di interi 1..365, dedup, ordinato decrescente.
     * Range guardrail: blocca valori negativi, zero, o oltre l'anno.
     *
     * @return array<int>
     */
    private function parseReminderGiorni(string $csv): array
    {
        $values = array_filter(
            array_map(static fn ($v) => (int) trim((string) $v), explode(',', $csv)),
            static fn (int $v) => $v >= 1 && $v <= 365
        );
        $values = array_values(array_unique($values));
        rsort($values);
        return $values;
    }
}
