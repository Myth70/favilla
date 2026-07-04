<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/meteo.css'); ?>
<?php $view->pushScript('js/apexcharts.min.js'); ?>
<?php $view->pushScript('js/meteo.js'); ?>
<?php $view->start('content'); ?>
<?php
/** @var array $forecast */
$offline = !empty($forecast['offline']);
$label   = (string) ($forecast['label'] ?? t('home.weather.label'));
$cur     = is_array($forecast['current'] ?? null) ? $forecast['current'] : [];
$today   = is_array($forecast['today'] ?? null) ? $forecast['today'] : [];
$days    = is_array($forecast['days'] ?? null) ? $forecast['days'] : [];
$hours   = is_array($forecast['hours'] ?? null) ? $forecast['hours'] : [];
$tz      = (string) ($forecast['timezone'] ?? '');
$updated = (string) ($forecast['updated_label'] ?? '');
$tUnit   = (string) ($cur['temp_unit'] ?? '°C');
$wUnit   = (string) ($cur['wind_unit'] ?? 'km/h');

// Classe "cielo" risolta a monte da WeatherService::skyClass().
$skyClass = $offline ? 'mt-sky-cloud' : (string) ($cur['sky'] ?? 'mt-sky-default');
// Fase oraria (dawn/day/golden/dusk/night) per i gradienti caldi/crepuscolari dell'hero.
$phaseClass = $offline ? '' : 'mt-phase-' . (string) ($cur['phase'] ?? 'day');

// "Mood" del cielo (tinta + saturazione) propagato a tutta la pagina via custom property.
$moodMap = [
    'mt-sky-clear'   => [40, 95],
    'mt-sky-default' => [218, 78],
    'mt-sky-cloud'   => [215, 30],
    'mt-sky-rain'    => [199, 85],
    'mt-sky-snow'    => [195, 70],
    'mt-sky-storm'   => [262, 60],
    'mt-sky-night'   => [230, 55],
];
[$moodH, $moodS] = $moodMap[$skyClass] ?? [218, 78];

// Hue termica (blu freddo → rosso caldo) per striscia oraria e barre 7 giorni.
$tempHue = static function (float $t): int {
    $p = max(0.0, min(1.0, ($t + 5) / 40)); // intervallo utile -5°C..35°C
    return (int) round(210 - $p * 202);
};

$heroIcon = 'fa-solid ' . (string) ($cur['icon'] ?? 'fa-cloud-sun');
if ($offline) {
    $heroSubtitle = '<i class="fa-solid fa-location-dot me-1"></i>' . e($label) . ' &middot; <span class="text-warning">' . e(t('home.weather.service_down')) . '</span>';
} else {
    // L'hero resta sobrio: temperatura e descrizione vivono nella card scenica sotto.
    $heroSubtitle = '<i class="fa-solid fa-location-dot me-1"></i>' . e($label);
}
$heroButtons = '<a href="' . e(route('home')) . '" class="btn btn-sm btn-light rounded-pill shadow-sm">'
    . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('home.weather.home')) . '</a>';
?>

<div class="container-fluid mt-page" style="--mt-mood-h: <?= (int) $moodH ?>; --mt-mood-s: <?= (int) $moodS ?>%;">

    <?php $view->include('partials/pf-hero-module', [
        'moduleName'     => t('home.weather.label'),
        'moduleIcon'     => $heroIcon,
        'moduleSubtitle' => $heroSubtitle,
        'moduleButtons'  => $heroButtons,
    ]); ?>

    <!-- Toolbar: aggiornamento + ricerca località -->
    <div class="mt-toolbar">
        <div class="mt-updated">
            <i class="fa-regular fa-clock me-1"></i><?= e(t('home.weather.updated_at', ['time' => $updated ?: '—'])) ?><?= $tz !== '' ? ' &middot; ' . e($tz) : '' ?>
        </div>
        <div class="mt-locsearch" hx-headers='<?= e(json_encode(['X-CSRF-Token' => csrf_token()])) ?>'>
            <i class="fa-solid fa-magnifying-glass mt-locsearch-icon"></i>
            <input type="search" class="mt-locsearch-input" name="q" placeholder="<?= e(t('home.weather.search_city')) ?>" autocomplete="off"
                   aria-label="<?= e(t('home.weather.search_aria')) ?>"
                   hx-get="<?= e(route('home.weather.search')) ?>"
                   hx-trigger="keyup changed delay:400ms"
                   hx-target="#mt-loc-results" hx-swap="innerHTML">
            <div id="mt-loc-results" class="hm-weather-loc-results mt-locsearch-results"></div>
        </div>
    </div>

    <?php if ($offline): ?>
        <div class="mt-offline">
            <div class="mt-offline-icon"><i class="fa-solid fa-cloud-slash"></i></div>
            <h3><?= e(t('home.weather.offline_title')) ?></h3>
            <p><?= t('home.weather.offline_body') ?></p>
        </div>
    <?php else: ?>

        <!-- ░░ Condizioni attuali ░░ -->
        <section class="mt-current <?= e($skyClass) ?> <?= e($phaseClass) ?>">
            <div class="mt-current-glow" aria-hidden="true"></div>
            <div class="mt-sky-fx" aria-hidden="true">
                <span class="mt-fx-sun"></span>
                <span class="mt-fx-cloud mt-fx-cloud--1"></span>
                <span class="mt-fx-cloud mt-fx-cloud--2"></span>
                <span class="mt-fx-cloud mt-fx-cloud--3"></span>
                <span class="mt-fx-precip"></span>
                <span class="mt-fx-stars"></span>
                <span class="mt-fx-flash"></span>
            </div>
            <div class="mt-current-main">
                <div class="mt-current-icon"><i class="fa-solid <?= e((string) ($cur['icon'] ?? 'fa-cloud')) ?>"></i></div>
                <div class="mt-current-headline">
                    <div class="mt-current-temp"><?= (int) ($cur['temp'] ?? 0) ?><span class="mt-deg"><?= e($tUnit) ?></span></div>
                    <div class="mt-current-desc"><?= e((string) ($cur['description'] ?? '')) ?></div>
                    <div class="mt-current-sub">
                        <?= e(t('home.weather.apparent')) ?> <strong><?= (int) ($cur['apparent'] ?? 0) ?><?= e($tUnit) ?></strong>
                        &middot; <?= e(t('home.weather.min')) ?> <?= (int) ($today['tmin'] ?? 0) ?>° / <?= e(t('home.weather.max')) ?> <?= (int) ($today['tmax'] ?? 0) ?>°
                    </div>
                </div>
            </div>
            <?php
            $nowStats = [
                ['fa-droplet',   t('home.weather.humidity'), ($cur['humidity'] ?? 0) . '%'],
                ['fa-wind',      t('home.weather.wind'),     ((int) ($cur['wind'] ?? 0)) . ' ' . $wUnit . ' ' . ($cur['wind_dir_label'] ?? '')],
                ['fa-tornado',   t('home.weather.gusts'),    ((int) ($cur['gusts'] ?? 0)) . ' ' . $wUnit],
                ['fa-gauge-high',t('home.weather.pressure'), ((int) ($cur['pressure'] ?? 0)) . ' ' . ($cur['press_unit'] ?? 'hPa')],
                ['fa-cloud',     t('home.weather.cloud'),    ($cur['cloud_cover'] ?? 0) . '%'],
                ['fa-umbrella',  t('home.weather.rain_now'), ($cur['precipitation'] ?? 0) . ' ' . ($cur['precip_unit'] ?? 'mm')],
            ];
            ?>
            <div class="mt-current-stats">
                <?php foreach ($nowStats as [$ic, $lab, $val]): ?>
                    <div class="mt-now">
                        <span class="mt-now-ic">
                            <i class="fa-solid <?= e($ic) ?>"></i>
                            <?php if ($ic === 'fa-wind'): ?>
                                <i class="fa-solid fa-location-arrow mt-wind-arrow" style="transform: rotate(<?= (int) (($cur['wind_dir'] ?? 0) - 45) ?>deg);"></i>
                            <?php endif; ?>
                        </span>
                        <span class="mt-now-lab"><?= e($lab) ?></span>
                        <span class="mt-now-val"><?= e($val) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ░░ Oggi in dettaglio (bento) ░░ -->
        <div class="mt-section-head">
            <div class="mt-section-ic"><i class="fa-solid fa-calendar-day"></i></div>
            <div>
                <h2><?= e(t('home.weather.today_detail')) ?></h2>
                <p><?= e(t('home.weather.today_detail_sub', ['label' => $label])) ?></p>
            </div>
        </div>
        <?php
        $uv         = (float) ($today['uv_max'] ?? 0);
        $uvFrac     = max(0.0, min(1.0, $uv / 11));
        $uvRisk     = $uv >= 11 ? t('home.weather.uv_extreme') : ($uv >= 8 ? t('home.weather.uv_very_high') : ($uv >= 6 ? t('home.weather.uv_high') : ($uv >= 3 ? t('home.weather.uv_moderate') : t('home.weather.uv_low'))));
        $isDaylight = !empty($today['is_daylight']);
        $sunP       = (float) ($today['sun_progress'] ?? 0);
        $sunX       = round(50 - 44 * cos(M_PI * $sunP), 2);
        $sunY       = round(50 - 44 * sin(M_PI * $sunP), 2);
        $windDeg    = (int) ($cur['wind_dir'] ?? 0);
        // Escursione termica: posiziona la barra su scala fissa -5°..35° (come la hue oraria).
        $rMin   = (int) ($today['tmin'] ?? 0);
        $rMax   = (int) ($today['tmax'] ?? 0);
        $rLeft  = max(0.0, min(100.0, ($rMin + 5) / 40 * 100));
        $rRight = max(0.0, min(100.0, ($rMax + 5) / 40 * 100));
        $rWidth = max(4.0, $rRight - $rLeft);
        ?>
        <div class="mt-today-bento">

            <!-- Arco solare: alba → ora → tramonto -->
            <div class="mt-card mt-bento mt-bento-sun<?= $isDaylight ? '' : ' mt-bento-sun--night' ?>">
                <div class="mt-bento-head"><i class="fa-solid fa-sun"></i><span><?= e(t('home.weather.sun_path')) ?></span></div>
                <div class="mt-sun-body">
                    <div class="mt-arc">
                        <svg viewBox="0 0 100 58" class="mt-arc-svg" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                            <defs>
                                <linearGradient id="mtArcGrad" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0" stop-color="#f59e0b"/>
                                    <stop offset=".5" stop-color="#fcd34d"/>
                                    <stop offset="1" stop-color="#f59e0b"/>
                                </linearGradient>
                            </defs>
                            <path class="mt-arc-track" d="M6 50 A44 44 0 0 1 94 50"/>
                            <?php if ($isDaylight): ?>
                                <path class="mt-arc-elapsed" d="M6 50 A44 44 0 0 1 <?= $sunX ?> <?= $sunY ?>"/>
                                <circle class="mt-arc-sun" cx="<?= $sunX ?>" cy="<?= $sunY ?>" r="4.6"/>
                            <?php else: ?>
                                <circle class="mt-arc-moon" cx="50" cy="13" r="6"/>
                            <?php endif; ?>
                        </svg>
                    </div>
                    <div class="mt-sun-stats">
                        <div class="mt-sun-stat">
                            <i class="fa-solid fa-arrow-up-long"></i>
                            <div class="mt-sun-stat-txt">
                                <span class="mt-sun-lab"><?= e(t('home.weather.sunrise')) ?></span>
                                <span class="mt-sun-val"><?= e((string) ($today['sunrise'] ?? '—')) ?></span>
                            </div>
                        </div>
                        <div class="mt-sun-stat">
                            <i class="fa-solid fa-arrow-down-long"></i>
                            <div class="mt-sun-stat-txt">
                                <span class="mt-sun-lab"><?= e(t('home.weather.sunset')) ?></span>
                                <span class="mt-sun-val"><?= e((string) ($today['sunset'] ?? '—')) ?></span>
                            </div>
                        </div>
                        <div class="mt-sun-stat">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <div class="mt-sun-stat-txt">
                                <span class="mt-sun-lab"><?= e(t('home.weather.daylight')) ?></span>
                                <span class="mt-sun-val"><?= e((string) ($today['daylight'] ?? '—')) ?></span>
                            </div>
                        </div>
                        <div class="mt-sun-stat">
                            <i class="fa-solid fa-cloud-sun"></i>
                            <div class="mt-sun-stat-txt">
                                <span class="mt-sun-lab"><?= e(t('home.weather.sunshine')) ?></span>
                                <span class="mt-sun-val"><?= e((string) ($today['sunshine'] ?? '—')) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gauge indice UV -->
            <div class="mt-card mt-bento mt-bento-uv" style="--uv: <?= $uvFrac ?>;">
                <div class="mt-bento-head"><i class="fa-solid fa-radiation"></i><span><?= e(t('home.weather.uv_index')) ?></span></div>
                <div class="mt-uv-readout">
                    <span class="mt-uv-num"><?= (int) round($uv) ?></span>
                    <span class="mt-uv-risk"><?= e($uvRisk) ?></span>
                </div>
                <div class="mt-uv-bar"><span class="mt-uv-marker"></span></div>
                <div class="mt-uv-scale"><span>0</span><span>3</span><span>6</span><span>8</span><span>11+</span></div>
            </div>

            <!-- Bussola del vento -->
            <div class="mt-card mt-bento mt-bento-wind" style="--wind-deg: <?= $windDeg ?>deg;">
                <div class="mt-bento-head"><i class="fa-solid fa-wind"></i><span><?= e(t('home.weather.wind_card')) ?></span></div>
                <div class="mt-compass" title="<?= e((string) ($cur['wind_dir_label'] ?? '')) ?>">
                    <span class="mt-compass-rose"></span>
                    <span class="mt-compass-tick mt-compass-tick--n"><?= e(t('home.weather.compass_n')) ?></span>
                    <span class="mt-compass-tick mt-compass-tick--e"><?= e(t('home.weather.compass_e')) ?></span>
                    <span class="mt-compass-tick mt-compass-tick--s"><?= e(t('home.weather.compass_s')) ?></span>
                    <span class="mt-compass-tick mt-compass-tick--o"><?= e(t('home.weather.compass_w')) ?></span>
                    <span class="mt-compass-needle"></span>
                    <span class="mt-compass-core"><strong><?= (int) ($cur['wind'] ?? 0) ?></strong><small><?= e($wUnit) ?></small></span>
                </div>
                <div class="mt-bento-foot">
                    <span><i class="fa-solid fa-compass"></i><?= e((string) ($cur['wind_dir_label'] ?? '')) ?></span>
                    <span><i class="fa-solid fa-tornado"></i><?= (int) ($cur['gusts'] ?? 0) ?> <?= e($wUnit) ?></span>
                </div>
            </div>

            <!-- Escursione termica -->
            <div class="mt-card mt-bento mt-bento-range">
                <div class="mt-bento-head"><i class="fa-solid fa-temperature-half"></i><span><?= e(t('home.weather.range')) ?></span></div>
                <div class="mt-range-readout">
                    <span class="mt-range-min"><?= $rMin ?>°</span>
                    <span class="mt-range-arrow"><i class="fa-solid fa-arrow-right-long"></i></span>
                    <span class="mt-range-max"><?= $rMax ?>°</span>
                </div>
                <div class="mt-range-bar">
                    <span class="mt-range-fill" style="left: <?= round($rLeft, 1) ?>%; width: <?= round($rWidth, 1) ?>%; --c1: hsl(<?= $tempHue((float) $rMin) ?> 85% 55%); --c2: hsl(<?= $tempHue((float) $rMax) ?> 90% 55%);"></span>
                </div>
                <div class="mt-bento-foot">
                    <span><i class="fa-solid fa-snowflake"></i><?= e(t('home.weather.min')) ?> <?= $rMin ?>°</span>
                    <span><?= e(t('home.weather.max')) ?> <?= $rMax ?>°<i class="fa-solid fa-sun"></i></span>
                </div>
            </div>

            <!-- Pioggia -->
            <div class="mt-card mt-bento mt-bento-rain">
                <div class="mt-bento-head"><i class="fa-solid fa-umbrella"></i><span><?= e(t('home.weather.rain')) ?></span></div>
                <div class="mt-rain-readout">
                    <span class="mt-rain-num"><?= e((string) ($today['precip_sum'] ?? 0)) ?></span>
                    <span class="mt-rain-unit">mm</span>
                </div>
                <div class="mt-rain-bar"><span class="mt-rain-fill" style="width: <?= (int) ($today['precip_prob'] ?? 0) ?>%;"></span></div>
                <div class="mt-bento-foot">
                    <span><i class="fa-solid fa-percent"></i><?= e(t('home.weather.rain_prob', ['count' => (int) ($today['precip_prob'] ?? 0)])) ?></span>
                    <span><i class="fa-regular fa-clock"></i><?= e(t('home.weather.rain_hours', ['count' => (int) ($today['precip_hours'] ?? 0)])) ?></span>
                </div>
            </div>
        </div>

        <!-- ░░ Prossime 24 ore ░░ -->
        <div class="mt-section-head">
            <div class="mt-section-ic"><i class="fa-solid fa-chart-line"></i></div>
            <div>
                <h2><?= e(t('home.weather.next24')) ?></h2>
                <p><?= e(t('home.weather.next24_sub')) ?></p>
            </div>
        </div>
        <?php
        $chartData = array_map(static fn(array $h): array => [
            't'    => $h['hour_label'] ?? '',
            'temp' => (int) ($h['temp'] ?? 0),
            'app'  => (int) ($h['apparent'] ?? 0),
            'prob' => (int) ($h['precip_prob'] ?? 0),
            'vis'  => (int) ($h['visibility'] ?? 0),
            'day'  => !empty($h['is_day']),
        ], $hours);
        ?>
        <div class="mt-card mt-chart-card">
            <div id="mt-hourly-chart" data-meteo-hours='<?= e(json_encode($chartData)) ?>'></div>
            <div class="mt-hourly-strip">
                <?php foreach ($hours as $idx => $h): ?>
                    <div class="mt-hour<?= $idx === 0 ? ' mt-hour--now' : '' ?>" style="--t-hue: <?= $tempHue((float) ($h['temp'] ?? 0)) ?>;">
                        <span class="mt-hour-time"><?= e((string) ($h['hour_label'] ?? '')) ?></span>
                        <i class="fa-solid <?= e((string) ($h['icon'] ?? 'fa-cloud')) ?> mt-hour-icon"></i>
                        <span class="mt-hour-temp"><?= (int) ($h['temp'] ?? 0) ?>°</span>
                        <span class="mt-hour-meta"><i class="fa-solid fa-droplet"></i><?= (int) ($h['precip_prob'] ?? 0) ?>%</span>
                        <span class="mt-hour-meta"><i class="fa-solid fa-wind"></i><?= (int) ($h['wind'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ░░ Prossimi 7 giorni ░░ -->
        <div class="mt-section-head">
            <div class="mt-section-ic"><i class="fa-solid fa-calendar-week"></i></div>
            <div>
                <h2><?= e(t('home.weather.next7')) ?></h2>
                <p><?= e(t('home.weather.next7_sub')) ?></p>
            </div>
        </div>
        <?php if (!empty($days)): ?>
        <?php
        // Scala termica condivisa della settimana (curva + barre delle righe).
        $weekMax = max(array_map(static fn(array $d): int => (int) ($d['tmax'] ?? 0), $days));
        $weekMin = min(array_map(static fn(array $d): int => (int) ($d['tmin'] ?? 0), $days));
        $domMax  = $weekMax + 1;
        $domMin  = $weekMin - 1;
        $domSpan = ($domMax - $domMin) ?: 1;
        $n       = max(1, count($days));
        $svgW    = ($n - 1) * 100 + 100;       // 50px di padding per lato
        $yTop = 16.0; $yBot = 56.0;            // area di plottaggio entro un viewBox alto 80 (aspetto largo → card bassa)
        $tempY = static fn(float $t): float => round($yBot - (($t - $domMin) / $domSpan) * ($yBot - $yTop), 1);

        // Punti max/min e curve morbide (Catmull-Rom → Bézier cubica).
        $maxPts = $minPts = [];
        foreach ($days as $i => $d) {
            $x = 50 + $i * 100;
            $maxPts[] = [$x, $tempY((float) ($d['tmax'] ?? 0))];
            $minPts[] = [$x, $tempY((float) ($d['tmin'] ?? 0))];
        }
        $catmull = static function (array $pts, bool $lineTo = false): string {
            $c = count($pts);
            if ($c === 0) { return ''; }
            $d = ($lineTo ? 'L' : 'M') . $pts[0][0] . ' ' . $pts[0][1];
            for ($i = 0; $i < $c - 1; $i++) {
                $p0 = $pts[$i - 1] ?? $pts[$i];
                $p1 = $pts[$i];
                $p2 = $pts[$i + 1];
                $p3 = $pts[$i + 2] ?? $pts[$i + 1];
                $d .= ' C' . round($p1[0] + ($p2[0] - $p0[0]) / 6, 1) . ' ' . round($p1[1] + ($p2[1] - $p0[1]) / 6, 1)
                    . ' ' . round($p2[0] - ($p3[0] - $p1[0]) / 6, 1) . ' ' . round($p2[1] - ($p3[1] - $p1[1]) / 6, 1)
                    . ' ' . $p2[0] . ' ' . $p2[1];
            }
            return $d;
        };
        $pathMax = $catmull($maxPts);
        $pathMin = $catmull($minPts);
        $band    = $pathMax . ' ' . $catmull(array_reverse($minPts), true) . ' Z';
        $hueMax  = $tempHue((float) $weekMax);
        $hueMin  = $tempHue((float) $weekMin);
        ?>
        <div class="mt-card mt-trend">
            <svg class="mt-trend-svg" viewBox="0 0 <?= $svgW ?> 80" role="img"
                 aria-label="<?= e(t('home.weather.trend_aria', ['count' => $n])) ?>">
                <defs>
                    <linearGradient id="mtTrendBand" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="hsl(<?= $hueMax ?> 90% 55%)" stop-opacity=".30"/>
                        <stop offset="1" stop-color="hsl(<?= $hueMin ?> 85% 55%)" stop-opacity=".05"/>
                    </linearGradient>
                </defs>
                <path class="mt-trend-band" d="<?= e($band) ?>" fill="url(#mtTrendBand)"/>
                <path class="mt-trend-line" d="<?= e($pathMax) ?>" style="stroke: hsl(<?= $hueMax ?> 90% 52%);"/>
                <path class="mt-trend-line" d="<?= e($pathMin) ?>" style="stroke: hsl(<?= $hueMin ?> 80% 56%);"/>
                <?php foreach ($days as $i => $d): $x = 50 + $i * 100; ?>
                    <?php if ($i === 0): ?>
                        <line class="mt-trend-now" x1="<?= $x ?>" y1="7" x2="<?= $x ?>" y2="62"/>
                    <?php endif; ?>
                    <circle class="mt-trend-dot" cx="<?= $x ?>" cy="<?= $tempY((float) ($d['tmax'] ?? 0)) ?>" r="2.4" style="fill: hsl(<?= $tempHue((float) ($d['tmax'] ?? 0)) ?> 90% 52%);"/>
                    <circle class="mt-trend-dot" cx="<?= $x ?>" cy="<?= $tempY((float) ($d['tmin'] ?? 0)) ?>" r="2.4" style="fill: hsl(<?= $tempHue((float) ($d['tmin'] ?? 0)) ?> 80% 56%);"/>
                    <text class="mt-trend-vmax" x="<?= $x ?>" y="<?= $tempY((float) ($d['tmax'] ?? 0)) - 6 ?>"><?= (int) ($d['tmax'] ?? 0) ?>°</text>
                    <text class="mt-trend-vmin" x="<?= $x ?>" y="<?= $tempY((float) ($d['tmin'] ?? 0)) + 9 ?>"><?= (int) ($d['tmin'] ?? 0) ?>°</text>
                    <text class="mt-trend-day<?= $i === 0 ? ' mt-trend-day--today' : '' ?>" x="<?= $x ?>" y="76"><?= e((string) ($d['day_label'] ?? '')) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>

        <div class="mt-card mt-week">
            <?php foreach ($days as $i => $d):
                $dMinHue = $tempHue((float) ($d['tmin'] ?? 0));
                $dMaxHue = $tempHue((float) ($d['tmax'] ?? 0));
            ?>
                <div class="mt-drow<?= $i === 0 ? ' mt-drow--today' : '' ?>">
                    <div class="mt-drow-day">
                        <strong><?= e((string) ($d['day_label'] ?? '')) ?></strong>
                        <span><?= e((string) ($d['date_label'] ?? '')) ?></span>
                    </div>
                    <div class="mt-drow-sky">
                        <i class="fa-solid <?= e((string) ($d['icon'] ?? 'fa-cloud')) ?>"></i>
                        <span class="mt-drow-desc"><?= e((string) ($d['desc'] ?? '')) ?></span>
                    </div>
                    <span class="mt-drow-min"><?= (int) ($d['tmin'] ?? 0) ?>°</span>
                    <span class="mt-drow-track">
                        <span class="mt-drow-bar" style="left: <?= e((string) ($d['range_start'] ?? 0)) ?>%; width: <?= e((string) ($d['range_width'] ?? 0)) ?>%; --c1: hsl(<?= $dMinHue ?> 85% 55%); --c2: hsl(<?= $dMaxHue ?> 90% 55%);"></span>
                    </span>
                    <span class="mt-drow-max"><?= (int) ($d['tmax'] ?? 0) ?>°</span>
                    <div class="mt-drow-extra">
                        <span title="<?= e(t('home.weather.rain_prob_tip')) ?>"><i class="fa-solid fa-umbrella"></i><?= (int) ($d['precip_prob'] ?? 0) ?>%</span>
                        <span title="<?= e(t('home.weather.wind_max_tip')) ?>"><i class="fa-solid fa-wind"></i><?= (int) ($d['wind_max'] ?? 0) ?></span>
                        <span title="<?= e(t('home.weather.uv_max_tip')) ?>"><i class="fa-solid fa-radiation"></i><?= e((string) ($d['uv_max'] ?? 0)) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-source">
            <?= t('home.weather.source', ['time' => e($updated ?: '—')]) ?><?= $tz !== '' ? ' (' . e($tz) . ')' : '' ?>
        </div>

    <?php endif; ?>
</div>

<?php $view->end(); ?>
