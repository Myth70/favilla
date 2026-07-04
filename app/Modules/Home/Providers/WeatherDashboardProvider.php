<?php

declare(strict_types=1);

namespace App\Modules\Home\Providers;

use App\Contracts\DashboardWidgetProvider;
use App\Modules\Home\Services\WeatherService;

/**
 * Widget meteo della Home. Usa i partial standard (stat + list) per restare
 * coerente con gli altri widget in dimensioni e stile. La previsione richiede
 * una chiamata HTTP esterna: viene cachata più a lungo (cache_ttl) e caricata
 * in modo lazy/parallelo, così non rallenta gli altri widget.
 */
class WeatherDashboardProvider implements DashboardWidgetProvider
{
    /** Le previsioni meteo cambiano lentamente: cache più lunga del default. */
    private const CACHE_TTL = 900;

    public function getWidgets(int $userId): array
    {
        return [
            [
                'id'         => 'home.weather',
                'type'       => 'stat',
                'label'      => 'Meteo',
                'icon'       => 'fa-cloud',
                'size'       => 3,
                'permission' => null,
                'cache_ttl'  => self::CACHE_TTL,
                'lazy'       => true, // chiamata HTTP esterna: caricata a parte
            ],
            [
                'id'         => 'home.weather_forecast',
                'type'       => 'list',
                'label'      => 'Previsioni meteo',
                'icon'       => 'fa-cloud-sun',
                'size'       => 6,
                'permission' => null,
                'cache_ttl'  => self::CACHE_TTL,
                'lazy'       => true, // chiamata HTTP esterna: caricata a parte
            ],
        ];
    }

    public function getWidgetData(int $userId, string $widgetId): ?array
    {
        $weather = app(WeatherService::class)->getForecast($userId);

        return match ($widgetId) {
            'home.weather'          => $this->currentData($weather),
            'home.weather_forecast' => $this->forecastData($weather),
            default                 => null,
        };
    }

    /**
     * @param array<string, mixed> $weather
     * @return array<string, mixed>
     */
    private function currentData(array $weather): array
    {
        $label = (string) ($weather['label'] ?? t('home.widget.weather_label'));

        if (!empty($weather['offline'])) {
            return [
                'label' => t('home.widget.weather_label'),
                'icon'  => 'fa-cloud',
                'data'  => [
                    'value'    => t('home.widget.na'),
                    'subtitle' => t('home.widget.weather_unavailable', ['label' => $label]),
                    'link'     => route('home.weather.page'),
                    'color'    => 'secondary',
                ],
            ];
        }

        $current  = is_array($weather['current'] ?? null) ? $weather['current'] : [];
        $unit     = (string) ($current['unit'] ?? '°C');
        $temp     = (int) ($current['temp'] ?? 0);
        $apparent = (int) ($current['apparent'] ?? $temp);
        $desc     = (string) ($current['description'] ?? '');
        $icon     = (string) ($current['icon'] ?? 'fa-cloud');

        return [
            'label' => $label,
            'icon'  => $icon,
            'data'  => [
                'value'    => $temp . $unit,
                'subtitle' => ($desc !== '' ? $desc . ' · ' : '') . t('home.widget.feels_like') . ' ' . $apparent . $unit,
                'link'     => route('home.weather.page'),
                'color'    => 'info',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $weather
     * @return array<string, mixed>|null
     */
    private function forecastData(array $weather): ?array
    {
        if (!empty($weather['offline'])) {
            return null;
        }

        $daily = is_array($weather['daily'] ?? null) ? $weather['daily'] : [];
        if (empty($daily)) {
            return null;
        }

        $label = (string) ($weather['label'] ?? t('home.widget.weather_label'));

        $rows = [];
        foreach (array_slice($daily, 0, 5) as $i => $day) {
            $rows[] = [
                ['html' => $this->dayLabel((string) ($day['date'] ?? ''), (int) $i)],
                ['html' => '<i class="fa-solid ' . e((string) ($day['icon'] ?? 'fa-cloud')) . ' me-1 text-info"></i>' . e((string) ($day['desc'] ?? ''))],
                ['html' => '<strong>' . (int) ($day['max'] ?? 0) . '°</strong> <span class="text-muted">' . (int) ($day['min'] ?? 0) . '°</span>'],
            ];
        }

        return [
            'label' => t('home.widget.forecast_label', ['label' => $label]),
            'data'  => [
                'columns'       => [t('home.widget.col_day'), t('home.widget.col_weather'), t('home.widget.col_maxmin')],
                'rows'          => $rows,
                'emptyMessage'  => t('home.widget.forecast_empty'),
                'link'          => route('home.weather.page'),
                'linkLabel'     => t('home.widget.open'),
                'iconColor'     => 'info',
                'headerPartial' => 'Home/Views/partials/widgets/weather_loc_selector',
            ],
        ];
    }

    private function dayLabel(string $date, int $index): string
    {
        if ($index === 0) {
            return '<strong>' . e(t('home.widget.today')) . '</strong>';
        }

        $ts = $date !== '' ? strtotime($date . ' 00:00:00') : false;
        if ($ts === false) {
            return e($date);
        }

        $giorni = [
            t('home.widget.dow_0'), t('home.widget.dow_1'), t('home.widget.dow_2'),
            t('home.widget.dow_3'), t('home.widget.dow_4'), t('home.widget.dow_5'),
            t('home.widget.dow_6'),
        ];
        $g = $giorni[(int) date('w', $ts)];
        return '<strong>' . e($g) . '</strong> <span class="text-muted">' . e(date('d/m', $ts)) . '</span>';
    }
}
