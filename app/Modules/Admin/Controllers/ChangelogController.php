<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Core\Controller;
use App\Core\Validator;
use App\Modules\Admin\Services\ChangelogService;
use App\Security\Sanitizer;
use App\Traits\ControllerHelpers;

class ChangelogController extends Controller
{
    use ControllerHelpers;
    private ChangelogService $service;

    public function __construct()
    {
        $this->service = app(ChangelogService::class);
    }

    // ---------------------------------------------------------------
    // INDEX — lista release con filtri HTMX
    // ---------------------------------------------------------------

    public function index(): void
    {
        $filters = [
            'search'    => $_GET['search']    ?? '',
            'published' => $_GET['published'] ?? '',
            'sort'      => $_GET['sort']       ?? 'release_date',
            'dir'       => $_GET['dir']        ?? 'DESC',
        ];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $result = $this->service->listPaginated($filters, $page);

        $data = array_merge($result, [
            'total_pages' => $result['lastPage'],
            'filters'     => $filters,
            'pageTitle'   => 'Changelog',
            'breadcrumbs' => [
                ['label' => 'Admin', 'route' => 'admin.dashboard'],
                ['label' => 'Changelog'],
            ],
        ]);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Admin/Views/changelog/partials/table', $data);
            return;
        }
        $this->render('Admin/Views/changelog/index', $data);
    }

    // ---------------------------------------------------------------
    // CREATE / STORE
    // ---------------------------------------------------------------

    public function create(): void
    {
        $this->render('Admin/Views/changelog/form', [
            'release'      => null,
            'translations' => [],
            'pageTitle'   => t('admin.changelog.new_release'),
            'breadcrumbs' => [
                ['label' => 'Admin',     'route' => 'admin.dashboard'],
                ['label' => 'Changelog', 'route' => 'admin.changelog.index'],
                ['label' => t('admin.changelog.bc_new')],
            ],
        ]);
    }

    public function store(): void
    {
        $errors = $this->validateRelease($_POST);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('admin.changelog.create'));
            return;
        }

        $clean   = $this->cleanPost(['version', 'title', 'notes', 'release_date']);
        $version = $clean['version'];
        if ($this->service->findByVersion($version)) {
            $_SESSION['_errors'] = ['version' => t('admin.changelog.version_exists', ['version' => $version])];
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('admin.changelog.create'));
            return;
        }

        $id = $this->service->create([
            'version'      => $version,
            'title'        => $clean['title'],
            'notes'        => $clean['notes'],
            'release_date' => $clean['release_date'],
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'created_by'   => auth()['id'] ?? null,
        ], $this->collectTranslations());

        flash_success(t('admin.changelog.flash_created', ['version' => $version]));
        $this->redirect(route('admin.changelog.show', ['id' => $id]));
    }

    // ---------------------------------------------------------------
    // SHOW
    // ---------------------------------------------------------------

    public function show(string $id): void
    {
        $release = $this->service->find($id);
        if (!$release) {
            $this->redirect(route('admin.changelog.index'));
            return;
        }

        $this->render('Admin/Views/changelog/show', [
            'release'     => $release,
            'pageTitle'   => 'v' . e($release['version']),
            'breadcrumbs' => [
                ['label' => 'Admin',     'route' => 'admin.dashboard'],
                ['label' => 'Changelog', 'route' => 'admin.changelog.index'],
                ['label' => 'v' . $release['version']],
            ],
        ]);
    }

    // ---------------------------------------------------------------
    // EDIT / UPDATE
    // ---------------------------------------------------------------

    public function edit(string $id): void
    {
        $release = $this->service->find($id);
        if (!$release) {
            $this->redirect(route('admin.changelog.index'));
            return;
        }

        $this->render('Admin/Views/changelog/form', [
            'release'      => $release,
            'translations' => $this->service->getTranslations($id),
            'pageTitle'   => t('admin.changelog.edit_page_title', ['version' => $release['version']]),
            'breadcrumbs' => [
                ['label' => 'Admin',     'route' => 'admin.dashboard'],
                ['label' => 'Changelog', 'route' => 'admin.changelog.index'],
                ['label' => 'v' . $release['version'], 'route' => 'admin.changelog.show', 'params' => ['id' => $id]],
                ['label' => t('admin.changelog.bc_edit')],
            ],
        ]);
    }

    public function update(string $id): void
    {
        $release = $this->service->find($id);
        if (!$release) {
            $this->redirect(route('admin.changelog.index'));
            return;
        }

        $errors = $this->validateRelease($_POST, $id);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('admin.changelog.edit', ['id' => $id]));
            return;
        }

        $clean   = $this->cleanPost(['version', 'title', 'notes', 'release_date']);
        $version = $clean['version'];
        $existing = $this->service->findByVersion($version);
        if ($existing && (int) $existing['id'] !== (int) $id) {
            $_SESSION['_errors'] = ['version' => t('admin.changelog.version_exists', ['version' => $version])];
            $_SESSION['_old']    = $_POST;
            $this->redirect(route('admin.changelog.edit', ['id' => $id]));
            return;
        }

        $this->service->update($id, [
            'version'      => $version,
            'title'        => $clean['title'],
            'notes'        => $clean['notes'],
            'release_date' => $clean['release_date'],
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
        ], $this->collectTranslations());

        flash_success(t('admin.changelog.flash_updated', ['version' => $version]));
        $this->redirect(route('admin.changelog.show', ['id' => $id]));
    }

    // ---------------------------------------------------------------
    // DESTROY
    // ---------------------------------------------------------------

    public function destroy(string $id): void
    {
        $release = $this->service->find($id);
        if ($release) {
            $this->service->delete($id);
            flash_success(t('admin.changelog.flash_deleted', ['version' => $release['version']]));
        }
        $this->redirect(route('admin.changelog.index'));
    }

    // ---------------------------------------------------------------
    // TOGGLE PUBLISH
    // ---------------------------------------------------------------

    public function publish(string $id): void
    {
        try {
            $updated = $this->service->togglePublished($id);
        } catch (\RuntimeException $e) {
            $this->redirect(route('admin.changelog.index'));
            return;
        }

        if ($this->isHtmxRequest()) {
            $label = $updated['is_published'] ? t('admin.changelog.published') : t('admin.changelog.draft');
            header('HX-Trigger: ' . json_encode([
                'notify' => ['message' => t('admin.changelog.flash_toggled', ['version' => $updated['version'], 'label' => $label]), 'type' => 'success'],
            ]));
            $this->renderPartial('Admin/Views/changelog/partials/publish-badge', ['release' => $updated]);
            return;
        }

        $this->redirect(route('admin.changelog.show', ['id' => $id]));
    }

    // ---------------------------------------------------------------
    // VERSION — endpoint JSON per badge footer
    // ---------------------------------------------------------------

    public function version(): void
    {
        $latest = $this->service->getLatestPublished();
        $this->json([
            'version' => $latest ? $latest['version'] : null,
            'title'   => $latest ? $latest['title'] : null,
        ]);
    }

    // ---------------------------------------------------------------
    // VALIDAZIONE privata
    // ---------------------------------------------------------------

    /**
     * Raccoglie e sanifica le traduzioni per-locale dal POST (tr[locale][title|notes]).
     * L'italiano resta nei campi base; qui si raccolgono solo le altre lingue.
     *
     * @return array<string, array{title: string, notes: string}>
     */
    private function collectTranslations(): array
    {
        $raw = $_POST['tr'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $locale => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            $out[(string) $locale] = [
                'title' => Sanitizer::clean((string) ($fields['title'] ?? ''), 255),
                'notes' => Sanitizer::clean((string) ($fields['notes'] ?? '')),
            ];
        }

        return $out;
    }

    private function validateRelease(array $data, ?string $excludeId = null): array
    {
        $validator = new Validator();
        $validator->validate($data, [
            'version'      => 'required|regex:/^\d+\.\d+\.\d+$/',
            'title'        => 'required|max:255',
            'notes'        => 'required',
            'release_date' => 'required|date',
        ], [
            'version'      => t('admin.changelog.field_version'),
            'title'        => t('admin.changelog.field_title'),
            'notes'        => t('admin.changelog.field_notes'),
            'release_date' => t('admin.changelog.field_date'),
        ]);
        $errors = $validator->errors();

        // Messaggio specifico per formato semver
        if (!empty($errors['version']) && !empty($data['version']) && !preg_match('/^\d+\.\d+\.\d+$/', trim($data['version']))) {
            $errors['version'] = [t('admin.changelog.invalid_semver')];
        }

        return $errors;
    }
}
