<?php
/**
 * Risultati ricerca località (geocoding). Variabili: $results
 * Ogni voce salva la località via HTMX (X-CSRF-Token ereditato dal wrapper).
 */
?>
<?php if (empty($results)): ?>
    <div class="hm-weather-loc-empty"><?= e(t('home.weather.no_locations')) ?></div>
<?php else: ?>
    <?php foreach ($results as $r): ?>
        <?php
        $payload = json_encode([
            'name'         => $r['name'],
            'admin1'       => $r['admin1'],
            'country'      => $r['country'],
            'country_code' => $r['country_code'],
            'latitude'     => $r['latitude'],
            'longitude'    => $r['longitude'],
            'timezone'     => $r['timezone'],
        ], JSON_UNESCAPED_UNICODE);

        $labelParts = array_filter([
            $r['name'] ?? null,
            $r['admin1'] ?? null,
            $r['country'] ?? null,
        ], static fn($v) => is_string($v) && trim($v) !== '');
        $label = implode(', ', $labelParts);
        ?>
        <button type="button"
                class="hm-weather-loc-item"
                hx-post="<?= e(route('home.weather.location')) ?>"
                hx-vals='<?= e($payload) ?>'
                hx-swap="none">
            <i class="fa-solid fa-location-dot"></i><span><?= e($label) ?></span>
        </button>
    <?php endforeach; ?>
<?php endif; ?>
