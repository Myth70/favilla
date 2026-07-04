<?php
/** @var array $db */
/** @var string[] $errors */
$host = htmlspecialchars($db['host'] ?? 'localhost');
$port = htmlspecialchars($db['port'] ?? '3306');
$name = htmlspecialchars($db['name'] ?? '');
$user = htmlspecialchars($db['user'] ?? 'root');
?>
<h2>Configurazione database</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="setup.php" id="db-form">
    <input type="hidden" name="step" value="2">

    <div class="input-row">
        <div class="form-group wz-flex-2">
            <label>Host</label>
            <input type="text" name="db_host" value="<?= $host ?>" placeholder="localhost">
        </div>
        <div class="form-group wz-flex-1">
            <label>Porta</label>
            <input type="text" name="db_port" value="<?= $port ?>" placeholder="3306">
        </div>
    </div>

    <div class="form-group">
        <label>Nome database</label>
        <input type="text" name="db_name" value="<?= $name ?>" placeholder="favilla">
    </div>

    <div class="form-group">
        <label>Utente database</label>
        <input type="text" name="db_user" value="<?= $user ?>" placeholder="root">
    </div>

    <div class="form-group">
        <label>Password database</label>
        <input type="password" name="db_pass" placeholder="(lascia vuoto se senza password)">
        <div class="hint">In sviluppo XAMPP la password root è spesso vuota.</div>
    </div>

    <div id="db-test-result"></div>

    <div class="wz-actions-between">
        <button type="button" class="btn btn-secondary btn-sm" onclick="testDbConnection()">
            Testa Connessione
        </button>
        <button type="submit" class="btn btn-primary" id="btn-next" disabled>
            Avanti →
        </button>
    </div>
</form>

<script>
(function () {
    function testDbConnection() {
        var btn = document.querySelector('[onclick="testDbConnection()"]');
        var res = document.getElementById('db-test-result');

        btn.disabled = true;
        btn.textContent = 'Test in corso…';
        res.innerHTML = '';

        var fd = new FormData();
        fd.append('action', 'test_db');
        fd.append('host',   document.querySelector('[name=db_host]').value);
        fd.append('port',   document.querySelector('[name=db_port]').value);
        fd.append('name',   document.querySelector('[name=db_name]').value);
        fd.append('user',   document.querySelector('[name=db_user]').value);
        fd.append('pass',   document.querySelector('[name=db_pass]').value);

        fetch('setup.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    res.innerHTML = '<div class="alert alert-success wz-alert-top">✓ ' + data.message + '</div>';
                    document.getElementById('btn-next').disabled = false;
                } else {
                    res.innerHTML = '<div class="alert alert-danger wz-alert-top">✗ ' + data.message + '</div>';
                    document.getElementById('btn-next').disabled = true;
                }
                btn.disabled = false;
                btn.textContent = 'Testa Connessione';
            })
            .catch(function () {
                res.innerHTML = '<div class="alert alert-danger wz-alert-top">Errore di comunicazione con il server.</div>';
                btn.disabled = false;
                btn.textContent = 'Testa Connessione';
            });
    }

    window.testDbConnection = testDbConnection;
}());
</script>
