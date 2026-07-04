<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Contacts\Services\ContactSourceService;
use App\Modules\Contacts\Services\ContactsService;
use App\Traits\ControllerHelpers;

class ImportController extends Controller
{
    use ControllerHelpers;

    private ContactSourceService $sources;
    private ContactsService      $contatti;

    public function __construct()
    {
        $this->sources  = app(ContactSourceService::class);
        $this->contatti = app(ContactsService::class);
    }

    // ── INDEX: lista delle fonti raggruppate per modulo ─────────────────────

    public function index(): void
    {
        $user = auth() ?? [];
        $modules = $this->sources->getSourcesForUser($user);

        $this->render('Contacts/Views/import/index', [
            'pageTitle'   => 'Importa contatti',
            'modules'     => $modules,
            'breadcrumbs' => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Importa'],
            ],
        ]);
    }

    // ── BROWSE: pagina con tabella record di una fonte ──────────────────────

    public function browse(string $module, string $source): void
    {
        $sourceMeta = $this->sources->findSource($module, $source);
        if ($sourceMeta === null) {
            flash_error('Sorgente non trovata.');
            $this->redirect(route('contacts.import.index'));
            return;
        }

        $filters = $this->cleanGet(['q', 'page'], 255);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        try {
            $result = $this->sources->fetchList($module, $source, $filters, $page, 25);
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('contacts.import.index'));
            return;
        }

        $this->render('Contacts/Views/import/browse', [
            'pageTitle'   => 'Importa da ' . ($sourceMeta['label'] ?? $source),
            'module'      => $module,
            'source'      => $source,
            'sourceMeta'  => $sourceMeta,
            'rows'        => $result['rows'] ?? [],
            'total'       => (int) ($result['total'] ?? 0),
            'page'        => $page,
            'perPage'     => 25,
            'filters'     => $filters,
            'breadcrumbs' => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Importa', 'route' => 'contacts.import.index'],
                ['label' => $sourceMeta['module_label'] ?? $module],
                ['label' => $sourceMeta['label'] ?? $source],
            ],
        ]);
    }

    // ── LIST PARTIAL (HTMX): tbody refresh per filtri ───────────────────────

    public function listPartial(string $module, string $source): void
    {
        $sourceMeta = $this->sources->findSource($module, $source);
        if ($sourceMeta === null) {
            http_response_code(404);
            return;
        }

        $filters = $this->cleanGet(['q', 'page'], 255);
        $page    = max(1, (int) ($filters['page'] ?? 1));

        try {
            $result = $this->sources->fetchList($module, $source, $filters, $page, 25);
        } catch (\RuntimeException) {
            http_response_code(403);
            return;
        }

        $this->renderPartial('Contacts/Views/import/partials/import_rows', [
            'module'  => $module,
            'source'  => $source,
            'rows'    => $result['rows'] ?? [],
            'total'   => (int) ($result['total'] ?? 0),
            'page'    => $page,
            'perPage' => 25,
            'filters' => $filters,
        ]);
    }

    // ── PREVIEW: form Contatti precompilato dal payload del provider ────────

    public function preview(string $module, string $source, string $sourceId): void
    {
        $sourceMeta = $this->sources->findSource($module, $source);
        if ($sourceMeta === null) {
            flash_error('Sorgente non trovata.');
            $this->redirect(route('contacts.import.index'));
            return;
        }

        try {
            $payload = $this->sources->fetchOne($module, $source, (int) $sourceId);
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('contacts.import.index'));
            return;
        }

        if ($payload === null) {
            flash_error('Record non trovato nella sorgente.');
            $this->redirect(route('contacts.import.browse', ['module' => $module, 'source' => $source]));
            return;
        }

        $userId    = (int) $_SESSION['user_id'];
        $categorie = $this->contatti->getCategorie($userId);

        // Pre-fill: form.php usa $old come prima fonte, $item come seconda.
        // Passare il payload via $old + $item=null mantiene il form in modalità "create".
        $sessionOld = $_SESSION['_old']    ?? [];
        $errors     = $_SESSION['_errors'] ?? [];
        $old        = !empty($sessionOld) ? $sessionOld : $payload;

        $this->render('Contacts/Views/form', [
            'pageTitle'    => 'Importa contatto da ' . ($sourceMeta['label'] ?? $source),
            'item'         => null,
            'categorie'    => $categorie,
            'errors'       => $errors,
            'old'          => $old,
            'formAction'   => route('contacts.import.store', [
                'module'   => $module,
                'source'   => $source,
                'sourceId' => $sourceId,
            ]),
            'formMethod'   => 'POST',
            'formTitle'    => 'Importa contatto',
            'formSubtitle' => 'Da ' . ($sourceMeta['module_label'] ?? $module) . ' / ' . ($sourceMeta['label'] ?? $source) . ' — rivedi e salva',
            'cancelUrl'    => route('contacts.import.browse', ['module' => $module, 'source' => $source]),
            'submitLabel'  => 'Importa contatto',
            'breadcrumbs'  => [
                ['label' => 'Contatti', 'route' => 'contacts.index'],
                ['label' => 'Importa', 'route' => 'contacts.import.index'],
                ['label' => $sourceMeta['label'] ?? $source, 'route' => 'contacts.import.browse', 'params' => ['module' => $module, 'source' => $source]],
                ['label' => 'Anteprima'],
            ],
        ]);
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    // ── STORE: validazione e creazione tramite ContactsService ──────────────

    public function store(string $module, string $source, string $sourceId): void
    {
        $sourceMeta = $this->sources->findSource($module, $source);
        if ($sourceMeta === null) {
            flash_error('Sorgente non trovata.');
            $this->redirect(route('contacts.import.index'));
            return;
        }

        // Permission re-check: previene chiamate dirette POST alla store dopo
        // che il provider/source è stato disabilitato o il permesso revocato.
        try {
            $this->sources->fetchOne($module, $source, (int) $sourceId);
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('contacts.import.index'));
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        $data   = $this->readFormData();
        $errors = $this->validateForm($data);

        if (!empty($errors)) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('contacts.import.preview', [
                'module'   => $module,
                'source'   => $source,
                'sourceId' => $sourceId,
            ]));
            return;
        }

        $avatarFile = $_FILES['avatar'] ?? null;
        $id         = $this->contatti->create($data, $userId, $avatarFile);

        flash_success('Contatto importato da ' . ($sourceMeta['module_label'] ?? $module) . '.');
        $this->redirect(route('contacts.show', ['id' => $id]));
    }

    // ── Helpers privati (mirror di ContactsController) ──────────────────────

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

        $labels = [
            'nome' => 'Nome', 'cognome' => 'Cognome', 'azienda' => 'Azienda',
            'ruolo' => 'Ruolo', 'email' => 'Email', 'telefono' => 'Telefono',
            'telefono_alt' => 'Telefono alternativo', 'sito_web' => 'Sito web',
            'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'twitter' => 'Twitter',
            'facebook' => 'Facebook', 'whatsapp' => 'WhatsApp', 'telegram' => 'Telegram',
            'indirizzo' => 'Indirizzo', 'latitude' => 'Latitudine', 'longitude' => 'Longitudine',
            'geocoding_source' => 'Origine geolocalizzazione',
            'tags' => 'Tag', 'note' => 'Note', 'categoria_id' => 'Categoria',
        ];

        $validator = new Validator();
        $validator->validate($data, $rules, $labels);
        $errors = $validator->errors();

        $latProvided = $data['latitude'] !== null && $data['latitude'] !== '';
        $lngProvided = $data['longitude'] !== null && $data['longitude'] !== '';

        if ($latProvided xor $lngProvided) {
            $errors['latitude'][]  = 'Latitudine e longitudine devono essere valorizzate insieme.';
            $errors['longitude'][] = 'Latitudine e longitudine devono essere valorizzate insieme.';
        }

        if ($latProvided && $lngProvided) {
            $lat = (float) $data['latitude'];
            $lng = (float) $data['longitude'];
            if ($lat < -90 || $lat > 90) {
                $errors['latitude'][] = 'La latitudine deve essere compresa tra -90 e 90.';
            }
            if ($lng < -180 || $lng > 180) {
                $errors['longitude'][] = 'La longitudine deve essere compresa tra -180 e 180.';
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
