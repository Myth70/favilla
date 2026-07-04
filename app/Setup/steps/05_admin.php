<?php
/** @var array $admin */
/** @var string[] $errors */
$name     = htmlspecialchars($admin['name']     ?? '');
$email    = htmlspecialchars($admin['email']    ?? '');
$username = htmlspecialchars($admin['username'] ?? '');
?>
<h2>Utente amministratore</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="setup.php">
    <input type="hidden" name="step" value="5">

    <div class="form-group">
        <label>Nome completo</label>
        <input type="text" name="admin_name" value="<?= $name ?>" placeholder="Mario Rossi" autocomplete="name">
    </div>

    <div class="input-row">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="admin_email" value="<?= $email ?>" placeholder="admin@azienda.it" autocomplete="email">
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="admin_username" value="<?= $username ?>" placeholder="admin" autocomplete="username">
        </div>
    </div>

    <div class="input-row">
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="admin_password" autocomplete="new-password" placeholder="••••••••">
        </div>
        <div class="form-group">
            <label>Conferma password</label>
            <input type="password" name="admin_confirm" autocomplete="new-password" placeholder="••••••••">
        </div>
    </div>
    <div class="hint wz-hint-tight">
        Minimo 8 caratteri, almeno una maiuscola e un numero.
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-primary">Installa →</button>
    </div>
</form>
