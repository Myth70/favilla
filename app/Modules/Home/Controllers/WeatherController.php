<?php

declare(strict_types=1);

namespace App\Modules\Home\Controllers;

use App\Core\Controller;
use App\Modules\Home\Services\WeatherService;
use App\Traits\ControllerHelpers;

class WeatherController extends Controller
{
    use ControllerHelpers;

    private WeatherService $weather;

    public function __construct()
    {
        $this->weather = app(WeatherService::class);
    }

    /**
     * Pagina Meteo completa — raggiungibile solo dai widget della dashboard.
     */
    public function page(): void
    {
        $userId   = (int) (auth()['id'] ?? 0);
        $forecast = $this->weather->getFullForecast($userId);

        $this->render('Home/Views/meteo', [
            'pageTitle'    => t('home.weather.label'),
            'breadcrumbs'  => [['label' => t('home.breadcrumb.weather')]],
            'forecast'     => $forecast,
        ]);
    }

    /**
     * HTMX: ricerca località (geocoding Open-Meteo) per il selettore del widget.
     */
    public function search(): void
    {
        $q       = trim((string) ($_GET['q'] ?? ''));
        $results = $q !== '' ? $this->weather->searchLocations($q) : [];

        $this->renderPartial('Home/Views/partials/widgets/weather_search_results', [
            'results' => $results,
        ]);
    }

    /**
     * POST: salva la località scelta e forza il refresh dei widget.
     */
    public function setLocation(): void
    {
        $userId = (int) (auth()['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(403);
            return;
        }

        $ok = $this->weather->saveLocation($userId, [
            'name'         => (string) ($_POST['name'] ?? ''),
            'admin1'       => $_POST['admin1'] ?? null,
            'country'      => $_POST['country'] ?? null,
            'country_code' => $_POST['country_code'] ?? null,
            'latitude'     => $_POST['latitude'] ?? null,
            'longitude'    => $_POST['longitude'] ?? null,
            'timezone'     => $_POST['timezone'] ?? 'auto',
        ]);

        if (!$ok) {
            $this->json(['error' => t('home.weather.no_locations')], 422);
            return;
        }

        $this->hxToast(t('home.weather.location_updated'));
        header('HX-Trigger: {"refreshWidgets": true}');
        http_response_code(204);
    }
}
