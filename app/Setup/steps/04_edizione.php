<?php
/** @var array $edition */
/** @var array $demo */
/** @var string[] $errors */
$defaultEdition = config('editions.default', 'developer');
$selected = $edition['edition'] ?? $defaultEdition;
$demoChecked = !empty($demo['load']);

$options = [
    'personal'  => [
        'label' => 'Personal',
        'desc'  => 'Uso singolo utente: sidebar essenziale, niente registrazione pubblica, amministrazione raggiungibile dal menu utente.',
    ],
    'team' => [
        'label' => 'Team',
        'desc'  => 'Esperienza completa multi-utente, con tutti i moduli di collaborazione disponibili.',
    ],
    'developer' => [
        'label' => 'Developer',
        'desc'  => 'Ambiente di sviluppo: nessuna restrizione, comportamento identico alla configurazione di base.',
    ],
];
?>
<h2>Edizione</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<p class="hint">Scegli come vuoi usare Favilla. Potrai cambiarla in seguito da Amministrazione → Configurazione.</p>

<form method="POST" action="setup.php">
    <input type="hidden" name="step" value="4">

    <?php foreach ($options as $value => $opt): ?>
    <div class="form-group">
        <label>
            <input type="radio" name="edition" value="<?= htmlspecialchars($value) ?>" <?= $selected === $value ? 'checked' : '' ?>>
            <?= htmlspecialchars($opt['label']) ?>
        </label>
        <div class="hint"><?= htmlspecialchars($opt['desc']) ?></div>
    </div>
    <?php endforeach; ?>

    <hr>

    <div class="form-group">
        <label>
            <input type="checkbox" name="demo_data" value="1" <?= $demoChecked ? 'checked' : '' ?>>
            Carica dati dimostrativi
        </label>
        <div class="hint">
            Popola l'installazione con contenuti di esempio (attività, calendario,
            contatti, documenti, progetti…) e <strong>10 utenti di prova con
            password deboli e prevedibili</strong> — solo per ambienti di
            valutazione, non per installazioni di produzione esposte.
        </div>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-primary">Avanti →</button>
    </div>
</form>
