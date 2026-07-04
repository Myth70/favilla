<?php
/** @var array $app */
/** @var string[] $errors */

// Auto-rileva URL dal server se non già impostato
$autoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Auto-rileva base path: rimuove /setup.php e il path del file di setup
// Es: /favilla/setup.php → base_path suggerito = /favilla/public
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$autoBasePath = '';
if ($scriptPath !== '' && basename($scriptPath) === 'setup.php') {
    $dir = rtrim(dirname($scriptPath), '/');
    $autoBasePath = ($dir !== '' && $dir !== '/') ? $dir . '/public' : '/public';
}

$appName  = htmlspecialchars($app['appName'] ?? 'Favilla');
$appUrl   = htmlspecialchars($app['appUrl']  ?? $autoUrl);
$appBasePath = htmlspecialchars($app['appBasePath'] ?? $autoBasePath);
$appEnv   = $app['appEnv']   ?? 'production';
// In Docker APP_KEY arriva dal process env (compose) e vince sempre sul .env
// scritto dal wizard: mostrare quella reale, non generarne una fittizia.
$appKey   = htmlspecialchars($app['appKey']  ?? (getenv('APP_KEY') ?: bin2hex(random_bytes(32))));
$timezone = $app['timezone'] ?? 'Europe/Rome';
?>
<h2>Configurazione applicazione</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $e): ?>
        <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="POST" action="setup.php">
    <input type="hidden" name="step" value="3">

    <div class="form-group">
        <label>Nome applicazione</label>
        <input type="text" name="app_name" value="<?= $appName ?>" placeholder="Favilla">
    </div>

    <div class="form-group">
        <label>URL applicazione</label>
        <input type="url" name="app_url" value="<?= $appUrl ?>" placeholder="https://intranet.azienda.it">
        <div class="hint">URL completo senza slash finale. Se contiene anche un path, verrà normalizzato automaticamente.</div>
    </div>

    <div class="form-group">
        <label>Base path pubblico</label>
        <input type="text" name="app_base_path" value="<?= $appBasePath ?>" placeholder="/public">
        <div class="hint">Rilevato automaticamente. XAMPP subfolder (es. <code>localhost/favilla</code>): <code>/favilla/public</code>. VirtualHost root: <code>/public</code>. Produzione con DocumentRoot su public/: lascia vuoto.</div>
    </div>

    <div class="input-row">
        <div class="form-group">
            <label>Ambiente</label>
            <select name="app_env">
                <option value="production"  <?= $appEnv === 'production' ? 'selected' : '' ?>>production</option>
                <option value="development" <?= $appEnv === 'development' ? 'selected' : '' ?>>development</option>
            </select>
        </div>
        <div class="form-group">
            <label>Fuso orario</label>
            <select name="timezone">
                <option value="Europe/Rome"   <?= $timezone === 'Europe/Rome' ? 'selected' : '' ?>>Europe/Rome</option>
                <option value="Europe/London" <?= $timezone === 'Europe/London' ? 'selected' : '' ?>>Europe/London</option>
                <option value="UTC"           <?= $timezone === 'UTC' ? 'selected' : '' ?>>UTC</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Chiave di sicurezza (APP_KEY)</label>
        <div class="wz-key-row">
            <input type="text" name="app_key" id="app-key" value="<?= $appKey ?>">
            <button type="button" class="btn btn-secondary btn-sm" onclick="regenKey()">Rigenera</button>
        </div>
        <div class="hint">Minimo 32 caratteri. Tienila segreta e non cambiarla dopo l'installazione.</div>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-primary">Avanti →</button>
    </div>
</form>

<script>
function regenKey() {
    var chars = '0123456789abcdef';
    var key = '';
    for (var i = 0; i < 64; i++) {
        key += chars[Math.floor(Math.random() * chars.length)];
    }
    document.getElementById('app-key').value = key;
}
</script>
