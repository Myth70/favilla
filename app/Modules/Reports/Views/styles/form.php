<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
$view->pushScript('js/reports.js');
$view->start('content');
?>

<?php

$isEdit = $style !== null;
$errors = $errors ?? [];
$old    = $old ?? [];

$fv = function (string $k, $default = '') use ($old, $style) {
    if (array_key_exists($k, $old))          return $old[$k];
    if ($style && array_key_exists($k, $style)) return $style[$k];
    return $default;
};
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');

// Font options available in DOMPDF
$fontOptions = [
    'dejavusans'  => 'DejaVu Sans',
    'dejavuserif' => 'DejaVu Serif',
    'freesans'    => 'Free Sans',
    'freeserif'   => 'Free Serif',
    'helvetica'   => 'Helvetica',
    'times'       => 'Times New Roman',
    'courier'     => 'Courier',
];
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon' => $isEdit ? 'fa-solid fa-swatchbook' : 'fa-solid fa-palette',
        'adminTitle' => $isEdit ? t('reports.style.title_edit') : t('reports.style.title_new'),
        'adminSubtitle' => t('reports.style.subtitle'),
        'adminButtons' => '<a href="' . e(route('reports.templates.index')) . '" class="btn btn-sm btn-outline-secondary">'
            . '<i class="fa-solid fa-arrow-left"></i>' . e(t('reports.style.back_templates')) . '</a>',
    ]); ?>

    <?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'templates']); ?>

    <div class="row g-4">

        <!-- LEFT: Form -->
        <div class="col-lg-7">
            <?php $view->include('partials/app-form-errors', [
                'errors' => $errors,
                'summaryTitle' => t('reports.style.fix_errors'),
            ]); ?>

            <form method="POST"
                  action="<?= $isEdit
                      ? e(route('reports.styles.update', ['id' => $style['id']]))
                      : e(route('reports.styles.store')) ?>"
                  enctype="multipart/form-data"
                  id="rp-style-form"
                  novalidate data-app-form>
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <!-- Sezione: Identità -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('reports.style.identity_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="rp-identity-body">
                        <span class="app-card-icon"><i class="fa-solid fa-palette"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= $isEdit ? e(t('reports.style.identity_edit')) : e(t('reports.style.identity_new')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="rp-identity-body">
                        <div class="mb-3">
                            <label for="name" class="form-label"><?= e(t('reports.style.name')) ?> <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="<?= $fc('name') ?>"
                                   id="name" name="name"
                                   value="<?= e($fv('name')) ?>"
                                   maxlength="150"
                                   required aria-required="true"
                                   aria-invalid="<?= $fe('name') ? 'true' : 'false' ?>"
                                   aria-describedby="name-feedback">
                            <div id="name-feedback" class="invalid-feedback"><?= e($fe('name') ?? t('reports.style.name_invalid')) ?></div>
                        </div>

                        <div class="mb-0">
                            <label for="description" class="form-label"><?= e(t('reports.style.description')) ?></label>
                            <textarea class="<?= $fc('description') ?>"
                                      id="description" name="description"
                                      rows="2" maxlength="500"
                                      aria-invalid="<?= $fe('description') ? 'true' : 'false' ?>"
                                      aria-describedby="description-feedback"><?= e($fv('description')) ?></textarea>
                            <div id="description-feedback" class="invalid-feedback"><?= e($fe('description') ?? t('reports.style.description_invalid')) ?></div>
                        </div>
                    </div>
                </fieldset>

                <!-- Sezione: Colori -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('reports.style.colors_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="rp-colors-body">
                        <span class="app-card-icon"><i class="fa-solid fa-droplet"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= e(t('reports.style.colors')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="rp-colors-body">
                        <div class="row g-3">
                            <?php
                            $colorFields = [
                                'primary_color'     => [t('reports.style.color_primary'),     '#3b82f6'],
                                'secondary_color'   => [t('reports.style.color_secondary'),   '#64748b'],
                                'accent_color'      => [t('reports.style.color_accent'),      '#f97316'],
                                'header_bg_color'   => [t('reports.style.color_header_bg'),   '#1e293b'],
                                'header_text_color' => [t('reports.style.color_header_text'), '#ffffff'],
                                'zebra_color'       => [t('reports.style.color_zebra'),       '#f8fafc'],
                            ];
                            foreach ($colorFields as $fname => [$label, $def]):
                                $val = (string) $fv($fname, $def);
                            ?>
                            <div class="col-md-4">
                                <label for="<?= e($fname) ?>" class="form-label small"><?= e($label) ?></label>
                                <div class="input-group input-group-sm">
                                    <input type="color" class="form-control form-control-color rp-color-input"
                                           id="<?= e($fname) ?>" name="<?= e($fname) ?>"
                                           value="<?= e($val) ?>">
                                    <input type="text" class="form-control rp-color-text" readonly
                                           value="<?= e($val) ?>">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </fieldset>

                <!-- Sezione: Tipografia -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('reports.style.typo_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="rp-typo-body">
                        <span class="app-card-icon"><i class="fa-solid fa-font"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= e(t('reports.style.typography')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="rp-typo-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="font_family" class="form-label small"><?= e(t('reports.style.font')) ?></label>
                                <select class="<?= $fc('font_family', 'form-select form-select-sm') ?>"
                                        id="font_family" name="font_family"
                                        aria-invalid="<?= $fe('font_family') ? 'true' : 'false' ?>">
                                    <?php $currentFont = (string) $fv('font_family', 'dejavusans'); ?>
                                    <?php foreach ($fontOptions as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= $currentFont === $val ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($fe('font_family')): ?>
                                    <div class="invalid-feedback"><?= e($fe('font_family')) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label for="font_size_base" class="form-label small"><?= e(t('reports.style.font_size')) ?></label>
                                <input type="number"
                                       class="<?= $fc('font_size_base', 'form-control form-control-sm') ?>"
                                       id="font_size_base" name="font_size_base"
                                       value="<?= e($fv('font_size_base', '9')) ?>"
                                       min="6" max="18"
                                       inputmode="numeric"
                                       aria-invalid="<?= $fe('font_size_base') ? 'true' : 'false' ?>">
                                <?php if ($fe('font_size_base')): ?>
                                    <div class="invalid-feedback"><?= e($fe('font_size_base')) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- Sezione: Loghi -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('reports.style.logos_legend')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="rp-logos-body">
                        <span class="app-card-icon"><i class="fa-solid fa-image"></i></span>
                        <span class="fw-semibold flex-grow-1"><?= e(t('reports.style.logos')) ?></span>
                        <i class="fa-solid fa-chevron-down app-chevron"></i>
                    </div>
                    <div class="app-form-section-body" id="rp-logos-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small"><?= e(t('reports.style.logo_primary')) ?></label>
                                <?php if ($isEdit && !empty($style['logo_path'])): ?>
                                <div class="mb-2 rp-logo-preview">
                                    <img src="<?= e(route('reports.styles.logo', ['id' => (int) $style['id'], 'slot' => 'primary'])) ?>"
                                         alt="<?= e(t('reports.style.logo_primary')) ?>" class="img-thumbnail rp-logo-thumb">
                                    <label class="form-check-label small ms-2">
                                        <input type="checkbox" name="remove_logo" value="1" class="form-check-input">
                                        <?= e(t('reports.style.remove')) ?>
                                    </label>
                                </div>
                                <?php endif; ?>
                                <div class="input-group input-group-sm">
                                    <input type="text"
                                           id="rp-logo-file-name"
                                           class="form-control"
                                           value="<?= e(t('reports.style.no_file')) ?>"
                                           readonly
                                           aria-label="<?= e(t('reports.style.logo_primary')) ?>">
                                    <label class="btn btn-outline-secondary text-nowrap" for="rp-logo-file">
                                        <i class="fa-solid fa-paperclip me-1"></i><?= e(t('reports.style.choose_file')) ?>
                                    </label>
                                    <input type="file"
                                           class="visually-hidden"
                                           id="rp-logo-file"
                                           name="logo"
                                           accept="image/png,image/jpeg,image/svg+xml"
                                           data-app-file-target="rp-logo-file-name"
                                           data-app-file-placeholder="<?= e(t('reports.style.no_file')) ?>">
                                </div>
                                <div class="form-text"><?= e(t('reports.style.logo_hint')) ?></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small"><?= e(t('reports.style.logo_secondary')) ?></label>
                                <?php if ($isEdit && !empty($style['logo_secondary_path'])): ?>
                                <div class="mb-2 rp-logo-preview">
                                    <img src="<?= e(route('reports.styles.logo', ['id' => (int) $style['id'], 'slot' => 'secondary'])) ?>"
                                         alt="<?= e(t('reports.style.logo_secondary')) ?>" class="img-thumbnail rp-logo-thumb">
                                    <label class="form-check-label small ms-2">
                                        <input type="checkbox" name="remove_logo_secondary" value="1" class="form-check-input">
                                        <?= e(t('reports.style.remove')) ?>
                                    </label>
                                </div>
                                <?php endif; ?>
                                <div class="input-group input-group-sm">
                                    <input type="text"
                                           id="rp-logo-secondary-file-name"
                                           class="form-control"
                                           value="<?= e(t('reports.style.no_file')) ?>"
                                           readonly
                                           aria-label="<?= e(t('reports.style.logo_secondary')) ?>">
                                    <label class="btn btn-outline-secondary text-nowrap" for="rp-logo-secondary-file">
                                        <i class="fa-solid fa-paperclip me-1"></i><?= e(t('reports.style.choose_file')) ?>
                                    </label>
                                    <input type="file"
                                           class="visually-hidden"
                                           id="rp-logo-secondary-file"
                                           name="logo_secondary"
                                           accept="image/png,image/jpeg,image/svg+xml"
                                           data-app-file-target="rp-logo-secondary-file-name"
                                           data-app-file-placeholder="<?= e(t('reports.style.no_file')) ?>">
                                </div>
                                <div class="form-text"><?= e(t('reports.style.logo_hint')) ?></div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="<?= e(route('reports.templates.index')) ?>" class="btn btn-outline-secondary"><?= e(t('reports.style.cancel')) ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check me-1"></i>
                        <?= $isEdit ? e(t('reports.style.update')) : e(t('reports.style.create')) ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- RIGHT: Live Preview -->
        <div class="col-lg-5">
            <div class="card shadow-sm position-sticky rp-preview-sticky">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fa-solid fa-eye me-2"></i><?= e(t('reports.style.preview')) ?></h6>
                </div>
                <div class="card-body" id="rp-style-preview">
                    <div class="rp-preview-page p-3 border rounded"
                         style="--rp-preview-primary: <?= e($fv('primary_color', '#3b82f6')) ?>;
                                --rp-preview-header-bg: <?= e($fv('header_bg_color', '#1e293b')) ?>;
                                --rp-preview-header-text: <?= e($fv('header_text_color', '#ffffff')) ?>;
                                --rp-preview-zebra: <?= e($fv('zebra_color', '#f8fafc')) ?>;">
                        <!-- Preview header -->
                        <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded rp-preview-header-bar" id="rp-preview-header-bar">
                            <div class="rp-preview-logo-chip rounded px-2 py-1">
                                <i class="fa-solid fa-image opacity-50"></i>
                            </div>
                            <div>
                                <div class="fw-bold small rp-preview-header-title"><?= e(t('reports.style.preview_title')) ?></div>
                                <div class="rp-preview-subtitle"><?= e(t('reports.style.preview_subtitle')) ?></div>
                            </div>
                        </div>

                        <!-- Preview table -->
                        <table class="table table-sm table-bordered mb-0 rp-table-sm-text" id="rp-preview-table">
                            <thead>
                                <tr id="rp-preview-thead" class="rp-preview-thead">
                                    <th><?= e(t('reports.style.preview_col_id')) ?></th>
                                    <th><?= e(t('reports.style.preview_col_name')) ?></th>
                                    <th><?= e(t('reports.style.preview_col_value')) ?></th>
                                    <th><?= e(t('reports.style.preview_col_date')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td><td><?= e(t('reports.style.preview_item')) ?> A</td><td>1.250,00</td><td>01/01/2026</td>
                                </tr>
                                <tr id="rp-preview-zebra" class="rp-preview-zebra">
                                    <td>2</td><td><?= e(t('reports.style.preview_item')) ?> B</td><td>3.470,50</td><td>15/02/2026</td>
                                </tr>
                                <tr>
                                    <td>3</td><td><?= e(t('reports.style.preview_item')) ?> C</td><td>890,00</td><td>28/02/2026</td>
                                </tr>
                                <tr class="rp-preview-zebra">
                                    <td>4</td><td><?= e(t('reports.style.preview_item')) ?> D</td><td>5.100,75</td><td>10/03/2026</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Preview footer -->
                        <div class="d-flex justify-content-between mt-2 rp-preview-footer">
                            <span class="text-muted" id="rp-preview-font-label">
                                <?= e($fontOptions[(string) $fv('font_family', 'dejavusans')] ?? 'DejaVu Sans') ?>
                                — <?= e($fv('font_size_base', '9')) ?>pt
                            </span>
                            <span class="text-muted"><?= e(t('reports.style.preview_page')) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php $view->end(); ?>
