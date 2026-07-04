<?php
// Hidden config node — the floating button has been replaced by an icon
// in the global header (see app/Views/partials/header.php). The JS
// reads data-* attributes from this root to drive the offcanvas.
?>
<div id="ho-root"
     hidden
     data-panel-url="<?= e(route('helponline.panel')) ?>"
     data-ask-url="<?= e(route('helponline.ask')) ?>"
     data-feedback-url="<?= e(route('helponline.feedback')) ?>"
     data-guide-url="<?= e(route('helponline.index')) ?>"
     data-current-path="<?= e(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>"
     data-page-title="<?= e((string) ($pageTitle ?? '')) ?>"></div>

<div class="offcanvas offcanvas-end ho-offcanvas"
     tabindex="-1"
     id="ho-offcanvas"
     aria-labelledby="ho-offcanvas-label"
     data-bs-scroll="true">
    <div id="ho-panel-host" class="ho-panel-host">
        <div class="ho-panel-loading">
            <div class="ho-panel-spinner" aria-hidden="true"></div>
            <div class="text-muted small"><?= e(t('helponline.panel.loading')) ?></div>
        </div>
    </div>
</div>
