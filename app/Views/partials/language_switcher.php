<?php
/**
 * Language switcher (i18n).
 * Reusable in the main header and the auth layout. Renders a Bootstrap dropdown
 * of the supported locales, each linking to the public GET /lang/{code} route.
 *
 * Optional var: $switcherAlign ('end' default) for dropdown-menu alignment.
 */
$supported = (array) config('localization.supported', ['it']);
if (count($supported) < 2) {
    return; // nothing to switch
}
$names   = (array) config('localization.names', []);
$flags   = (array) config('localization.flags', []);
$current = $currentLocale ?? locale();
$align   = ($switcherAlign ?? 'end') === 'start' ? '' : 'dropdown-menu-end';
?>
<div class="dropdown language-switcher">
    <button class="btn btn-link text-body text-decoration-none p-0 language-switcher-btn"
            type="button" data-bs-toggle="dropdown" aria-expanded="false"
            aria-label="<?= e(t('common.language.change')) ?>"
            title="<?= e(t('common.language.change')) ?>">
        <span class="language-switcher-flag" aria-hidden="true"><?= e($flags[$current] ?? '🌐') ?></span>
    </button>
    <ul class="dropdown-menu <?= $align ?> language-switcher-menu shadow">
        <li>
            <h6 class="dropdown-header d-flex align-items-center gap-1 text-muted">
                <i class="fa-solid fa-globe fa-xs"></i>
                <?= e(t('common.language.label')) ?>
            </h6>
        </li>
        <?php foreach ($supported as $code): ?>
            <li>
                <a class="dropdown-item language-switcher-item d-flex align-items-center gap-2<?= $code === $current ? ' active' : '' ?>"
                   href="<?= e(route('lang.switch', ['code' => $code])) ?>"
                   <?= $code === $current ? 'aria-current="true"' : '' ?>>
                    <span class="language-switcher-flag" aria-hidden="true"><?= e($flags[$code] ?? '') ?></span>
                    <span class="language-switcher-name flex-grow-1"><?= e($names[$code] ?? strtoupper($code)) ?></span>
                    <?php if ($code === $current): ?>
                        <i class="fa-solid fa-check fa-xs language-switcher-check ms-1" aria-hidden="true"></i>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
