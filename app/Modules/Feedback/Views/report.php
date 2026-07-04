<?php $view->layout('main'); ?>
<?php $view->start('content'); ?>
<?php
use App\Modules\Feedback\Services\FeedbackService;

$sgTipi     = FeedbackService::tipiMeta();
$sgSeverita = FeedbackService::severitaMeta();
$pageUrl    = (string) ($pageUrl ?? '');
$fromCode   = (string) ($fromCode ?? '');
?>
<div class="container app-page-narrow py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bug text-danger"></i>
                    <span class="fw-semibold"><?= e(t('feedback.report_title')) ?></span>
                </div>
                <div class="card-body">
                    <?php if ($fromCode !== '' || $pageUrl !== ''): ?>
                        <div class="alert alert-warning d-flex align-items-start gap-2">
                            <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                            <div class="small">
                                <?= e(t('feedback.report.warning')) ?>
                                <?php if ($fromCode !== ''): ?> <?= t('feedback.report.error_code', ['code' => '<strong>' . e($fromCode) . '</strong>']) ?><?php endif; ?>
                                <?php if ($pageUrl !== ''): ?><br><?= e(t('feedback.report.on_page')) ?> <code><?= e($pageUrl) ?></code><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted small">
                        <?= e(t('feedback.report.intro')) ?>
                    </p>

                    <form method="POST" action="<?= e(route('feedback.store')) ?>" id="sg-report-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pagina_url" value="<?= e($pageUrl) ?>">
                        <input type="hidden" name="from" value="<?= e($fromCode) ?>">

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label small text-muted" for="sg-r-tipo"><?= e(t('feedback.form.tipo')) ?></label>
                                <select class="form-select" id="sg-r-tipo" name="tipo">
                                    <?php foreach ($sgTipi as $key => $meta): ?>
                                        <option value="<?= e($key) ?>"><?= e($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-5">
                                <label class="form-label small text-muted" for="sg-r-severita"><?= e(t('feedback.form.severita')) ?></label>
                                <select class="form-select" id="sg-r-severita" name="severita">
                                    <?php foreach ($sgSeverita as $key => $meta): ?>
                                        <option value="<?= e($key) ?>" <?= $key === 'media' ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted" for="sg-r-titolo"><?= e(t('feedback.form.titolo')) ?> <span class="text-muted"><?= e(t('feedback.form.optional')) ?></span></label>
                            <input type="text" class="form-control" id="sg-r-titolo" name="titolo" maxlength="200" placeholder="<?= e(t('feedback.form.titolo_placeholder')) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted" for="sg-r-descrizione"><?= e(t('feedback.form.what_happened')) ?> <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="sg-r-descrizione" name="descrizione" rows="5" maxlength="5000" required
                                      placeholder="<?= e(t('feedback.form.descr_placeholder')) ?>"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted" for="sg-r-passi"><?= e(t('feedback.form.steps')) ?> <span class="text-muted"><?= e(t('feedback.form.optional')) ?></span></label>
                            <textarea class="form-control" id="sg-r-passi" name="passi" rows="2" maxlength="5000"
                                      placeholder="<?= e(t('feedback.form.steps_placeholder')) ?>"></textarea>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= e(rtrim((string) config('app.url', ''), '/') . rtrim((string) config('app.base_path', ''), '/') . '/') ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i><?= e(t('feedback.form.submit')) ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $view->end(); ?>
