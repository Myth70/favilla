<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;
use App\Services\GlobalSearchService;
use App\Traits\ControllerHelpers;

class SearchController extends Controller
{
    use ControllerHelpers;

    /**
     * GET /search?q=... — full results page.
     */
    public function index(): void
    {
        $q      = $this->cleanGet(['q'], 255)['q'];
        $userId = (int) (auth()['id'] ?? 0);

        $service  = app(GlobalSearchService::class);
        $grouped  = $q !== '' ? $service->search($q, $userId, 10) : [];

        $totalResults = 0;
        foreach ($grouped as $group) {
            $totalResults += count($group['results']);
        }

        $this->render('Home/Views/search', [
            'q'            => $q,
            'grouped'      => $grouped,
            'totalResults' => $totalResults,
            'pageTitle'    => $q !== '' ? t('home.search.title_results', ['query' => $q]) : t('home.search.title'),
            'breadcrumbs'  => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home.index'],
                ['label' => t('home.breadcrumb.search')],
            ],
        ]);
    }

    /**
     * GET /search/quick?q=... — HTMX dropdown partial.
     * Includes a simple session-based rate-limit to avoid excessive queries.
     */
    public function quick(): void
    {
        $q      = $this->cleanGet(['q'], 255)['q'];
        $userId = (int) (auth()['id'] ?? 0);

        $service = app(GlobalSearchService::class);
        $grouped = $q !== '' ? $service->search($q, $userId, 3) : [];

        $this->renderPartial('Home/Views/partials/search_results', [
            'q'       => $q,
            'grouped' => $grouped,
        ]);
    }
}
