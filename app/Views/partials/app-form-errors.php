<?php
$errors = $errors ?? [];

$summaryItems = $summaryItems ?? null;
if ($summaryItems === null) {
    $summaryItems = [];
    foreach ($errors as $messages) {
        foreach ((array) $messages as $message) {
            if ($message === null || $message === '') {
                continue;
            }
            $summaryItems[] = (string) $message;
        }
    }
}

$summaryText = trim((string) ($summaryText ?? ''));
if ($summaryText === '' && $summaryItems === []) {
    return;
}

$summaryTitle = $summaryTitle ?? 'Correggi gli errori.';
$summaryClass = $summaryClass ?? 'alert alert-danger d-flex align-items-start gap-2 mb-3';
$summaryBodyClass = $summaryBodyClass ?? '';
$summaryListClass = $summaryListClass ?? 'mb-0 mt-1 ps-3';
$summaryAriaLive = $summaryAriaLive ?? 'assertive';
?>

<div id="app-form-errors-summary" class="<?= e($summaryClass) ?>" role="alert" aria-live="<?= e($summaryAriaLive) ?>">
    <i class="fa-solid fa-triangle-exclamation mt-1" aria-hidden="true"></i>
    <div class="<?= e($summaryBodyClass) ?>">
        <?php if ($summaryTitle !== null && $summaryTitle !== '' && $summaryItems !== []): ?>
            <div class="fw-semibold mb-1"><?= e($summaryTitle) ?></div>
        <?php endif; ?>

        <?php if ($summaryText !== ''): ?>
            <div><?= e($summaryText) ?></div>
        <?php endif; ?>

        <?php if ($summaryItems !== []): ?>
            <ul class="<?= e($summaryListClass) ?>">
                <?php foreach ($summaryItems as $item): ?>
                    <li><?= e($item) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>