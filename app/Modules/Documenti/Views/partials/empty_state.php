<?php
/**
 * Empty state riusabile per il modulo Documenti.
 *
 * Parametri:
 *   $icon       string Classe Font Awesome (es. "fa-inbox"), senza prefisso "fa-solid"
 *   $titolo     string Titolo principale (es. "Nessun documento")
 *   $messaggio  string Messaggio descrittivo
 *   $cta        ?array ['label' => string, 'href' => string, 'icon' => string]
 *   $wrap       bool   Se true (default) avvolge in una card
 */
$icon      = $icon      ?? 'fa-circle-info';
$titolo    = $titolo    ?? t('documenti.empty_state.nessun_risultato');
$messaggio = $messaggio ?? '';
$cta       = $cta       ?? null;
$wrap      = $wrap      ?? true;
?>
<?php if ($wrap): ?>
<div class="card dc-empty-state">
<?php endif; ?>
    <div class="card-body text-center py-5">
        <div class="dc-empty-state-icon mb-3" aria-hidden="true">
            <i class="fa-solid <?= e($icon) ?>"></i>
        </div>
        <h5 class="mb-1"><?= e($titolo) ?></h5>
        <?php if ($messaggio !== ''): ?>
            <p class="text-muted mb-3"><?= e($messaggio) ?></p>
        <?php endif; ?>
        <?php if (is_array($cta) && !empty($cta['label']) && !empty($cta['href'])): ?>
            <a href="<?= e($cta['href']) ?>" class="btn btn-primary btn-sm">
                <?php if (!empty($cta['icon'])): ?>
                    <i class="fa-solid <?= e($cta['icon']) ?> me-1" aria-hidden="true"></i>
                <?php endif; ?>
                <?= e($cta['label']) ?>
            </a>
        <?php endif; ?>
    </div>
<?php if ($wrap): ?>
</div>
<?php endif; ?>
