<!DOCTYPE html>
<html lang="<?= e(function_exists('locale') ? locale() : 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — <?= e(t('errors.server_error.title')) ?></title>
    <?php
    $errAssetBase = rtrim((string) ($_ENV['APP_URL'] ?? 'http://localhost'), '/');
    $errBasePath  = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/');
    if ($errBasePath !== '') {
        $errAssetBase .= '/' . $errBasePath;
    }
    $errAppName  = htmlspecialchars((string) ($_ENV['APP_NAME'] ?? 'Favilla'), ENT_QUOTES, 'UTF-8');
    $errCssV     = @filemtime(__DIR__ . '/../../../public/assets/css/errors.css') ?: time();
    ?>
    <link rel="stylesheet" href="<?= $errAssetBase ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= $errAssetBase ?>/assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="<?= $errAssetBase ?>/assets/css/errors.css?v=<?= $errCssV ?>">
</head>
<body class="err-page-500">

    <div class="err-blob err-blob-1"></div>
    <div class="err-blob err-blob-2"></div>
    <div class="err-blob err-blob-3"></div>

    <div class="err-wrapper">
        <div class="err-card">

            <div class="err-brand">
                <div class="err-logo" aria-hidden="true">
                    <img src="<?= $errAssetBase ?>/assets/images/logo.svg" width="64" height="64" alt="">
                </div>
                <p class="err-brand-name"><?= $errAppName ?></p>
            </div>

            <hr class="err-divider">

            <div class="err-icon-wrap err-icon-wrap-500">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div class="err-code err-code-500">500</div>
            <div class="err-title"><?= e(t('errors.server_error.title')) ?></div>
            <p class="err-message">
                <?= t('errors.server_error.message') ?>
            </p>

            <a href="<?= $errAssetBase ?>/" class="err-btn err-btn-500">
                <i class="fa-solid fa-house me-2"></i><?= e(t('errors.back_home')) ?>
            </a>

            <p class="err-message">
                <?= e(t('errors.server_error.help')) ?>
                <a href="<?= $errAssetBase ?>/feedback/new?from=500&amp;url=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>"><?= e(t('errors.server_error.report')) ?></a>
            </p>

        </div>
    </div>

</body>
</html>
