<?php

declare(strict_types=1);

namespace App\Modules\Home\Services;

use App\Modules\Home\Repositories\WeatherPreferencesRepository;

/**
 * Servizio meteo minimale basato su Open-Meteo (nessuna API key).
 *
 * - Località di default configurabile via SettingsService (weather_lat/lon/label)
 *   o variabili d'ambiente (WEATHER_LAT/WEATHER_LON/WEATHER_LABEL); fallback: Roma.
 * - Cache su file (~30 min) per non chiamare l'API ad ogni refresh della dashboard.
 * - Non lancia mai eccezioni: in caso di errore ritorna ['offline' => true].
 */
class WeatherService
{
    private const API_URL      = 'https://api.open-meteo.com/v1/forecast';
    private const CACHE_TTL     = 1800; // 30 minuti
    private const HTTP_TIMEOUT  = 4;

    private const DEFAULT_LAT   = 41.8933;
    private const DEFAULT_LON   = 12.4829;
    private const DEFAULT_LABEL = 'Roma';

    /**
     * @return array{offline: bool, label: string, current?: array<string,mixed>, daily?: array<int,array<string,mixed>>}
     */
    public function getForecast(int $userId): array
    {
        try {
            [$lat, $lon, $label] = $this->location($userId);
            $raw = $this->fetch($lat, $lon);
            if ($raw === null) {
                return ['offline' => true, 'label' => $label];
            }
            return $this->normalize($raw, $label);
        } catch (\Throwable) {
            return ['offline' => true, 'label' => 'Meteo'];
        }
    }

    /**
     * Previsione COMPLETA per la pagina Meteo (current + hourly 24h + daily 7gg).
     *
     * @return array<string,mixed>
     */
    public function getFullForecast(int $userId): array
    {
        try {
            [$lat, $lon, $label] = $this->location($userId);
            $raw = $this->fetchFull($lat, $lon);
            if ($raw === null) {
                return ['offline' => true, 'label' => $label];
            }
            return $this->normalizeFull($raw, $label);
        } catch (\Throwable) {
            return ['offline' => true, 'label' => 'Meteo'];
        }
    }

    /**
     * Ricerca località tramite il geocoding di Open-Meteo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchLocations(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        try {
            $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
                'name'     => $query,
                'count'    => 6,
                'language' => 'it',
                'format'   => 'json',
            ]);
            $body = $this->httpGet($url);
            if ($body === null) {
                return [];
            }
            $data    = json_decode($body, true);
            $results = is_array($data['results'] ?? null) ? $data['results'] : [];
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($results as $r) {
            if (!isset($r['latitude'], $r['longitude'], $r['name'])) {
                continue;
            }
            $out[] = [
                'name'         => (string) $r['name'],
                'admin1'       => isset($r['admin1']) ? (string) $r['admin1'] : null,
                'country'      => isset($r['country']) ? (string) $r['country'] : null,
                'country_code' => isset($r['country_code']) ? (string) $r['country_code'] : null,
                'latitude'     => (float) $r['latitude'],
                'longitude'    => (float) $r['longitude'],
                'timezone'     => isset($r['timezone']) ? (string) $r['timezone'] : 'auto',
            ];
        }
        return $out;
    }

    /**
     * Salva la località preferita dell'utente. Ritorna false se i dati non sono validi.
     */
    public function saveLocation(int $userId, array $loc): bool
    {
        $lat  = $loc['latitude'] ?? null;
        $lon  = $loc['longitude'] ?? null;
        $name = trim((string) ($loc['name'] ?? ''));

        if ($userId <= 0 || $name === '' || !is_numeric($lat) || !is_numeric($lon)) {
            return false;
        }

        $clip = static fn ($v, int $len): ?string =>
            (is_string($v) && trim($v) !== '') ? mb_substr(trim($v), 0, $len) : null;

        app(WeatherPreferencesRepository::class)->upsertForUser($userId, [
            'name'         => mb_substr($name, 0, 150),
            'admin1'       => $clip($loc['admin1'] ?? null, 150),
            'country'      => $clip($loc['country'] ?? null, 150),
            'country_code' => $clip($loc['country_code'] ?? null, 10),
            'latitude'     => round((float) $lat, 6),
            'longitude'    => round((float) $lon, 6),
            'timezone'     => $clip($loc['timezone'] ?? null, 64) ?? 'auto',
        ]);

        return true;
    }

    /**
     * Risolve la località: preferenza utente → default globale (settings/env) → Roma.
     *
     * @return array{0: float, 1: float, 2: string}
     */
    private function location(int $userId): array
    {
        try {
            $pref = app(WeatherPreferencesRepository::class)->findByUser($userId);
            if ($pref && is_numeric($pref['latitude'] ?? null) && is_numeric($pref['longitude'] ?? null)) {
                $label = trim((string) ($pref['name'] ?? '')) ?: 'Località';
                return [(float) $pref['latitude'], (float) $pref['longitude'], $label];
            }
        } catch (\Throwable) {
            // ignora: usa il default
        }

        $lat   = setting('weather_lat', null)   ?? env('WEATHER_LAT', null);
        $lon   = setting('weather_lon', null)   ?? env('WEATHER_LON', null);
        $label = setting('weather_label', null) ?? env('WEATHER_LABEL', null);

        if (!is_numeric($lat) || !is_numeric($lon)) {
            return [self::DEFAULT_LAT, self::DEFAULT_LON, self::DEFAULT_LABEL];
        }

        $label = is_string($label) && trim($label) !== '' ? trim($label) : 'Località';
        return [(float) $lat, (float) $lon, $label];
    }

    /**
     * Payload compatto per i widget (current + daily 5gg).
     *
     * @return array<string,mixed>|null
     */
    private function fetch(float $lat, float $lon): ?array
    {
        $url = self::API_URL . '?' . http_build_query([
            'latitude'      => $lat,
            'longitude'     => $lon,
            'current'       => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m',
            'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
            'timezone'      => 'auto',
            'forecast_days' => 5,
        ]);

        $data = $this->cachedJson($url, self::CACHE_TTL);
        return ($data && isset($data['current'])) ? $data : null;
    }

    /**
     * Payload completo per la pagina Meteo (current + hourly + daily, molte variabili).
     *
     * @return array<string,mixed>|null
     */
    private function fetchFull(float $lat, float $lon): ?array
    {
        $url = self::API_URL . '?' . http_build_query([
            'latitude'      => $lat,
            'longitude'     => $lon,
            'current'       => 'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,rain,showers,snowfall,weather_code,cloud_cover,pressure_msl,wind_speed_10m,wind_direction_10m,wind_gusts_10m',
            'hourly'        => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation_probability,precipitation,weather_code,visibility,wind_speed_10m,is_day',
            'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,sunrise,sunset,daylight_duration,sunshine_duration,uv_index_max,precipitation_sum,precipitation_probability_max,precipitation_hours,wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant,shortwave_radiation_sum',
            'timezone'      => 'auto',
            'forecast_days' => 7,
        ]);

        $data = $this->cachedJson($url, 900);
        return ($data && isset($data['current'])) ? $data : null;
    }

    /**
     * GET con cache su file (stale-while-error) + decodifica JSON.
     *
     * @return array<string,mixed>|null
     */
    private function cachedJson(string $url, int $ttl): ?array
    {
        $cacheFile = $this->cacheDir() . DIRECTORY_SEPARATOR . 'weather_' . md5($url) . '.json';

        if (@is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $ttl) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $body = $this->httpGet($url);
        if ($body === null) {
            if (@is_file($cacheFile)) {
                $stale = json_decode((string) @file_get_contents($cacheFile), true);
                if (is_array($stale)) {
                    return $stale;
                }
            }
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        @file_put_contents($cacheFile, $body, LOCK_EX);
        return $data;
    }

    /**
     * Directory di cache dentro il progetto (compatibile con open_basedir).
     * sys_get_temp_dir() su XAMPP è spesso fuori da open_basedir.
     */
    private function cacheDir(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir  = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return (is_dir($dir) && is_writable($dir)) ? $dir : $base;
    }

    private function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT,
                CURLOPT_USERAGENT      => 'Favilla-Dashboard',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
        }

        $ctx = stream_context_create(['http' => [
            'timeout' => self::HTTP_TIMEOUT,
            'header'  => "User-Agent: Favilla-Dashboard\r\n",
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false ? $body : null;
    }

    /**
     * @param  array<string,mixed> $raw
     * @return array{offline: bool, label: string, current: array<string,mixed>, daily: array<int,array<string,mixed>>}
     */
    private function normalize(array $raw, string $label): array
    {
        $current = is_array($raw['current'] ?? null) ? $raw['current'] : [];
        $code    = (int) ($current['weather_code'] ?? 0);
        [$icon, $desc] = self::codeInfo($code);

        $out = [
            'offline' => false,
            'label'   => $label,
            'current' => [
                'temp'        => (int) round((float) ($current['temperature_2m'] ?? 0)),
                'apparent'    => (int) round((float) ($current['apparent_temperature'] ?? 0)),
                'wind'        => (int) round((float) ($current['wind_speed_10m'] ?? 0)),
                'code'        => $code,
                'icon'        => $icon,
                'description' => $desc,
                'unit'        => (string) ($raw['current_units']['temperature_2m'] ?? '°C'),
            ],
            'daily'   => [],
        ];

        $daily = is_array($raw['daily'] ?? null) ? $raw['daily'] : [];
        $times = is_array($daily['time'] ?? null) ? $daily['time'] : [];
        foreach ($times as $i => $day) {
            $dcode = (int) ($daily['weather_code'][$i] ?? 0);
            [$dicon, $ddesc] = self::codeInfo($dcode);
            $out['daily'][] = [
                'date'   => (string) $day,
                'icon'   => $dicon,
                'desc'   => $ddesc,
                'max'    => (int) round((float) ($daily['temperature_2m_max'][$i] ?? 0)),
                'min'    => (int) round((float) ($daily['temperature_2m_min'][$i] ?? 0)),
                'precip' => (int) ($daily['precipitation_probability_max'][$i] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Normalizza il payload completo Open-Meteo per la pagina Meteo.
     *
     * @param  array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalizeFull(array $raw, string $label): array
    {
        $cur    = is_array($raw['current'] ?? null) ? $raw['current'] : [];
        $cUnits = is_array($raw['current_units'] ?? null) ? $raw['current_units'] : [];
        $code   = (int) ($cur['weather_code'] ?? 0);
        [$icon, $desc] = self::codeInfo($code);

        $current = [
            'temp'           => (int) round((float) ($cur['temperature_2m'] ?? 0)),
            'apparent'       => (int) round((float) ($cur['apparent_temperature'] ?? 0)),
            'humidity'       => (int) round((float) ($cur['relative_humidity_2m'] ?? 0)),
            'code'           => $code,
            'icon'           => $icon,
            'description'    => $desc,
            'is_day'         => (int) ($cur['is_day'] ?? 1) === 1,
            'sky'            => self::skyClass($code, (int) ($cur['is_day'] ?? 1) === 1),
            'precipitation'  => round((float) ($cur['precipitation'] ?? 0), 1),
            'rain'           => round((float) ($cur['rain'] ?? 0), 1),
            'snowfall'       => round((float) ($cur['snowfall'] ?? 0), 1),
            'cloud_cover'    => (int) round((float) ($cur['cloud_cover'] ?? 0)),
            'pressure'       => (int) round((float) ($cur['pressure_msl'] ?? 0)),
            'wind'           => (int) round((float) ($cur['wind_speed_10m'] ?? 0)),
            'wind_dir'       => (int) round((float) ($cur['wind_direction_10m'] ?? 0)),
            'wind_dir_label' => self::degToCompass((float) ($cur['wind_direction_10m'] ?? 0)),
            'gusts'          => (int) round((float) ($cur['wind_gusts_10m'] ?? 0)),
            'temp_unit'      => (string) ($cUnits['temperature_2m'] ?? '°C'),
            'wind_unit'      => (string) ($cUnits['wind_speed_10m'] ?? 'km/h'),
            'press_unit'     => (string) ($cUnits['pressure_msl'] ?? 'hPa'),
            'precip_unit'    => (string) ($cUnits['precipitation'] ?? 'mm'),
        ];

        $updatedLabel = isset($cur['time']) ? date('H:i', (int) strtotime((string) $cur['time'])) : date('H:i');
        $nowTs        = isset($cur['time']) ? (int) strtotime((string) $cur['time']) : time();

        // ── Daily (7 giorni) ─────────────────────────────────────
        $daily  = is_array($raw['daily'] ?? null) ? $raw['daily'] : [];
        $dTimes = is_array($daily['time'] ?? null) ? $daily['time'] : [];
        $maxArr = array_map('floatval', is_array($daily['temperature_2m_max'] ?? null) ? $daily['temperature_2m_max'] : []);
        $minArr = array_map('floatval', is_array($daily['temperature_2m_min'] ?? null) ? $daily['temperature_2m_min'] : []);
        $globalMax = !empty($maxArr) ? max($maxArr) : 1.0;
        $globalMin = !empty($minArr) ? min($minArr) : 0.0;
        $span = ($globalMax - $globalMin) ?: 1.0;

        $days = [];
        foreach ($dTimes as $i => $d) {
            $dcode = (int) ($daily['weather_code'][$i] ?? 0);
            [$dicon, $ddesc] = self::codeInfo($dcode);
            $tmax = (float) ($maxArr[$i] ?? 0);
            $tmin = (float) ($minArr[$i] ?? 0);
            $ts   = (int) strtotime((string) $d . ' 12:00:00');
            $days[] = [
                'day_label'    => self::dayLabelIt($ts, (int) $i),
                'date_label'   => date('d/m', $ts),
                'icon'         => $dicon,
                'desc'         => $ddesc,
                'code'         => $dcode,
                'sky'          => self::skyClass($dcode, true),
                'tmax'         => (int) round($tmax),
                'tmin'         => (int) round($tmin),
                'precip_sum'   => round((float) ($daily['precipitation_sum'][$i] ?? 0), 1),
                'precip_prob'  => (int) ($daily['precipitation_probability_max'][$i] ?? 0),
                'precip_hours' => (int) round((float) ($daily['precipitation_hours'][$i] ?? 0)),
                'wind_max'     => (int) round((float) ($daily['wind_speed_10m_max'][$i] ?? 0)),
                'gusts_max'    => (int) round((float) ($daily['wind_gusts_10m_max'][$i] ?? 0)),
                'wind_dir'     => self::degToCompass((float) ($daily['wind_direction_10m_dominant'][$i] ?? 0)),
                'uv_max'       => round((float) ($daily['uv_index_max'][$i] ?? 0), 1),
                'sunrise'      => isset($daily['sunrise'][$i]) ? date('H:i', (int) strtotime((string) $daily['sunrise'][$i])) : '—',
                'sunset'       => isset($daily['sunset'][$i]) ? date('H:i', (int) strtotime((string) $daily['sunset'][$i])) : '—',
                'daylight'     => self::hms((int) ($daily['daylight_duration'][$i] ?? 0)),
                'sunshine'     => self::hms((int) ($daily['sunshine_duration'][$i] ?? 0)),
                'radiation'    => round((float) ($daily['shortwave_radiation_sum'][$i] ?? 0), 1),
                'range_start'  => round((($tmin - $globalMin) / $span) * 100, 1),
                'range_width'  => max(6.0, round((($tmax - $tmin) / $span) * 100, 1)),
            ];
        }

        // Progresso del sole per "oggi" (alimenta l'arco alba→tramonto) + fase del cielo
        // (dawn/day/golden/dusk/night) per i gradienti orari dell'hero.
        $phase = ($current['is_day'] ?? true) ? 'day' : 'night';
        if (!empty($days)) {
            $sr = isset($daily['sunrise'][0]) ? (int) strtotime((string) $daily['sunrise'][0]) : 0;
            $ss = isset($daily['sunset'][0]) ? (int) strtotime((string) $daily['sunset'][0]) : 0;
            if ($ss > $sr) {
                $days[0]['sun_progress'] = max(0.0, min(1.0, round(($nowTs - $sr) / ($ss - $sr), 4)));
                $days[0]['is_daylight']  = ($nowTs >= $sr && $nowTs <= $ss);

                if ($nowTs < $sr || $nowTs > $ss) {
                    $phase = 'night';
                } else {
                    $afterSunrise = ($nowTs - $sr) / 60; // minuti dall'alba
                    $beforeSunset = ($ss - $nowTs) / 60; // minuti al tramonto
                    $phase = match (true) {
                        $afterSunrise <= 60  => 'dawn',
                        $beforeSunset <= 40  => 'dusk',
                        $beforeSunset <= 110 => 'golden',
                        default              => 'day',
                    };
                }
            } else {
                $days[0]['sun_progress'] = 0.0;
                $days[0]['is_daylight']  = false;
            }
        }
        $current['phase'] = $phase;

        // ── Hourly (prossime 24 ore) ─────────────────────────────
        $hourly = is_array($raw['hourly'] ?? null) ? $raw['hourly'] : [];
        $hTimes = is_array($hourly['time'] ?? null) ? $hourly['time'] : [];
        $startIdx = 0;
        foreach ($hTimes as $i => $t) {
            if ((int) strtotime((string) $t) >= $nowTs - 1800) {
                $startIdx = (int) $i;
                break;
            }
        }
        $hours = [];
        $end = min($startIdx + 24, count($hTimes));
        for ($i = $startIdx; $i < $end; $i++) {
            $hcode = (int) ($hourly['weather_code'][$i] ?? 0);
            [$hicon] = self::codeInfo($hcode);
            $hts = (int) strtotime((string) $hTimes[$i]);
            $hours[] = [
                'hour_label'  => $i === $startIdx ? 'Ora' : date('H', $hts) . ':00',
                'temp'        => (int) round((float) ($hourly['temperature_2m'][$i] ?? 0)),
                'apparent'    => (int) round((float) ($hourly['apparent_temperature'][$i] ?? 0)),
                'icon'        => $hicon,
                'precip_prob' => (int) ($hourly['precipitation_probability'][$i] ?? 0),
                'wind'        => (int) round((float) ($hourly['wind_speed_10m'][$i] ?? 0)),
                'humidity'    => (int) round((float) ($hourly['relative_humidity_2m'][$i] ?? 0)),
                'visibility'  => (int) round((float) ($hourly['visibility'][$i] ?? 0) / 1000),
                'is_day'      => (int) ($hourly['is_day'][$i] ?? 1) === 1,
            ];
        }

        return [
            'offline'       => false,
            'label'         => $label,
            'updated_label' => $updatedLabel,
            'timezone'      => (string) ($raw['timezone'] ?? ''),
            'current'       => $current,
            'today'         => $days[0] ?? [],
            'days'          => $days,
            'hours'         => $hours,
        ];
    }

    private static function degToCompass(float $deg): string
    {
        $dirs = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
        return $dirs[((int) round($deg / 45)) % 8] ?? 'N';
    }

    /**
     * Mappa il codice meteo WMO nella classe "cielo" usata dalla UI (.mt-sky-*).
     * Riusata sia per le condizioni attuali sia per le card dei 7 giorni.
     */
    public static function skyClass(int $code, bool $isDay, bool $offline = false): string
    {
        if ($offline) {
            return 'mt-sky-cloud';
        }
        if (!$isDay) {
            return 'mt-sky-night';
        }
        return match (true) {
            in_array($code, [0, 1], true)                                                 => 'mt-sky-clear',
            in_array($code, [2, 3, 45, 48], true)                                         => 'mt-sky-cloud',
            in_array($code, [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82], true)   => 'mt-sky-rain',
            in_array($code, [71, 73, 75, 77, 85, 86], true)                               => 'mt-sky-snow',
            in_array($code, [95, 96, 99], true)                                           => 'mt-sky-storm',
            default                                                                        => 'mt-sky-default',
        };
    }

    private static function dayLabelIt(int $ts, int $index): string
    {
        if ($index === 0) {
            return 'Oggi';
        }
        if ($index === 1) {
            return 'Domani';
        }
        return ['Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'][(int) date('w', $ts)];
    }

    private static function hms(int $seconds): string
    {
        $seconds = max(0, $seconds);
        return intdiv($seconds, 3600) . 'h ' . str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT) . 'm';
    }

    /**
     * Mappa il codice meteo WMO (Open-Meteo) in [icona FontAwesome, descrizione IT].
     *
     * @return array{0: string, 1: string}
     */
    public static function codeInfo(int $code): array
    {
        return match (true) {
            $code === 0                                     => ['fa-sun', 'Sereno'],
            $code === 1                                     => ['fa-cloud-sun', 'Prevalentemente sereno'],
            $code === 2                                     => ['fa-cloud-sun', 'Parzialmente nuvoloso'],
            $code === 3                                     => ['fa-cloud', 'Nuvoloso'],
            in_array($code, [45, 48], true)                 => ['fa-smog', 'Nebbia'],
            in_array($code, [51, 53, 55, 56, 57], true)     => ['fa-cloud-rain', 'Pioggerella'],
            in_array($code, [61, 63, 65, 66, 67], true)     => ['fa-cloud-showers-heavy', 'Pioggia'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['fa-snowflake', 'Neve'],
            in_array($code, [80, 81, 82], true)             => ['fa-cloud-showers-heavy', 'Rovesci'],
            in_array($code, [95, 96, 99], true)             => ['fa-cloud-bolt', 'Temporale'],
            default                                         => ['fa-cloud', 'Variabile'],
        };
    }
}
