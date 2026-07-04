<?php
/**
 * Command Palette - overlay modale per navigazione rapida admin.
 * Trigger: Ctrl+K / Cmd+K oppure bottone sidebar.
 * Incluso da sidebar.php, gated su permesso admin.
 * Il CSS resta inline perche' il partial viene renderizzato dopo il <head>;
 * lo script invece viene registrato nel footer tramite pushScript().
 */
$view->pushScript('js/components/command-palette.js');
?>
<div id="command-palette-root"
     class="cp-overlay d-none"
     role="dialog"
     aria-modal="true"
     aria-label="<?= e(t('admin.palette.aria_label')) ?>"
     data-palette-url="<?= e(route('admin.api.palette')) ?>"
     data-admin-index-url="<?= e(route('admin.index')) ?>">
    <div class="cp-dialog">

        <!-- Input area -->
        <div class="cp-input-wrapper">
            <i class="fa-solid fa-magnifying-glass cp-input-icon" aria-hidden="true"></i>
            <input type="text"
                   class="cp-input"
                   placeholder="<?= e(t('admin.palette.placeholder')) ?>"
                   autocomplete="off"
                   spellcheck="false"
                   aria-label="<?= e(t('admin.palette.aria_label')) ?>"
                   aria-controls="cp-results-list"
                   aria-autocomplete="list">
            <button type="button" class="cp-clear-btn" aria-label="<?= e(t('admin.palette.clear')) ?>">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <kbd class="cp-shortcut-badge">Esc</kbd>
        </div>

        <!-- Filter tabs -->
        <div class="cp-tabs" role="tablist" aria-label="<?= e(t('admin.palette.filter_aria')) ?>">
            <button type="button" class="cp-tab cp-tab-active" data-cp-tab="all" role="tab" aria-selected="true">
                <i class="fa-solid fa-table-cells-large fa-xs" aria-hidden="true"></i>
                <?= e(t('admin.palette.tab_all')) ?>
                <span class="cp-tab-count" id="cp-count-all">-</span>
            </button>
            <button type="button" class="cp-tab" data-cp-tab="recent" role="tab" aria-selected="false">
                <i class="fa-solid fa-clock-rotate-left fa-xs" aria-hidden="true"></i>
                <?= e(t('admin.palette.tab_recent')) ?>
                <span class="cp-tab-count" id="cp-count-recent">0</span>
            </button>
        </div>

        <!-- Result count -->
        <div class="cp-result-count cp-hidden" id="cp-result-count"></div>

        <!-- Results -->
        <div class="cp-results" id="cp-results-list" role="listbox" aria-label="<?= e(t('admin.palette.results_aria')) ?>"></div>

        <!-- Footer -->
        <div class="cp-footer">
            <span class="cp-footer-item"><kbd class="cp-kbd">&uarr;</kbd><kbd class="cp-kbd">&darr;</kbd> <span><?= e(t('admin.palette.nav')) ?></span></span>
            <span class="cp-footer-item"><kbd class="cp-kbd"><?= e(t('admin.palette.key_enter')) ?></kbd> <span><?= e(t('admin.palette.open')) ?></span></span>
            <span class="cp-footer-sep" aria-hidden="true">&middot;</span>
            <span class="cp-footer-item cp-footer-newtab"
                  data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('admin.palette.newtab_tip')) ?>">
                <kbd class="cp-kbd">Ctrl</kbd>+<kbd class="cp-kbd"><?= e(t('admin.palette.key_enter')) ?></kbd> <span><?= e(t('admin.palette.newtab')) ?></span>
            </span>
            <span class="cp-footer-item"><kbd class="cp-kbd">Esc</kbd> <span><?= e(t('admin.palette.close')) ?></span></span>
            <a href="<?= e(route('admin.index')) ?>" class="cp-footer-link" id="cp-index-link"
               data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('admin.palette.index_tip')) ?>">
                <i class="fa-solid fa-table-cells-large fa-xs" aria-hidden="true"></i>
                <?= e(t('admin.palette.full_index')) ?>
            </a>
        </div>
    </div>
</div>
<link rel="stylesheet" href="<?= e(asset('css/components/command-palette.css')) ?>">

