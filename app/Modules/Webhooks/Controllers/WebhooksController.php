<?php

declare(strict_types=1);

namespace App\Modules\Webhooks\Controllers;

use App\Core\Controller;
use App\Modules\Notifications\Services\NotificationEventRegistryService;
use App\Modules\Webhooks\Services\WebhooksService;
use App\Traits\ControllerHelpers;

class WebhooksController extends Controller
{
    use ControllerHelpers;

    private WebhooksService $service;

    public function __construct()
    {
        $this->service = app(WebhooksService::class);
    }

    public function index(): void
    {
        // Secret appena generato: mostrato una sola volta.
        $newSecret = $_SESSION['_new_webhook_secret'] ?? null;
        unset($_SESSION['_new_webhook_secret']);

        $this->render('Webhooks/Views/index', [
            'pageTitle'     => t('webhooks.title'),
            'endpoints'     => $this->service->list(),
            'stats'         => $this->service->deliveryStats(),
            'newSecret'     => is_string($newSecret) ? $newSecret : null,
            'breadcrumbs'   => [['label' => t('webhooks.title')]],
        ]);
    }

    public function create(): void
    {
        $this->renderForm(null);
    }

    public function edit(string $id): void
    {
        $endpoint = $this->service->find((int) $id);
        if ($endpoint === null) {
            flash_error(t('webhooks.flash_not_found'));
            $this->redirect(route('webhooks.index'));
            return;
        }
        $this->renderForm($endpoint);
    }

    public function store(): void
    {
        [$url, $events, $description] = $this->readInput();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $result = $this->service->create($url, $events, $description, $userId);
            $_SESSION['_new_webhook_secret'] = $result['secret'];
            flash_success(t('webhooks.flash_created'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('webhooks.create'));
            return;
        }

        $this->redirect(route('webhooks.index'));
    }

    public function update(string $id): void
    {
        [$url, $events, $description] = $this->readInput();
        $isActive = ($_POST['is_active'] ?? '') === '1';

        try {
            $this->service->update((int) $id, $url, $events, $description, $isActive);
            flash_success(t('webhooks.flash_updated'));
        } catch (\RuntimeException $e) {
            flash_error($e->getMessage());
            $this->redirect(route('webhooks.edit', ['id' => $id]));
            return;
        }

        $this->redirect(route('webhooks.index'));
    }

    public function destroy(string $id): void
    {
        $this->service->delete((int) $id);
        flash_success(t('webhooks.flash_deleted'));
        $this->redirect(route('webhooks.index'));
    }

    public function regenerateSecret(string $id): void
    {
        $secret = $this->service->regenerateSecret((int) $id);
        if ($secret === null) {
            flash_error(t('webhooks.flash_not_found'));
        } else {
            $_SESSION['_new_webhook_secret'] = $secret;
            flash_success(t('webhooks.flash_secret_regenerated'));
        }
        $this->redirect(route('webhooks.index'));
    }

    public function test(string $id): void
    {
        try {
            $message = $this->service->sendTest((int) $id);
            flash_success(t('webhooks.flash_test_ok') . ' ' . $message);
        } catch (\RuntimeException $e) {
            flash_error(t('webhooks.flash_test_failed') . ' ' . $e->getMessage());
        }
        $this->redirect(route('webhooks.index'));
    }

    public function deliveries(string $id): void
    {
        $endpoint = $this->service->find((int) $id);
        if ($endpoint === null) {
            flash_error(t('webhooks.flash_not_found'));
            $this->redirect(route('webhooks.index'));
            return;
        }

        $this->render('Webhooks/Views/deliveries', [
            'pageTitle'   => t('webhooks.deliveries_title'),
            'endpoint'    => $endpoint,
            'deliveries'  => $this->service->deliveriesFor((int) $id),
            'breadcrumbs' => [
                ['label' => t('webhooks.title'), 'route' => 'webhooks.index'],
                ['label' => t('webhooks.deliveries_title')],
            ],
        ]);
    }

    /**
     * @param array<string, mixed>|null $endpoint
     */
    private function renderForm(?array $endpoint): void
    {
        $this->render('Webhooks/Views/form', [
            'pageTitle'    => $endpoint === null ? t('webhooks.create_title') : t('webhooks.edit_title'),
            'endpoint'     => $endpoint,
            'eventCatalog' => $this->eventCatalog(),
            'breadcrumbs'  => [
                ['label' => t('webhooks.title'), 'route' => 'webhooks.index'],
                ['label' => $endpoint === null ? t('webhooks.create_title') : t('webhooks.edit_title')],
            ],
        ]);
    }

    /**
     * Catalogo eventi disponibili (slug + label), raggruppati per modulo.
     *
     * @return array<int, array{module:string, events:array<int, array{slug:string, name:string}>}>
     */
    private function eventCatalog(): array
    {
        $catalog = app(NotificationEventRegistryService::class)->getEventCatalog();
        $out = [];
        foreach ($catalog as $module) {
            $events = [];
            foreach (($module['events'] ?? []) as $event) {
                $events[] = ['slug' => (string) $event['slug'], 'name' => (string) $event['name']];
            }
            if ($events !== []) {
                $out[] = ['module' => (string) ($module['label'] ?? $module['slug'] ?? ''), 'events' => $events];
            }
        }
        return $out;
    }

    /**
     * @return array{0:string, 1:string[], 2:?string}
     */
    private function readInput(): array
    {
        $url = trim((string) ($_POST['url'] ?? ''));
        $events = $_POST['event_types'] ?? [];
        $events = is_array($events) ? array_values(array_filter(array_map('strval', $events))) : [];
        $description = trim((string) ($_POST['description'] ?? ''));
        return [$url, $events, $description !== '' ? $description : null];
    }
}
