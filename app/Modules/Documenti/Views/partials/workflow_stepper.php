<?php
/**
 * Partial: stepper visivo del workflow di approvazione.
 * Variabili: $doc (array con 'stato' e 'step_corrente')
 */
$stato = (string) ($doc['stato'] ?? '');
$step  = (string) ($doc['step_corrente'] ?? 'redazione');

$steps = [
    'redazione'    => ['label' => t('documenti.stepper.redazione'),    'icon' => 'fa-pen-ruler'],
    'controllo'    => ['label' => t('documenti.stepper.controllo'),    'icon' => 'fa-magnifying-glass'],
    'approvazione' => ['label' => t('documenti.stepper.approvazione'), 'icon' => 'fa-clipboard-check'],
    'completato'   => ['label' => t('documenti.stepper.pubblicazione'), 'icon' => 'fa-globe'],
];
$order   = array_keys($steps);
$current = array_search($step, $order, true);
if ($current === false) {
    $current = 0;
}
$isRifiutato = $stato === 'rifiutato';
$totale      = count($order);
?>
<div class="dc-stepper d-flex align-items-start justify-content-between mb-3" role="list"
     aria-label="<?= e(t('documenti.stepper.aria_label')) ?>">
    <?php foreach ($order as $i => $key):
        $s = $steps[$key];
        if ($isRifiutato) {
            $state = 'rejected';
        } elseif ($i < $current) {
            $state = 'done';
        } elseif ($i === $current) {
            $state = 'active';
        } else {
            $state = 'todo';
        }
        $color = match ($state) {
            'done'     => 'success',
            'active'   => 'primary',
            'rejected' => 'danger',
            default    => 'secondary',
        };
        $iconCls = $state === 'done' ? 'fa-check' : ($state === 'rejected' ? 'fa-xmark' : $s['icon']);
        ?>
        <div class="dc-step text-center flex-fill" role="listitem"
             <?= $state === 'active' ? 'aria-current="step"' : '' ?>>
            <span class="dc-step-dot d-inline-flex align-items-center justify-content-center rounded-circle bg-<?= $color ?> <?= $state === 'todo' ? 'bg-opacity-25 text-secondary' : 'text-white' ?>"
                  style="width:2.25rem;height:2.25rem;">
                <i class="fa-solid <?= e($iconCls) ?>" aria-hidden="true"></i>
            </span>
            <div class="small mt-1 <?= $state === 'todo' ? 'text-muted' : 'fw-semibold' ?>"><?= e($s['label']) ?></div>
        </div>
        <?php if ($i < $totale - 1): ?>
            <div class="dc-step-line flex-fill border-top mt-3 mx-1" style="max-width:3rem;opacity:.4;"></div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
