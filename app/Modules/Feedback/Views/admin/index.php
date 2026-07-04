<?php $view->layout('main'); ?>
<?php $view->start('content'); ?>
<?php
use App\Modules\Feedback\Services\FeedbackService;

$tipiMeta     = FeedbackService::tipiMeta();
$severitaMeta = FeedbackService::severitaMeta();
$statiMeta    = FeedbackService::statiMeta();
$f            = $filters ?? [];
?>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'     => 'fa-solid fa-bug',
        'adminTitle'    => t('feedback.admin_title'),
        'adminSubtitle' => t('feedback.admin_subtitle'),
    ]); ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form id="sg-filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted" for="sg-f-q"><?= e(t('feedback.filters.search')) ?></label>
                    <input type="search" class="form-control form-control-sm" id="sg-f-q" name="q"
                           value="<?= e((string) ($f['q'] ?? '')) ?>"
                           placeholder="<?= e(t('feedback.filters.search_placeholder')) ?>"
                           hx-get="<?= e(route('feedback.admin.index')) ?>"
                           hx-trigger="keyup changed delay:400ms, search"
                           hx-target="#sg-table" hx-include="#sg-filter-form" hx-push-url="true">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted" for="sg-f-stato"><?= e(t('feedback.filters.stato')) ?></label>
                    <select class="form-select form-select-sm" id="sg-f-stato" name="stato"
                            hx-get="<?= e(route('feedback.admin.index')) ?>" hx-trigger="change"
                            hx-target="#sg-table" hx-include="#sg-filter-form" hx-push-url="true">
                        <option value=""><?= e(t('feedback.filters.all_f')) ?></option>
                        <?php foreach ($statiMeta as $k => $m): ?>
                            <option value="<?= e($k) ?>" <?= ($f['stato'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted" for="sg-f-tipo"><?= e(t('feedback.filters.tipo')) ?></label>
                    <select class="form-select form-select-sm" id="sg-f-tipo" name="tipo"
                            hx-get="<?= e(route('feedback.admin.index')) ?>" hx-trigger="change"
                            hx-target="#sg-table" hx-include="#sg-filter-form" hx-push-url="true">
                        <option value=""><?= e(t('feedback.filters.all_m')) ?></option>
                        <?php foreach ($tipiMeta as $k => $m): ?>
                            <option value="<?= e($k) ?>" <?= ($f['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted" for="sg-f-severita"><?= e(t('feedback.filters.severita')) ?></label>
                    <select class="form-select form-select-sm" id="sg-f-severita" name="severita"
                            hx-get="<?= e(route('feedback.admin.index')) ?>" hx-trigger="change"
                            hx-target="#sg-table" hx-include="#sg-filter-form" hx-push-url="true">
                        <option value=""><?= e(t('feedback.filters.all_f')) ?></option>
                        <?php foreach ($severitaMeta as $k => $m): ?>
                            <option value="<?= e($k) ?>" <?= ($f['severita'] ?? '') === $k ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted" for="sg-f-modulo"><?= e(t('feedback.filters.modulo')) ?></label>
                    <select class="form-select form-select-sm" id="sg-f-modulo" name="modulo"
                            hx-get="<?= e(route('feedback.admin.index')) ?>" hx-trigger="change"
                            hx-target="#sg-table" hx-include="#sg-filter-form" hx-push-url="true">
                        <option value=""><?= e(t('feedback.filters.all_m')) ?></option>
                        <?php foreach (($moduli ?? []) as $mod): ?>
                            <option value="<?= e((string) $mod) ?>" <?= ($f['modulo'] ?? '') === (string) $mod ? 'selected' : '' ?>><?= e((string) $mod) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div id="sg-table">
        <?php $view->include('Feedback/Views/admin/partials/table', get_defined_vars()); ?>
    </div>
</div>

<?php $view->end(); ?>
