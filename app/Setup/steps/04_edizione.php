<?php
/** @var array $edition */
/** @var string[] $errors */
$defaultEdition = config('editions.default', 'developer');
$selected = $edition['edition'] ?? $defaultEdition;

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

    <div class="btn-row">
        <button type="submit" class="btn btn-primary">Avanti →</button>
    </div>
</form>
