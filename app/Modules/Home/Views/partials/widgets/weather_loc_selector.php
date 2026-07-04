<?php
/**
 * Selettore località per il widget meteo (lista).
 * Ricerca geocoding via HTMX; la scelta salva e fa il refresh dei widget.
 */
?>
<div class="hm-weather-loc" hx-headers='<?= e(json_encode(['X-CSRF-Token' => csrf_token()])) ?>'>
    <input type="search"
           class="form-control form-control-sm hm-weather-loc-input"
           name="q"
           placeholder="<?= e(t('home.weather.change_location')) ?>"
           autocomplete="off"
           aria-label="<?= e(t('home.weather.change_location_aria')) ?>"
           hx-get="<?= e(route('home.weather.search')) ?>"
           hx-trigger="keyup changed delay:400ms"
           hx-target="#hm-weather-loc-results"
           hx-swap="innerHTML">
    <div id="hm-weather-loc-results" class="hm-weather-loc-results"></div>
</div>
