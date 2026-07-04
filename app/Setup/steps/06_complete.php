<?php
/** @var string[] $log */
/** @var string|null $error */
$success = $error === null;
?>

<?php if ($success): ?>

<span class="big-icon">🎉</span>
<h2 class="wz-title-center">Installazione completata!</h2>

<div class="log-box">
    <ul>
        <?php foreach ($log as $entry): ?>
            <li class="log-ok"><?= htmlspecialchars($entry) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="alert alert-success">
    Favilla è pronto. Accedi con le credenziali che hai appena configurato.
</div>

<div class="btn-row btn-row-center">
    <a href="public/" class="btn btn-primary">Vai al Login →</a>
</div>

<?php else: ?>

<span class="big-icon">⚠️</span>
<h2 class="wz-title-center">Errore durante l'installazione</h2>

<?php if (!empty($log)): ?>
<div class="log-box">
    <ul>
        <?php foreach ($log as $entry): ?>
            <li class="log-ok"><?= htmlspecialchars($entry) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="alert alert-danger">
    <strong>Errore:</strong> <?= htmlspecialchars($error) ?>
</div>

<div class="alert alert-warning">
    Il file <code>storage/.setup_complete</code> <strong>non</strong> è stato creato.
    Correggi il problema e riprova.
</div>

<div class="btn-row">
    <a href="setup.php?step=1" class="btn btn-secondary">← Ricomincia</a>
</div>

<?php endif; ?>
