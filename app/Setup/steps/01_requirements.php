<?php
/** @var array<array{name:string,ok:bool,detail:string}> $checks */
/** @var string[] $errors */
$allOk = array_reduce($checks, fn ($c, $i) => $c && $i['ok'], true);
?>
<h2>Verifica requisiti di sistema</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<ul class="check-list">
<?php foreach ($checks as $check): ?>
    <li>
        <span class="<?= $check['ok'] ? 'check-ok' : 'check-fail' ?>"><?= $check['ok'] ? '✓' : '✗' ?></span>
        <?= htmlspecialchars($check['name']) ?>
        <span class="check-detail"><?= htmlspecialchars($check['detail']) ?></span>
    </li>
<?php endforeach; ?>
</ul>

<?php if (!$allOk): ?>
<div class="alert alert-warning">
    Correggi gli elementi contrassegnati con ✗ prima di procedere.
</div>
<?php endif; ?>

<form method="POST" action="setup.php">
    <input type="hidden" name="step" value="1">
    <div class="btn-row">
        <button type="submit" class="btn btn-primary" <?= !$allOk ? 'disabled' : '' ?>>
            Avanti →
        </button>
    </div>
</form>
