<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Reset password — <?= htmlspecialchars($app_name ?? 'Favilla', ENT_QUOTES, 'UTF-8') ?></title>
<style>
<?= file_get_contents(__DIR__ . '/email.css') ?>
</style>
</head>
<body class="em-body">
<div class="em-container">
  <h2 class="em-title">Reset della password</h2>
  <p class="em-text">Ciao <?= htmlspecialchars($user_name ?? 'utente', ENT_QUOTES, 'UTF-8') ?>,</p>
  <p class="em-text">
    Hai richiesto il reset della password per il tuo account.
    Clicca il bottone qui sotto per impostarne una nuova:
  </p>
  <p class="em-cta-wrap">
    <a href="<?= htmlspecialchars($reset_link ?? '#', ENT_QUOTES, 'UTF-8') ?>"
       class="em-button">
      Reimposta password
    </a>
  </p>
  <p class="em-note">
    Il link scade fra <strong>24 ore</strong>.
    Se non hai richiesto il reset, ignora questa email.
  </p>
  <hr class="em-divider">
  <p class="em-footer">
    <?= htmlspecialchars($app_name ?? 'Favilla', ENT_QUOTES, 'UTF-8') ?> — intranet aziendale
  </p>
</div>
</body>
</html>
