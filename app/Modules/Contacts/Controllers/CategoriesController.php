<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Modules\Contacts\Services\ContactsService;
use App\Traits\ControllerHelpers;

class CategoriesController extends Controller
{
    use ControllerHelpers;

    private ContactsService $service;

    public function __construct()
    {
        $this->service = app(ContactsService::class);
    }

    public function index(): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $categorie = $this->service->getCategorie($userId);

        $this->render('Contacts/Views/categories', [
            'pageTitle'   => 'Categorie Contatti',
            'categorie'   => $categorie,
            'breadcrumbs' => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Categorie'],
            ],
        ]);
    }

    public function store(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $clean  = $this->cleanPost(['nome', 'colore']);
        $nome   = $clean['nome'] ?? '';
        $colore = $clean['colore'] ?: '#6c757d';

        if ($nome === '') {
            $this->json(['error' => 'Il nome è obbligatorio.'], 422);
            return;
        }
        if (!$this->isValidHexColor($colore)) {
            $this->json(['error' => 'Formato colore non valido.'], 422);
            return;
        }

        $this->service->createCategoria(['nome' => $nome, 'colore' => $colore], $userId);
        $this->hxToast('Categoria creata.', 'success', ['source' => 'contatti-categorie']);
        $categorie = $this->service->getCategorie($userId);

        $this->renderPartial('Contacts/Views/partials/categories_list', compact('categorie'));
    }

    public function update(string $cid): void
    {
        $userId = (int) $_SESSION['user_id'];
        $clean  = $this->cleanPost(['nome', 'colore']);
        $nome   = $clean['nome'] ?? '';
        $colore = $clean['colore'] ?: '#6c757d';

        if ($nome === '') {
            $this->json(['error' => 'Il nome è obbligatorio.'], 422);
            return;
        }
        if (!$this->isValidHexColor($colore)) {
            $this->json(['error' => 'Formato colore non valido.'], 422);
            return;
        }

        $this->service->updateCategoria((int) $cid, ['nome' => $nome, 'colore' => $colore], $userId);
        $this->hxToast('Categoria aggiornata.', 'success', ['source' => 'contatti-categorie']);
        $categorie = $this->service->getCategorie($userId);

        $this->renderPartial('Contacts/Views/partials/categories_list', compact('categorie'));
    }

    public function destroy(string $cid): void
    {
        $userId = (int) $_SESSION['user_id'];
        $this->service->deleteCategoria((int) $cid, $userId);

        $this->hxToast('Categoria eliminata.', 'warning', ['source' => 'contatti-categorie']);
        $categorie = $this->service->getCategorie($userId);
        $this->renderPartial('Contacts/Views/partials/categories_list', compact('categorie'));
    }

    private function isValidHexColor(string $value): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $value);
    }
}
