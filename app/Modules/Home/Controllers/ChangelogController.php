<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;
use App\Modules\Home\Services\ChangelogService;

class ChangelogController extends Controller
{
    private ChangelogService $service;

    public function __construct()
    {
        $this->service = app(ChangelogService::class);
    }

    public function index(): void
    {
        $timeline = $this->service->getPublicTimeline(40);
        $latest = $timeline['latest'];

        $subtitle = empty($latest['release_date'])
            ? t('home.changelog.subtitle', ['count' => $timeline['total']])
            : t('home.changelog.subtitle_updated', [
                'count' => $timeline['total'],
                'date'  => format_date((string) $latest['release_date'], 'short'),
            ]);

        $this->render('Home/Views/changelog', [
            'pageTitle'   => t('home.changelog.title'),
            'breadcrumbs' => [
                ['label' => t('home.breadcrumb.home'), 'route' => 'home'],
                ['label' => t('home.breadcrumb.changelog')],
            ],
            'items'          => $timeline['items'],
            'totalPublished' => $timeline['total'],
            'latestVersion'  => $latest['version'] ?? null,
            'moduleSubtitle' => $subtitle,
        ]);
    }
}
