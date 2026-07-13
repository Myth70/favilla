<!DOCTYPE html>
<html lang="<?= e(function_exists('locale') ? locale() : 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(t('errors.maintenance.title')) ?></title>
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
<body class="err-page-maint">

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

            <div class="maint-icon-wrap" aria-hidden="true">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>

            <div class="maint-badge">
                <span class="maint-badge-dot"></span>
                <?= e(t('errors.maintenance.badge')) ?>
            </div>

            <div class="err-title"><?= e(t('errors.maintenance.title')) ?></div>
            <p class="err-message">
                <?= t('errors.maintenance.message') ?>
            </p>

            <div class="maint-progress-wrap">
                <div class="maint-progress-bar"></div>
            </div>

            <p class="maint-footer"><?= e(t('errors.maintenance.footer')) ?></p>

        </div>
    </div>

</body>
</html>
