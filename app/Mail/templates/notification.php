<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($subject ?? 'Notifica', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($app_name ?? 'Favilla', ENT_QUOTES, 'UTF-8') ?></title>
<style>
<?= file_get_contents(__DIR__ . '/email.css') ?>
</style>
</head>
<body class="em-body">
<div class="em-container">
  <h2 class="em-title"><?= htmlspecialchars($title ?? 'Notifica', ENT_QUOTES, 'UTF-8') ?></h2>
  <p class="em-text">
    Ciao <?= htmlspecialchars($user_name ?? 'utente', ENT_QUOTES, 'UTF-8') ?>,
  </p>
  <div class="em-body-html">
    <?= $body ?? '' /* HTML body — escaped by caller if from user input */ ?>
  </div>
  <?php if (!empty($action_url) && !empty($action_label)): ?>
  <p class="em-cta-wrap">
    <a href="<?= htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8') ?>"
       class="em-button">
      <?= htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8') ?>
    </a>
  </p>
  <?php endif; ?>
  <hr class="em-divider">
  <p class="em-footer">
    <?= htmlspecialchars($app_name ?? 'Favilla', ENT_QUOTES, 'UTF-8') ?> — intranet aziendale
  </p>
</div>
</body>
</html>
