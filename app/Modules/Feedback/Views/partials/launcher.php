<?php
/**
 * Launcher globale del modulo Segnalazioni.
 *
 * Incluso da app/Views/layouts/main.php (guardato da isModuleEnabled + utente loggato).
 * Il trigger è l'icona Bug nel footer (vedi app/Views/partials/footer.php);
 * qui forniamo il nodo di config (data-*), l'offcanvas col form e gli asset.
 *
 * Variabili: $pageTitle
 *
 * NB: asset CSS/JS via tag diretti — consentito nelle partial incluse dal layout
 * (ui.md §2: pushStyle/pushScript vietati nelle partial condivise, tag diretti ok).
 */
use App\Modules\Feedback\Services\FeedbackService;

$sgTipi     = FeedbackService::tipiMeta();
$sgSeverita = FeedbackService::severitaMeta();
?>
<link rel="stylesheet" href="<?= e(asset('css/feedback.css')) ?>">

<div id="sg-root"
     hidden
     data-store-url="<?= e(route('feedback.store')) ?>"
     data-current-path="<?= e(strtok($_SERVER['REQUEST_URI'] ?? '/', '?')) ?>"
     data-current-route="<?= e(app(\App\Core\Router::class)->current() ?? '') ?>"
     data-page-title="<?= e((string) ($pageTitle ?? '')) ?>"></div>

<div class="offcanvas offcanvas-end sg-offcanvas"
     tabindex="-1"
     id="sg-offcanvas"
     aria-labelledby="sg-offcanvas-label"
     data-bs-scroll="true">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title d-flex align-items-center gap-2" id="sg-offcanvas-label">
            <i class="fa-solid fa-bug text-danger" aria-hidden="true"></i>
            <?= e(t('feedback.report_title')) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(t('common.action.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="text-muted small">
            <?= e(t('feedback.launcher.intro')) ?>
        </p>

        <form id="sg-form" novalidate>
            <?= csrf_field() ?>

            <div class="row g-2 mb-3">
                <div class="col-7">
                    <label class="form-label small text-muted" for="sg-tipo"><?= e(t('feedback.form.tipo')) ?></label>
                    <select class="form-select form-select-sm" id="sg-tipo" name="tipo">
                        <?php foreach ($sgTipi as $key => $meta): ?>
                            <option value="<?= e($key) ?>"><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-5">
                    <label class="form-label small text-muted" for="sg-severita"><?= e(t('feedback.form.severita')) ?></label>
                    <select class="form-select form-select-sm" id="sg-severita" name="severita">
                        <?php foreach ($sgSeverita as $key => $meta): ?>
                            <option value="<?= e($key) ?>" <?= $key === 'media' ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted" for="sg-titolo"><?= e(t('feedback.form.titolo')) ?> <span class="text-muted"><?= e(t('feedback.form.optional')) ?></span></label>
                <input type="text" class="form-control form-control-sm" id="sg-titolo" name="titolo"
                       maxlength="200" placeholder="<?= e(t('feedback.form.titolo_placeholder_long')) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted" for="sg-descrizione"><?= e(t('feedback.form.what_happened')) ?> <span class="text-danger">*</span></label>
                <textarea class="form-control form-control-sm" id="sg-descrizione" name="descrizione"
                          rows="4" maxlength="5000" required
                          placeholder="<?= e(t('feedback.form.descr_placeholder_long')) ?>"></textarea>
                <div class="invalid-feedback"><?= e(t('feedback.form.descr_invalid')) ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted" for="sg-passi"><?= e(t('feedback.form.steps')) ?> <span class="text-muted"><?= e(t('feedback.form.optional')) ?></span></label>
                <textarea class="form-control form-control-sm" id="sg-passi" name="passi"
                          rows="2" maxlength="5000"
                          placeholder="<?= e(t('feedback.form.steps_placeholder_long')) ?>"></textarea>
            </div>

            <details class="sg-context-disclosure mb-3">
                <summary class="small text-muted">
                    <i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i><?= e(t('feedback.launcher.attached_label')) ?>
                </summary>
                <div class="sg-context-summary small text-muted mt-2" id="sg-context-summary"></div>
            </details>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary" id="sg-submit">
                    <i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i><?= e(t('feedback.form.submit')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="<?= e(asset('js/feedback.js')) ?>" nonce="<?= e(csp_nonce()) ?>"></script>
