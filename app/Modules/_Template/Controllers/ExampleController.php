<?php

declare(strict_types=1);

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  CONTROLLER DI MODULO — Scheletro CRUD completo                ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * ISTRUZIONI:
 * 1. Rinomina la classe (es. ClientiController)
 * 2. Aggiorna il namespace al tuo modulo
 * 3. Aggiorna il Service e i path delle view
 *
 * PATTERN 3-LAYER:
 *   Controller → Service → Repository
 *
 * Il Controller si occupa SOLO di:
 * - Leggere input HTTP (GET, POST)
 * - Validazione form (required, formato)
 * - Chiamare il Service per la business logic
 * - Output: render/redirect/json/renderPartial
 *
 * Il Controller NON deve:
 * - Chiamare il Repository direttamente
 * - Contenere logica di business (unicità, regole dominio)
 * - Manipolare dati oltre la sanitizzazione
 */

namespace App\Modules\_Template\Controllers;

use App\Core\Controller;
use App\Modules\_Template\Services\ExampleService;
use App\Traits\ControllerHelpers;

class ExampleController extends Controller
{
    use ControllerHelpers;
    private ExampleService $service;

    public function __construct()
    {
        $this->service = app(ExampleService::class);
    }

    /**
     * Lista paginata con filtri e supporto HTMX.
     */
    public function index(): void
    {
        $filters = [
            'q'      => trim($_GET['q'] ?? ''),
            'status' => $_GET['status'] ?? '',
            'sort'   => $_GET['sort'] ?? 'created_at',
            'dir'    => $_GET['dir'] ?? 'desc',
            'page'   => (int) ($_GET['page'] ?? 1),
        ];

        $userId = (int) $_SESSION['user_id'];
        $result = $this->service->list($filters, $userId);
        $statusCounts = $this->service->statusCounts($userId);

        $viewData = [
            'items'        => $result['data'],
            'total'        => $result['total'],
            'pages'        => $result['lastPage'],
            'page'         => $result['page'],
            'filters'      => $filters,
            'statusCounts' => $statusCounts,
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('_Template/Views/partials/table', $viewData);
            return;
        }

        $this->render('_Template/Views/index', array_merge($viewData, [
            'pageTitle'   => t('example.title'),
            'breadcrumbs' => [
                ['label' => t('example.title')],
            ],
        ]));
    }

    /**
     * Dettaglio singolo record.
     */
    public function show(string $id): void
    {
        // findWithAuthor() è owner-scoped: un ID altrui restituisce null (no IDOR).
        $item = $this->service->findWithAuthor((int) $id, (int) $_SESSION['user_id']);

        if (!$item) {
            flash_error(t('example.flash.not_found'));
            $this->redirect(route('example.index'));
            return;
        }

        $this->render('_Template/Views/show', [
            'pageTitle'   => (string) $item['name'],
            'item'        => $item,
            'breadcrumbs' => [
                ['label' => t('example.title'), 'route' => 'example.index'],
                ['label' => (string) $item['name']],
            ],
        ]);
    }

    /**
     * Form di creazione — GET.
     */
    public function create(): void
    {
        $this->render('_Template/Views/form', [
            'pageTitle'   => t('example.new_page_title'),
            'item'        => null,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old'] ?? [],
            'breadcrumbs' => [
                ['label' => t('example.title'), 'route' => 'example.index'],
                ['label' => t('example.breadcrumb_new')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    /**
     * Salvataggio — POST con validazione.
     */
    public function store(): void
    {
        $data = $this->readFormData();
        $errors = $this->validateForm($data);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            $this->redirect(route('example.create'));
            return;
        }

        $this->service->create($data, (int) $_SESSION['user_id']);

        flash_success(t('example.flash.created'));
        $this->redirect(route('example.index'));
    }

    /**
     * Form di modifica — GET.
     */
    public function edit(string $id): void
    {
        $item = $this->service->find((int) $id, (int) $_SESSION['user_id']);

        if (!$item) {
            flash_error(t('example.flash.not_found'));
            $this->redirect(route('example.index'));
            return;
        }

        $this->render('_Template/Views/form', [
            'pageTitle'   => t('example.edit_page_title'),
            'item'        => $item,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old'] ?? [],
            'breadcrumbs' => [
                ['label' => t('example.title'), 'route' => 'example.index'],
                ['label' => t('example.breadcrumb_edit')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    /**
     * Aggiornamento — PUT (via POST + _method=PUT).
     */
    public function update(string $id): void
    {
        $itemId = (int) $id;
        $userId = (int) $_SESSION['user_id'];
        $item   = $this->service->find($itemId, $userId);

        if (!$item) {
            flash_error(t('example.flash.not_found'));
            $this->redirect(route('example.index'));
            return;
        }

        $data = $this->readFormData();
        $errors = $this->validateForm($data, $itemId);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $data;
            $this->redirect(route('example.edit', ['id' => $itemId]));
            return;
        }

        $this->service->update($itemId, $data, $userId);

        flash_success(t('example.flash.updated'));
        $this->redirect(route('example.index'));
    }

    /**
     * Eliminazione — DELETE (via POST + _method=DELETE).
     */
    public function destroy(string $id): void
    {
        $itemId = (int) $id;
        $userId = (int) $_SESSION['user_id'];

        if (!$this->service->delete($itemId, $userId)) {
            flash_error(t('example.flash.not_found'));
            $this->redirect(route('example.index'));
            return;
        }
        flash_success(t('example.flash.deleted'));
        $this->redirect(route('example.index'));
    }

    // ── Private helpers ─────────────────────────────────────────────

    /**
     * Legge e sanitizza i dati dal form POST.
     * Adatta i campi alla tua tabella.
     */
    private function readFormData(): array
    {
        $clean = $this->cleanPost(['name', 'email', 'description']);
        return [
            'name'        => $clean['name'],
            'email'       => $clean['email'],
            'description' => $clean['description'],
            'status'      => $_POST['status'] ?? 'active',
        ];
    }

    /**
     * Validazione form (required, formato).
     * La validazione business (unicità, regole dominio) va nel Service.
     */
    private function validateForm(array $data, ?int $ignoreId = null): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = [t('validation.required', ['field' => t('example.fields.name')])];
        } elseif (mb_strlen($data['name']) > 255) {
            $errors['name'] = [t('validation.max', ['field' => t('example.fields.name'), 'max' => 255])];
        }

        if ($data['email'] === '') {
            $errors['email'] = [t('validation.required', ['field' => t('example.fields.email')])];
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = [t('validation.email', ['field' => t('example.fields.email')])];
        }

        if (!in_array($data['status'], ['active', 'inactive', 'archived'], true)) {
            $errors['status'] = [t('validation.in', ['field' => t('example.fields.status')])];
        }

        return $errors;
    }
}
