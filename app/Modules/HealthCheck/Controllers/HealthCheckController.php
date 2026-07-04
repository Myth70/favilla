<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Controllers;

use App\Core\Controller;
use App\Modules\HealthCheck\Services\HealthCheckService;
use App\Services\CsvExportService;
use App\Traits\ControllerHelpers;

class HealthCheckController extends Controller
{
    use ControllerHelpers;

    private HealthCheckService $service;

    public function __construct()
    {
        $this->service = app(HealthCheckService::class);
    }

    /**
     * Dashboard principale Health Check (solo check rapidi).
     */
    public function index(): void
    {
        // Richiesta HTMX: esegui i check rapidi e restituisci solo il partial
        if ($this->isHtmxRequest()) {
            $this->renderResults($this->service->runFast(), false);
            return;
        }

        // Prima visita: restituisce solo lo shell con lo spinner (HTMX caricherà il contenuto)
        $data = [
            'pageTitle'   => t('healthcheck.title'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'admin.dashboard', 'Admin'), 'route' => 'admin.dashboard'],
                ['label' => t('healthcheck.title')],
            ],
        ];

        $this->render('HealthCheck/Views/index', $data);
    }

    /**
     * Scansione approfondita: esegue anche i check "deep" (DNS email, fetch .env,
     * composer audit). Disponibile via HTMX dal pulsante dedicato.
     */
    public function deepScan(): void
    {
        $this->renderResults($this->service->runAll(), true);
    }

    /**
     * Aggrega, salva, notifica e rende il partial dei risultati.
     *
     * @param array<string,array{label:string,description:string,checks:array}> $results
     */
    private function renderResults(array $results, bool $deep): void
    {
        $summary = $this->service->summary($results);
        $groups  = $this->service->prioritizeResults($results);
        $this->service->saveRun($results, $summary);
        $this->service->notifyOnFailures($results, $summary);

        $this->renderPartial('HealthCheck/Views/partials/content', [
            'results' => $results,
            'summary' => $summary,
            'groups'  => $groups,
            'deep'    => $deep,
        ]);
    }

    /**
     * Storico run.
     */
    public function history(): void
    {
        $filters = $this->cleanGet(['page']);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $result = $this->service->getHistory(20, $page);

        $data = [
            'pageTitle'   => t('healthcheck.history_title'),
            'breadcrumbs' => [
                ['label' => t_line('nav', 'admin.dashboard', 'Admin'), 'route' => 'admin.dashboard'],
                ['label' => t('healthcheck.title'), 'route' => 'healthcheck.index'],
                ['label' => t('healthcheck.breadcrumb_history')],
            ],
            'runs'  => $result['items'],
            'total' => $result['total'],
            'page'  => $result['page'],
            'pages' => $result['lastPage'],
        ];

        $this->htmxOrRender(
            'HealthCheck/Views/partials/history_table',
            'HealthCheck/Views/history',
            $data
        );
    }

    /**
     * Export CSV dei check correnti.
     */
    public function export(): void
    {
        $results = $this->service->runAll();
        $rows    = $this->service->toExportRows($results);

        CsvExportService::stream($rows, 'healthcheck_' . date('Ymd_His') . '.csv');
    }
}
