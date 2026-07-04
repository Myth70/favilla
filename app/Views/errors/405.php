<!DOCTYPE html>
<html lang="<?= e(function_exists('locale') ? locale() : 'it') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>405 — <?= e(t('errors.method_not_allowed.title')) ?></title>
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
<body class="err-page-405">

    <div class="err-blob err-blob-1"></div>
    <div class="err-blob err-blob-2"></div>
    <div class="err-blob err-blob-3"></div>

    <div class="err-wrapper">
        <div class="err-card">

            <div class="err-brand">
                <div class="err-logo" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" focusable="false">
                        <path d="M16 2 C13 7 5 10 5 18 C5 24.8 9.8 29.5 16 30 C22.2 29.5 27 24.8 27 18 C27 10 19 7 16 2Z" fill="#f97316"/>
                        <path d="M16 9 C14 13 10 16 10 20 C10 23.9 12.7 27 16 27 C19.3 27 22 23.9 22 20 C22 16 18 13 16 9Z" fill="#ea580c"/>
                        <path d="M16 15 C15 17 13 19 14 22 C14.7 24 16 25 16 25 C16 25 17.3 24 18 22 C19 19 17 17 16 15Z" fill="#fbbf24"/>
                    </svg>
                </div>
                <p class="err-brand-name"><?= $errAppName ?></p>
            </div>

            <hr class="err-divider">

            <div class="err-icon-wrap err-icon-wrap-405">
                <i class="fa-solid fa-ban"></i>
            </div>

            <div class="err-code err-code-405">405</div>
            <div class="err-title"><?= e(t('errors.method_not_allowed.title')) ?></div>
            <p class="err-message">
                <?= t('errors.method_not_allowed.message') ?>
            </p>

            <a href="<?= $errAssetBase ?>/" class="err-btn err-btn-405">
                <i class="fa-solid fa-house me-2"></i><?= e(t('errors.back_home')) ?>
            </a>

            <p class="err-message">
                <?= e(t('errors.is_error_q')) ?>
                <a href="<?= $errAssetBase ?>/feedback/new?from=405&amp;url=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '') ?>"><?= e(t('errors.report_problem')) ?></a>
            </p>

        </div>
    </div>

</body>
</html>
