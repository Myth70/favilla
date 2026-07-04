<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
$view->pushStyle('css/reports-wizard.css');
$view->pushScript('js/reports-wizard.js');
$view->start('content');
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-wand-magic-sparkles',
    'adminTitle'    => t('reports.wizard.title'),
    'adminSubtitle' => t('reports.wizard.subtitle'),
    'adminButtons'  => '<a href="' . e(route('reports.templates.index')) . '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>' . e(t('reports.wizard.back_templates')) . '</a>',
]); ?>

<?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'templates']); ?>

<?php
$step = max(1, min(3, (int) ($_GET['step'] ?? 1)));
$selSourceType = $prefill['source_type'] ?? 'list';
$selFormat     = $prefill['output_format'] ?? 'pdf';
$selModule     = $prefill['module'] ?? '';
$selSource     = $prefill['source_key'] ?? '';

$sourceTypeCards = [
    'list' => [
        'icon'  => 'fa-list',
        'label' => t('reports.wizard.type_list_label'),
        'desc'  => t('reports.wizard.type_list_desc'),
    ],
    'document' => [
        'icon'  => 'fa-file-lines',
        'label' => t('reports.wizard.type_document_label'),
        'desc'  => t('reports.wizard.type_document_desc'),
    ],
    'aggregate' => [
        'icon'  => 'fa-chart-pie',
        'label' => t('reports.wizard.type_aggregate_label'),
        'desc'  => t('reports.wizard.type_aggregate_desc'),
    ],
];

$formatCards = [
    'pdf'   => ['icon' => 'fa-file-pdf',   'label' => 'PDF',   'desc' => t('reports.wizard.format_pdf_desc')],
    'excel' => ['icon' => 'fa-file-excel', 'label' => 'Excel', 'desc' => t('reports.wizard.format_excel_desc')],
    'csv'   => ['icon' => 'fa-file-csv',   'label' => 'CSV',   'desc' => t('reports.wizard.format_csv_desc')],
];

$selectedSource = null;
if ($selModule && $selSource && isset($sourcesByModule[$selModule])) {
    foreach ($sourcesByModule[$selModule] as $s) {
        if (($s['key'] ?? '') === $selSource) { $selectedSource = $s; break; }
    }
}
?>

<div class="rp-wizard-wrap">
    <!-- Stepper -->
    <div class="rp-wizard-stepper mb-4">
        <div class="rp-wizard-step <?= $step >= 1 ? 'is-done' : '' ?> <?= $step === 1 ? 'is-active' : '' ?>">
            <span class="rp-wizard-step-num">1</span>
            <span class="rp-wizard-step-label"><?= e(t('reports.wizard.step_type')) ?></span>
        </div>
        <div class="rp-wizard-step-line <?= $step >= 2 ? 'is-done' : '' ?>"></div>
        <div class="rp-wizard-step <?= $step >= 2 ? 'is-done' : '' ?> <?= $step === 2 ? 'is-active' : '' ?>">
            <span class="rp-wizard-step-num">2</span>
            <span class="rp-wizard-step-label"><?= e(t('reports.wizard.step_source')) ?></span>
        </div>
        <div class="rp-wizard-step-line <?= $step >= 3 ? 'is-done' : '' ?>"></div>
        <div class="rp-wizard-step <?= $step === 3 ? 'is-active' : '' ?>">
            <span class="rp-wizard-step-num">3</span>
            <span class="rp-wizard-step-label"><?= e(t('reports.wizard.step_design')) ?></span>
        </div>
    </div>

    <form method="GET" action="<?= e(route('reports.templates.new')) ?>" id="wizard-form" class="rp-wizard-form">
        <input type="hidden" name="step" value="<?= e((string) $step) ?>">

        <?php if ($step === 1): ?>
            <h4 class="mb-1"><?= e(t('reports.wizard.type_q')) ?></h4>
            <p class="text-muted mb-4"><?= e(t('reports.wizard.type_hint')) ?></p>

            <div class="rp-card-grid mb-4">
                <?php foreach ($sourceTypeCards as $key => $card): ?>
                <label class="rp-choice-card <?= $selSourceType === $key ? 'is-selected' : '' ?>">
                    <input type="radio" name="source_type" value="<?= e($key) ?>"
                           <?= $selSourceType === $key ? 'checked' : '' ?>
                           class="rp-choice-radio">
                    <div class="rp-choice-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div>
                    <div class="rp-choice-body">
                        <div class="rp-choice-title"><?= e($card['label']) ?></div>
                        <div class="rp-choice-desc"><?= e($card['desc']) ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <h5 class="mb-2"><?= e(t('reports.wizard.format_title')) ?></h5>
            <div class="rp-card-grid rp-card-grid-compact mb-4">
                <?php foreach ($formatCards as $key => $card): ?>
                <label class="rp-choice-card rp-choice-card-sm <?= $selFormat === $key ? 'is-selected' : '' ?>">
                    <input type="radio" name="output_format" value="<?= e($key) ?>"
                           <?= $selFormat === $key ? 'checked' : '' ?>
                           class="rp-choice-radio">
                    <div class="rp-choice-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></div>
                    <div class="rp-choice-body">
                        <div class="rp-choice-title"><?= e($card['label']) ?></div>
                        <div class="rp-choice-desc"><?= e($card['desc']) ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= e(route('reports.templates.index')) ?>" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('reports.wizard.cancel')) ?>
                </a>
                <button type="submit" name="step" value="2" class="btn btn-primary">
                    <?= e(t('reports.wizard.next')) ?> <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
            </div>

        <?php elseif ($step === 2): ?>
            <input type="hidden" name="source_type" value="<?= e($selSourceType) ?>">
            <input type="hidden" name="output_format" value="<?= e($selFormat) ?>">

            <h4 class="mb-1"><?= e(t('reports.wizard.source_q')) ?></h4>
            <p class="text-muted mb-4"><?= e(t('reports.wizard.source_hint')) ?></p>

            <?php if (empty($sourcesByModule)): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= e(t('reports.wizard.no_sources')) ?>
                </div>
            <?php else: ?>
            <h6 class="mb-2"><?= e(t('reports.wizard.module')) ?></h6>
            <div class="rp-card-grid rp-card-grid-compact mb-4">
                <?php foreach ($sourcesByModule as $modName => $modSources): ?>
                <label class="rp-choice-card rp-choice-card-sm <?= $selModule === $modName ? 'is-selected' : '' ?>">
                    <input type="radio" name="module" value="<?= e($modName) ?>"
                           <?= $selModule === $modName ? 'checked' : '' ?>
                           data-rp-autosubmit
                           class="rp-choice-radio">
                    <div class="rp-choice-icon"><i class="fa-solid fa-cube"></i></div>
                    <div class="rp-choice-body">
                        <div class="rp-choice-title"><?= e($modName) ?></div>
                        <div class="rp-choice-desc"><?= e(tc('reports.wizard.sources_count', count($modSources))) ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <?php if ($selModule && isset($sourcesByModule[$selModule])): ?>
                <h6 class="mb-2"><?= e(t('reports.wizard.source_label')) ?></h6>
                <div class="rp-card-grid rp-card-grid-compact mb-4">
                    <?php foreach ($sourcesByModule[$selModule] as $src): ?>
                    <label class="rp-choice-card rp-choice-card-sm <?= $selSource === ($src['key'] ?? '') ? 'is-selected' : '' ?>">
                        <input type="radio" name="source_key" value="<?= e($src['key'] ?? '') ?>"
                               <?= $selSource === ($src['key'] ?? '') ? 'checked' : '' ?>
                               data-rp-autosubmit
                               class="rp-choice-radio">
                        <div class="rp-choice-icon"><i class="fa-solid fa-database"></i></div>
                        <div class="rp-choice-body">
                            <div class="rp-choice-title"><?= e($src['label'] ?? $src['key'] ?? t('reports.wizard.source_fallback')) ?></div>
                            <?php if (!empty($src['description'])): ?>
                                <div class="rp-choice-desc"><?= e($src['description']) ?></div>
                            <?php elseif (!empty($src['fields'])): ?>
                                <div class="rp-choice-desc"><?= e(t('reports.wizard.fields_count', ['count' => count($src['fields'])])) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($selectedSource): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fa-solid fa-eye me-1"></i>
                        <?= e(t('reports.wizard.fields_preview')) ?> — <strong><?= e($selectedSource['label'] ?? $selectedSource['key']) ?></strong>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($selectedSource['fields'])): ?>
                        <div class="rp-field-chips">
                            <?php foreach ($selectedSource['fields'] as $field): ?>
                                <span class="rp-field-chip"
                                      data-bs-toggle="tooltip"
                                      title="<?= e($field['name'] ?? '') ?> (<?= e($field['type'] ?? 'string') ?>)">
                                    <?= e($field['label'] ?? $field['name'] ?? '') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <div class="text-muted small"><?= e(t('reports.wizard.no_fields')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center">
                <button type="submit" name="step" value="1" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('reports.wizard.back')) ?>
                </button>
                <button type="submit" name="step" value="3" class="btn btn-primary"
                        <?= (!$selModule || !$selSource) ? 'disabled' : '' ?>>
                    <?= e(t('reports.wizard.next')) ?> <i class="fa-solid fa-arrow-right ms-1"></i>
                </button>
            </div>

        <?php else: ?>
            <?php
            $designerUrl = route('reports.templates.create') . '?' . http_build_query([
                'source_type'   => $selSourceType,
                'output_format' => $selFormat,
                'module'        => $selModule,
                'source_key'    => $selSource,
            ]);
            ?>

            <h4 class="mb-1"><?= e(t('reports.wizard.ready_title')) ?></h4>
            <p class="text-muted mb-4"><?= e(t('reports.wizard.ready_hint')) ?></p>

            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4"><?= e(t('reports.wizard.summary_type')) ?></dt>
                        <dd class="col-sm-8"><?= e($sourceTypeCards[$selSourceType]['label'] ?? $selSourceType) ?></dd>
                        <dt class="col-sm-4"><?= e(t('reports.wizard.summary_format')) ?></dt>
                        <dd class="col-sm-8"><?= e(strtoupper($selFormat)) ?></dd>
                        <dt class="col-sm-4"><?= e(t('reports.wizard.summary_module')) ?></dt>
                        <dd class="col-sm-8"><?= e($selModule) ?></dd>
                        <dt class="col-sm-4"><?= e(t('reports.wizard.summary_source')) ?></dt>
                        <dd class="col-sm-8"><?= e($selectedSource['label'] ?? $selSource) ?></dd>
                    </dl>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= e(route('reports.templates.new')) ?>?step=2&source_type=<?= e($selSourceType) ?>&output_format=<?= e($selFormat) ?>&module=<?= e($selModule) ?>&source_key=<?= e($selSource) ?>" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i><?= e(t('reports.wizard.back')) ?>
                </a>
                <a href="<?= e($designerUrl) ?>" class="btn btn-primary">
                    <?= e(t('reports.wizard.open_designer')) ?> <i class="fa-solid fa-wand-magic-sparkles ms-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php $view->end(); ?>
