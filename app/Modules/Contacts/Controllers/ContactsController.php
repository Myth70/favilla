<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Contacts\Services\ContactsReminderService;
use App\Modules\Contacts\Services\ContactsService;
use App\Modules\Contacts\Services\RecurrencesService;
use App\Traits\ControllerHelpers;

class ContactsController extends Controller
{
    use ControllerHelpers;

    private ContactsService        $service;
    private RecurrencesService      $ricService;
    private ContactsReminderService $reminderService;

    public function __construct()
    {
        $this->service         = app(ContactsService::class);
        $this->ricService      = app(RecurrencesService::class);
        $this->reminderService = app(ContactsReminderService::class);
    }

    // ── INDEX ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $userId  = (int) $_SESSION['user_id'];
        $filters = $this->cleanGet(['q', 'categoria_id', 'tag', 'sort', 'dir', 'preferiti', 'page'], 255);
        $filters['preferiti'] = !empty($filters['preferiti']) ? 1 : 0;
        $filters['page']      = max(1, (int) ($filters['page'] ?? 1));
        $userRoles            = (array) (auth()['roles'] ?? []);

        $result     = $this->service->list($userId, $filters, $userRoles);
        $categorie  = $this->service->getCategorie($userId);
        $tags       = $this->service->getAllTags($userId);
        $stats      = $this->service->getStats($userId);
        $prossime   = $this->reminderService->getProssime($userId, 30);

        $viewData = [
            'items'       => $result['data'],
            'total'       => $result['total'],
            'pages'       => $result['lastPage'],
            'page'        => $result['page'],
            'perPage'     => $result['perPage'],
            'filters'     => $filters,
            'categorie'   => $categorie,
            'tags'        => $tags,
            'stats'       => $stats,
            'prossime'    => $prossime,
        ];

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Contacts/Views/partials/cards', $viewData);
            return;
        }

        $this->render('Contacts/Views/index', array_merge($viewData, [
            'pageTitle'   => t('contacts.title'),
            'breadcrumbs' => [['label' => t('contacts.title')]],
        ]));
    }

    // ── SEARCH (HTMX live) ───────────────────────────────────────────────────

    public function search(): void
    {
        $userId  = (int) $_SESSION['user_id'];
        $filters = $this->cleanGet(['q', 'categoria_id', 'tag', 'sort', 'dir', 'preferiti'], 255);
        $filters['preferiti'] = !empty($filters['preferiti']) ? 1 : 0;
        $filters['page']      = 1;
        $userRoles            = (array) (auth()['roles'] ?? []);

        $result = $this->service->list($userId, $filters, $userRoles);

        $this->renderPartial('Contacts/Views/partials/cards', [
            'items'    => $result['data'],
            'total'    => $result['total'],
            'pages'    => $result['lastPage'],
            'page'     => $result['page'],
            'perPage'  => $result['perPage'],
            'filters'  => $filters,
        ]);
    }

    // ── SHOW ─────────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $userRoles = (array) (auth()['roles'] ?? []);
        $item   = $this->service->find((int) $id, $userId, $userRoles);

        if (!$item) {
            flash_error(t('contacts.flash.not_found'));
            $this->redirect(route('contacts.index'));
            return;
        }

        // Le ricorrenze restano private del proprietario: i destinatari di
        // una share vedono solo l'anagrafica.
        $isOwner    = !empty($item['is_owner']);
        $ricorrenze = $isOwner ? $this->ricService->allForContatto((int) $id) : [];
        $categorie  = $this->service->getCategorie($userId);
        $shares     = $isOwner ? ($this->service->getShares((int) $id, $userId) ?? []) : [];

        $nomeCompleto = trim($item['nome'] . ' ' . ($item['cognome'] ?? ''));

        $this->render('Contacts/Views/show', [
            'pageTitle'   => $nomeCompleto,
            'item'        => $item,
            'ricorrenze'  => $ricorrenze,
            'categorie'   => $categorie,
            'isOwner'     => $isOwner,
            'shares'      => $shares,
            'breadcrumbs' => [
                ['label' => t('contacts.title'), 'route' => 'contacts.index'],
                ['label' => $nomeCompleto],
            ],
        ]);
    }

    /**
     * Streaming foto contatto — GET /contacts/{id}/foto.
     * uploads/contacts non è servita da Apache: si passa da qui con la
     * stessa visibilità di show (owner o share per ruolo).
     */
    public function foto(string $id): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $userRoles = (array) (auth()['roles'] ?? []);
        $item = $this->service->find((int) $id, $userId, $userRoles);

        if (!$item || empty($item['avatar'])) {
            http_response_code(404);
            return;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $filePath = $basePath . '/public/uploads/contacts/' . basename((string) $item['avatar']);
        if (!is_file($filePath)) {
            http_response_code(404);
            return;
        }

        // Solo immagini: l'upload valida i magic bytes, qui difesa in profondità
        // (mai servire inline un MIME arbitrario).
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($filePath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode(basename((string) $item['avatar'])));
        header('Content-Length: ' . (string) filesize($filePath));
        header('Cache-Control: private, max-age=300');
        readfile($filePath);
        exit;
    }

    // ── CREATE ───────────────────────────────────────────────────────────────

    public function create(): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $categorie = $this->service->getCategorie($userId);

        $this->render('Contacts/Views/form', [
            'pageTitle'   => t('contacts.page_title_new'),
            'item'        => null,
            'categorie'   => $categorie,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old']    ?? [],
            'breadcrumbs' => [
                ['label' => t('contacts.title'), 'route' => 'contacts.index'],
                ['label' => t('contacts.breadcrumb_new')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    // ── STORE ────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $userId = (int) $_SESSION['user_id'];
        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('contacts.create'));
            return;
        }

        $avatarFile = $_FILES['avatar'] ?? null;
        $id         = $this->service->create($data, $userId, $avatarFile);

        flash_success(t('contacts.flash.created'));
        $this->redirect(route('contacts.show', ['id' => $id]));
    }

    // ── EDIT ─────────────────────────────────────────────────────────────────

    public function edit(string $id): void
    {
        $userId = (int) $_SESSION['user_id'];
        $item   = $this->service->findForUser((int) $id, $userId);

        if (!$item) {
            flash_error(t('contacts.flash.not_found'));
            $this->redirect(route('contacts.index'));
            return;
        }

        $categorie = $this->service->getCategorie($userId);

        $nomeCompleto = trim($item['nome'] . ' ' . ($item['cognome'] ?? ''));

        $this->render('Contacts/Views/form', [
            'pageTitle'   => t('contacts.page_title_edit', ['name' => $nomeCompleto]),
            'item'        => $item,
            'categorie'   => $categorie,
            'errors'      => $_SESSION['_errors'] ?? [],
            'old'         => $_SESSION['_old']    ?? [],
            'breadcrumbs' => [
                ['label' => t('contacts.title'), 'route' => 'contacts.index'],
                ['label' => $nomeCompleto, 'route' => 'contacts.show', 'params' => ['id' => $id]],
                ['label' => t('contacts.breadcrumb_edit')],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    // ── UPDATE ───────────────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $itemId = (int) $id;
        $userId = (int) $_SESSION['user_id'];

        if (!$this->service->findForUser($itemId, $userId)) {
            flash_error(t('contacts.flash.not_found'));
            $this->redirect(route('contacts.index'));
            return;
        }

        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('contacts.edit', ['id' => $itemId]));
            return;
        }

        // Rimozione avatar esplicita
        if (!empty($_POST['rimuovi_avatar'])) {
            $this->service->deleteAvatar($itemId, $userId);
        }

        $avatarFile = $_FILES['avatar'] ?? null;
        $this->service->update($itemId, $data, $userId, $avatarFile);

        flash_success(t('contacts.flash.updated'));
        $this->redirect(route('contacts.show', ['id' => $itemId]));
    }

    // ── DESTROY ──────────────────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $userId = (int) $_SESSION['user_id'];
        $this->service->delete((int) $id, $userId);

        flash_success(t('contacts.flash.deleted'));
        $this->redirect(route('contacts.index'));
    }

    // ── TOGGLE PREFERITO (HTMX) ──────────────────────────────────────────────

    public function togglePreferito(string $id): void
    {
        $userId    = (int) $_SESSION['user_id'];
        $itemId    = (int) $id;
        if (!$this->service->togglePreferito($itemId, $userId)) {
            http_response_code(404);
            return;
        }
        $preferito = $this->service->getPreferito($itemId, $userId);

        $this->renderPartial('Contacts/Views/partials/star_button', [
            'id'        => $itemId,
            'preferito' => $preferito,
        ]);
    }

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function readFormData(): array
    {
        $clean = $this->cleanPost([
            'nome', 'cognome', 'azienda', 'ruolo',
            'email', 'telefono', 'telefono_alt', 'indirizzo',
            'sito_web', 'linkedin', 'instagram', 'twitter',
            'facebook', 'whatsapp', 'telegram',
            'tags', 'note', 'latitude', 'longitude', 'geocoding_source',
        ]);

        $clean['categoria_id'] = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
        $clean['preferito']    = !empty($_POST['preferito']) ? 1 : 0;
        $clean['latitude']     = $this->parseCoordinate($clean['latitude'] ?? null);
        $clean['longitude']    = $this->parseCoordinate($clean['longitude'] ?? null);

        $source = (string) ($clean['geocoding_source'] ?? '');
        $clean['geocoding_source'] = in_array($source, ['manual', 'osm'], true) ? $source : null;

        return $clean;
    }

    private function validateForm(array $data): array
    {
        $rules = [
            'nome'         => 'required|max:100',
            'cognome'      => 'nullable|max:100',
            'azienda'      => 'nullable|max:100',
            'ruolo'        => 'nullable|max:100',
            'email'        => 'nullable|email|max:255',
            'telefono'     => 'nullable|regex:/^[\d\s\+\-\.\(\)]{6,20}$/|max:30',
            'telefono_alt' => 'nullable|regex:/^[\d\s\+\-\.\(\)]{6,20}$/|max:30',
            'sito_web'     => 'nullable|url|max:255',
            'linkedin'     => 'nullable|url|max:255',
            'instagram'    => 'nullable|max:100',
            'twitter'      => 'nullable|max:100',
            'facebook'     => 'nullable|url|max:255',
            'whatsapp'     => 'nullable|regex:/^[\d\s\+\-\.\(\)]{6,20}$/|max:30',
            'telegram'     => 'nullable|max:100',
            'indirizzo'    => 'nullable|max:500',
            'latitude'     => 'nullable|numeric|regex:/^-?\d{1,2}(?:\.\d{1,8})?$/',
            'longitude'    => 'nullable|numeric|regex:/^-?\d{1,3}(?:\.\d{1,8})?$/',
            'geocoding_source' => 'nullable|in:manual,osm',
            'tags'         => 'nullable|max:500',
            'note'         => 'nullable|max:2000',
            'categoria_id' => 'nullable|integer',
        ];

        $labels = [];
        foreach (array_keys($rules) as $field) {
            $labels[$field] = t('contacts.fields.' . $field);
        }

        $validator = new Validator();
        $validator->validate($data, $rules, $labels);

        $errors = $validator->errors();

        $latProvided = $data['latitude'] !== null && $data['latitude'] !== '';
        $lngProvided = $data['longitude'] !== null && $data['longitude'] !== '';

        if ($latProvided xor $lngProvided) {
            $errors['latitude'][] = t('contacts.validation.coords_together');
            $errors['longitude'][] = t('contacts.validation.coords_together');
        }

        if ($latProvided && $lngProvided) {
            $lat = (float) $data['latitude'];
            $lng = (float) $data['longitude'];

            if ($lat < -90 || $lat > 90) {
                $errors['latitude'][] = t('contacts.validation.lat_range');
            }

            if ($lng < -180 || $lng > 180) {
                $errors['longitude'][] = t('contacts.validation.lng_range');
            }
        }

        return $errors;
    }

    private function parseCoordinate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace(',', '.', $value));
        return $value === '' ? null : $value;
    }
}
