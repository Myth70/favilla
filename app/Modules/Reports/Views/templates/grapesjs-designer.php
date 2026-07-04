<?php
$view->layout('main');
$view->pushStyle('css/reports.css');
// GrapeJS CSS local (CSP-safe)
$view->pushStyle('css/vendor/grapesjs/grapes.min.css');
$view->pushStyle('css/reports-grapesjs.css');
// GrapeJS JS local (CSP-safe)
$view->pushScript('js/vendor/grapesjs/grapes.min.js');
$view->pushScript('js/reports-grapesjs.js');
$view->start('content');
?>

<?php $view->include('Reports/Views/partials/subnav', ['activeTab' => 'templates']); ?>

<?php
$isEdit = $template !== null;
$old = $_SESSION['_old'] ?? [];
$errorsBag = $_SESSION['_errors'] ?? [];
$tplName = $old['name'] ?? $template['name'] ?? '';
$tplModule = $old['module'] ?? $template['module'] ?? $preModule ?? '';
$tplSourceKey = $old['source_key'] ?? $template['source_key'] ?? $preSource ?? '';
$tplFormat = $old['output_format'] ?? $template['output_format'] ?? 'pdf';
$tplSourceType = $old['source_type'] ?? $template['source_type'] ?? 'list';
$tplStyleId = $old['style_preset_id'] ?? $template['style_preset_id'] ?? '';
$tplVisibility = $old['visibility'] ?? $template['visibility'] ?? 'private';
$tplMaxRows = $old['max_rows'] ?? $template['max_rows'] ?? 10000;
$tplDescription = $old['description'] ?? $template['description'] ?? '';
$tplHtml = $old['template_html'] ?? $template['template_html'] ?? '';
$starterPreset = $_GET['source_type'] ?? ($template['source_type'] ?? 'list');
$nameErr = $errorsBag['name'] ?? null;
$moduleErr = $errorsBag['module'] ?? null;
$sourceErr = $errorsBag['source_key'] ?? null;
$errorMessages = [];
foreach ($errorsBag as $fieldErrors) {
    foreach ((array) $fieldErrors as $message) {
        $errorMessages[] = (string) $message;
    }
}
unset($_SESSION['_old'], $_SESSION['_errors']);

// Flatten sources
$flatSources = [];
$sourcesByModule = [];
foreach ($sources as $group) {
    $mod = $group['module'] ?? 'Altro';
    foreach ($group['sources'] ?? [] as $src) {
        $src['module'] = $mod;
        $flatSources[$src['key']] = $src;
        $sourcesByModule[$mod][$src['key']] = $src;
    }
}
ksort($sourcesByModule);

// Prepare merge fields data
$mergeFields = [];
if (!empty($flatSources[$tplSourceKey])) {
    $fields = $flatSources[$tplSourceKey]['fields'] ?? [];
    foreach ($fields as $field) {
        $fieldName = $field['name'] ?? $field['key'] ?? null;
        if ($fieldName === null) continue;
        $mergeFields[$fieldName] = $field['label'] ?? ucfirst($fieldName);
    }
}
?>

<?php
$designerButtons = '<a href="' . e(route('reports.templates.index')) . '" class="btn btn-sm btn-outline-secondary">'
    . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('reports.designer.back_templates')) . '</a>';
if (!$isEdit) {
    $designerButtons .= ' <a href="' . e(route('reports.templates.new')) . '" class="btn btn-sm btn-outline-secondary">'
        . '<i class="fa-solid fa-wand-magic-sparkles me-1"></i>' . e(t('reports.designer.wizard')) . '</a>';
}
?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => $isEdit ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-wand-magic-sparkles',
    'adminTitle'    => $isEdit ? t('reports.designer.title_edit') : t('reports.designer.title_new'),
    'adminSubtitle' => $isEdit ? e((string) ($template['name'] ?? '')) : t('reports.designer.subtitle'),
    'adminButtons'  => $designerButtons,
]); ?>

<?php $view->include('partials/app-form-errors', [
    'errors' => $errorsBag,
    'summaryTitle' => t('reports.designer.fix_errors'),
]); ?>

<form method="POST"
      action="<?= $isEdit ? e(route('reports.templates.update', ['id' => $template['id']])) : e(route('reports.templates.store')) ?>"
      id="template-form"
      data-template-form="true">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" id="template-id" value="<?= e($template['id']) ?>">
    <?php endif; ?>

    <!-- Hidden HTML template -->
    <input type="hidden" id="template-html-input" name="template_html" value="<?= e($tplHtml) ?>">
    <input type="hidden" id="template-starter-preset" value="<?= e($starterPreset) ?>">

<?php
// System fields available for insertion (must match those resolved server-side)
$systemFields = [
    'data_report'      => t('reports.designer.sf_data_report'),
    'utente'           => t('reports.designer.sf_utente'),
    'nome_azienda'     => t('reports.designer.sf_nome_azienda'),
    'titolo_report'    => t('reports.designer.sf_titolo_report'),
    'pagina'           => t('reports.designer.sf_pagina'),
    'totale_pagine'    => t('reports.designer.sf_totale_pagine'),
];
?>

    <!-- ================================================================
         DESIGNER CONTROLS PANEL
         ================================================================ -->
    <div class="reports-designer-controls">
        <div class="flex-grow-1">
            <h5 class="mb-0"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> <?= e(t('reports.designer.heading')) ?></h5>
        </div>

        <!-- Insert-field dropdowns (source + system) -->
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(t('reports.designer.insert_fields')) ?>">
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        data-bs-toggle-tooltip="1" title="<?= e(t('reports.designer.source_field_tip')) ?>">
                    <i class="fa-solid fa-tags me-1"></i><?= e(t('reports.designer.source_field')) ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end rp-field-dropdown p-2" id="rp-source-fields-dropdown">
                    <input type="search" class="form-control form-control-sm mb-2"
                           id="rp-source-fields-search" placeholder="<?= e(t('reports.designer.search_field')) ?>"
                           autocomplete="off">
                    <div class="rp-field-dropdown-list" id="rp-source-fields-list">
                        <?php if (empty($mergeFields)): ?>
                            <div class="text-muted small px-2 py-1"><?= e(t('reports.designer.select_source_first')) ?></div>
                        <?php else: ?>
                            <?php foreach ($mergeFields as $fieldKey => $fieldLabel): ?>
                                <button type="button" class="dropdown-item merge-field-btn rp-field-dropdown-item"
                                        data-field="<?= e($fieldKey) ?>">
                                    <span class="fw-semibold"><?= e($fieldLabel) ?></span>
                                    <small class="text-muted d-block">{{ <?= e($fieldKey) ?> }}</small>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false"
                        data-bs-toggle-tooltip="1" title="<?= e(t('reports.designer.system_field_tip')) ?>">
                    <i class="fa-solid fa-gear me-1"></i><?= e(t('reports.designer.system_field')) ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end rp-field-dropdown p-2">
                    <div class="rp-field-dropdown-list">
                        <?php foreach ($systemFields as $sfKey => $sfLabel): ?>
                            <button type="button" class="dropdown-item merge-field-btn rp-field-dropdown-item"
                                    data-field="<?= e($sfKey) ?>">
                                <span class="fw-semibold"><?= e($sfLabel) ?></span>
                                <small class="text-muted d-block">{{ <?= e($sfKey) ?> }}</small>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="vr mx-1"></div>

        <div class="btn-group btn-group-sm" role="group" aria-label="<?= e(t('reports.designer.devices')) ?>">
            <button type="button" id="device-desktop-btn" class="btn btn-outline-secondary is-active" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.device_desktop')) ?>">
                <i class="fa-solid fa-desktop"></i>
            </button>
            <button type="button" id="device-tablet-btn" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.device_tablet')) ?>">
                <i class="fa-solid fa-tablet-screen-button"></i>
            </button>
            <button type="button" id="device-mobile-btn" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.device_mobile')) ?>">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </button>
        </div>

        <div class="vr mx-1"></div>

        <div class="btn-group btn-group-sm" role="group">
            <button type="button" id="undo-btn" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.undo')) ?>">
                <i class="fa-solid fa-rotate-left"></i>
            </button>
            <button type="button" id="redo-btn" class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.redo')) ?>">
                <i class="fa-solid fa-rotate-right"></i>
            </button>
        </div>

        <div class="vr mx-1"></div>

        <button type="button" id="code-edit-btn" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.edit_code')) ?>">
            <i class="fa-solid fa-code"></i>
        </button>
        <button type="button" id="import-btn" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.import_html')) ?>">
            <i class="fa-solid fa-file-import"></i>
        </button>
        <button type="button" id="export-btn" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.export_html')) ?>">
            <i class="fa-solid fa-file-export"></i>
        </button>
        <button type="button" id="preview-btn" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.preview_window')) ?>">
            <i class="fa-solid fa-eye"></i>
        </button>

        <div class="vr mx-1"></div>

        <button type="button" id="fullscreen-btn" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.fullscreen')) ?>">
            <i class="fa-solid fa-expand"></i>
        </button>
        <button type="button" id="clear-btn" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="<?= e(t('reports.designer.clear_canvas')) ?>">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>

    <!-- Save indicator -->
    <div id="save-indicator" role="status" aria-live="polite"><?= e(t('reports.designer.saved')) ?></div>

    <!-- Main editor layout -->
    <div class="reports-designer-editor">
        <!-- Left panel: Blocks -->
        <aside class="reports-gjs-sidebar reports-gjs-sidebar-left">
            <div class="reports-gjs-sidebar-header">
                <span class="fw-semibold"><?= e(t('reports.designer.blocks')) ?></span>
            </div>
            <div id="grapesjs-blocks" class="gjs-blocks-panel reports-gjs-sidebar-body"></div>
        </aside>

        <!-- Center: Canvas -->
        <div id="grapesjs-editor" class="reports-gjs-canvas" data-bootstrap-css="<?= e(asset('css/bootstrap.min.css')) ?>"></div>

        <!-- Right panel: Properties -->
        <aside class="reports-gjs-sidebar reports-gjs-sidebar-right">
            <div class="reports-gjs-sidebar-header p-0">
                <div class="nav nav-tabs reports-gjs-tabs" role="tablist" aria-label="<?= e(t('reports.designer.panels')) ?>">
                    <button type="button" class="reports-gjs-tab is-active" data-gjs-panel-tab="styles"><?= e(t('reports.designer.tab_styles')) ?></button>
                    <button type="button" class="reports-gjs-tab" data-gjs-panel-tab="traits"><?= e(t('reports.designer.tab_traits')) ?></button>
                    <button type="button" class="reports-gjs-tab" data-gjs-panel-tab="layers"><?= e(t('reports.designer.tab_layers')) ?></button>
                </div>
            </div>
            <div class="reports-gjs-sidebar-body reports-gjs-tab-panels">
                <section class="reports-gjs-tab-panel is-active" data-gjs-panel="styles">
                    <div id="grapesjs-selectors"></div>
                    <div id="grapesjs-styles"></div>
                </section>
                <section class="reports-gjs-tab-panel" data-gjs-panel="traits">
                    <div id="grapesjs-traits"></div>
                </section>
                <section class="reports-gjs-tab-panel" data-gjs-panel="layers">
                    <div id="grapesjs-layers"></div>
                </section>
            </div>
        </aside>
    </div>

    <!-- ================================================================
         TEMPLATE CONFIGURATION PANEL
         ================================================================ -->
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Template metadata -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><?= e(t('reports.designer.info_title')) ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label"><?= e(t('reports.designer.name')) ?></label>
                            <input type="text" id="name" name="name" class="form-control <?= $nameErr ? 'is-invalid' : '' ?>"
                                   value="<?= e($tplName) ?>" required>
                            <?php if ($nameErr): ?>
                                <div class="invalid-feedback"><?= e($nameErr[0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label"><?= e(t('reports.designer.description')) ?></label>
                            <textarea id="description" name="description" class="form-control" rows="2"><?= e($tplDescription) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="max_rows" class="form-label"><?= e(t('reports.designer.max_rows')) ?></label>
                            <input type="number" id="max_rows" name="max_rows" class="form-control"
                                   value="<?= e($tplMaxRows) ?>" min="1" max="100000">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data source & styling -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><?= e(t('reports.designer.source_title')) ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="module" class="form-label"><?= e(t('reports.designer.module')) ?></label>
                            <select id="module" name="module" class="form-select <?= $moduleErr ? 'is-invalid' : '' ?>" required>
                                <option value=""><?= e(t('reports.designer.select')) ?></option>
                                <?php foreach ($sourcesByModule as $modName => $modSources): ?>
                                    <option value="<?= e($modName) ?>" <?= $tplModule === $modName ? 'selected' : '' ?>><?= e($modName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($moduleErr): ?>
                                <div class="invalid-feedback"><?= e($moduleErr[0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="source_key" class="form-label"><?= e(t('reports.designer.source_data')) ?></label>
                            <select id="source_key" name="source_key" class="form-select <?= $sourceErr ? 'is-invalid' : '' ?>" required>
                                <option value=""><?= e(t('reports.designer.select')) ?></option>
                                <?php foreach ($flatSources as $key => $src): ?>
                                    <option value="<?= e($key) ?>" data-module="<?= e($src['module']) ?>"
                                            <?= $tplSourceKey === $key ? 'selected' : '' ?>>
                                        <?= e($src['label'] ?? $key) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($sourceErr): ?>
                                <div class="invalid-feedback"><?= e($sourceErr[0]) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="style_preset_id" class="form-label"><?= e(t('reports.designer.style')) ?></label>
                            <div class="input-group">
                                <select id="style_preset_id" name="style_preset_id" class="form-select">
                                    <option value=""><?= e(t('reports.designer.style_default')) ?></option>
                                    <?php foreach ($stylePresets as $preset): ?>
                                        <option value="<?= e($preset['id']) ?>" <?= (int)$tplStyleId === (int)$preset['id'] ? 'selected' : '' ?>>
                                            <?= e($preset['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (has_permission('reports.styles')): ?>
                                    <button type="button" class="btn btn-outline-secondary" id="rp-style-edit-btn"
                                            data-bs-toggle="tooltip" title="<?= e(t('reports.designer.style_edit_tip')) ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="rp-style-new-btn"
                                            data-bs-toggle="tooltip" title="<?= e(t('reports.designer.style_new_tip')) ?>">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="visibility" class="form-label"><?= e(t('reports.designer.visibility')) ?></label>
                            <select id="visibility" name="visibility" class="form-select">
                                <option value="private" <?= $tplVisibility === 'private' ? 'selected' : '' ?>><?= e(t('reports.designer.vis_private')) ?></option>
                                <option value="role" <?= $tplVisibility === 'role' ? 'selected' : '' ?>><?= e(t('reports.designer.vis_role')) ?></option>
                                <option value="global" <?= $tplVisibility === 'global' ? 'selected' : '' ?>><?= e(t('reports.designer.vis_global')) ?></option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex gap-2 justify-content-end">
                    <a href="<?= e(route('reports.templates.index')) ?>" class="btn btn-secondary"><?= e(t('reports.designer.cancel')) ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> <?= e(t('reports.designer.save')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php
$showBindings = $isEdit
    && $template !== null
    && ($template['source_type'] ?? '') === 'document'
    && has_permission('reports.documents');
$documentBindings = $documentBindings ?? [];
?>
<?php if ($showBindings): ?>
<div class="card mt-4" id="rp-bindings-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fa-solid fa-link me-2"></i><?= e(t('reports.designer.bind_used_as')) ?>
        </h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="rp-binding-new-btn"
                data-bs-toggle="tooltip" title="<?= e(t('reports.designer.bind_new')) ?>">
            <i class="fa-solid fa-plus me-1"></i><?= e(t('reports.designer.bind_new')) ?>
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle" id="rp-bindings-table">
                <thead>
                    <tr>
                        <th><?= e(t('reports.designer.bind_col_module')) ?></th>
                        <th><?= e(t('reports.designer.bind_col_label')) ?></th>
                        <th><code class="small">operation</code></th>
                        <th class="text-end" style="width:120px;"><?= e(t('reports.designer.bind_col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="rp-bindings-tbody">
                    <?php if (empty($documentBindings)): ?>
                        <tr id="rp-bindings-empty">
                            <td colspan="4" class="text-center text-muted py-3">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                <?= e(t('reports.designer.bind_empty')) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documentBindings as $b): ?>
                        <tr data-binding-id="<?= (int) $b['id'] ?>">
                            <td><?= e($b['module']) ?></td>
                            <td><?= e($b['label']) ?></td>
                            <td><code class="small"><?= e($b['operation']) ?></code></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary rp-binding-edit-btn"
                                        data-bs-toggle="tooltip" title="<?= e(t('reports.designer.bind_edit')) ?>">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger rp-binding-delete-btn"
                                        data-bs-toggle="tooltip" title="<?= e(t('reports.designer.bind_delete')) ?>">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="rpBindingModal" tabindex="-1" aria-labelledby="rpBindingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="rp-binding-modal-form" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="rp-binding-modal-method" value="">
                <input type="hidden" name="template_id" value="<?= (int) $template['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="rpBindingModalLabel">
                        <i class="fa-solid fa-link"></i><span id="rp-binding-modal-title"><?= e(t('reports.designer.bind_modal_title')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div id="rp-binding-modal-alert" class="alert alert-danger d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="rp-binding-module" class="form-label"><?= e(t('reports.designer.bind_module')) ?> <span class="text-danger">*</span></label>
                        <select class="form-select" id="rp-binding-module" name="module" required>
                            <option value=""><?= e(t('reports.designer.bind_module_select')) ?></option>
                            <?php foreach (array_keys($sourcesByModule) as $modName): ?>
                                <option value="<?= e($modName) ?>"><?= e($modName) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small"><?= e(t('reports.designer.bind_module_hint')) ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="rp-binding-label" class="form-label"><?= e(t('reports.designer.bind_label')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rp-binding-label" name="label"
                               placeholder="<?= e(t('reports.designer.bind_label_ph')) ?>" data-autoslug-source required>
                    </div>

                    <div class="mb-0">
                        <details id="rp-binding-advanced">
                            <summary class="small text-muted" style="cursor:pointer;">
                                <i class="fa-solid fa-sliders me-1"></i><?= e(t('reports.designer.bind_advanced')) ?>
                            </summary>
                            <div class="mt-2">
                                <label for="rp-binding-operation" class="form-label small">
                                    <?= e(t('reports.designer.bind_operation')) ?> <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-sm font-monospace"
                                       id="rp-binding-operation" name="operation"
                                       pattern="[a-z0-9_]+" autocomplete="off"
                                       data-autoslug-target required>
                                <div class="form-text small">
                                    <?= t('reports.designer.bind_operation_hint') ?>
                                </div>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('reports.designer.cancel_short')) ?></button>
                    <button type="submit" class="btn btn-primary" id="rp-binding-modal-submit">
                        <i class="fa-solid fa-check me-1"></i><?= e(t('reports.designer.save_short')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">
    window.__rpBindingRoutes = {
        store:   <?= json_encode(route('reports.documents.store')) ?>,
        edit:    <?= json_encode(str_replace('__ID__', '0', route('reports.documents.edit',    ['id' => '__ID__']))) ?>,
        update:  <?= json_encode(str_replace('__ID__', '0', route('reports.documents.update',  ['id' => '__ID__']))) ?>,
        destroy: <?= json_encode(str_replace('__ID__', '0', route('reports.documents.destroy', ['id' => '__ID__']))) ?>,
    };
</script>
<?php endif; ?>

<?php if (has_permission('reports.styles')): ?>
<?php
$rpStyleFontOptions = [
    'dejavusans'  => 'DejaVu Sans',
    'dejavuserif' => 'DejaVu Serif',
    'freesans'    => 'Free Sans',
    'freeserif'   => 'Free Serif',
    'helvetica'   => 'Helvetica',
    'times'       => 'Times New Roman',
    'courier'     => 'Courier',
];
?>
<!-- ================================================================
     STYLE PRESET MODAL (create/edit)
     ================================================================ -->
<div class="modal fade" id="stylePresetModal" tabindex="-1" aria-labelledby="stylePresetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="rp-style-modal-form" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="_method" id="rp-style-modal-method" value="">
                <input type="hidden" name="_style_id" id="rp-style-modal-id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="stylePresetModalLabel">
                        <i class="fa-solid fa-palette"></i><span id="rp-style-modal-title"><?= e(t('reports.designer.style_modal_title')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div id="rp-style-modal-alert" class="alert alert-danger d-none" role="alert"></div>

                    <div class="mb-3">
                        <label for="rp-style-name" class="form-label"><?= e(t('reports.designer.style_name')) ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rp-style-name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="rp-style-description" class="form-label"><?= e(t('reports.designer.style_description')) ?></label>
                        <textarea class="form-control" id="rp-style-description" name="description" rows="2"></textarea>
                    </div>

                    <h6 class="mt-3 mb-2"><i class="fa-solid fa-droplet me-1"></i><?= e(t('reports.designer.style_colors')) ?></h6>
                    <div class="row g-2">
                        <?php
                        $rpColorFields = [
                            'primary_color'     => [t('reports.designer.color_primary'),     '#3b82f6'],
                            'secondary_color'   => [t('reports.designer.color_secondary'),   '#64748b'],
                            'accent_color'      => [t('reports.designer.color_accent'),      '#f97316'],
                            'header_bg_color'   => [t('reports.designer.color_header_bg'),   '#1e293b'],
                            'header_text_color' => [t('reports.designer.color_header_text'), '#ffffff'],
                            'zebra_color'       => [t('reports.designer.color_zebra'),       '#f8fafc'],
                        ];
                        foreach ($rpColorFields as $fld => [$lbl, $def]): ?>
                        <div class="col-md-4">
                            <label for="rp-style-<?= e($fld) ?>" class="form-label small"><?= e($lbl) ?></label>
                            <input type="color" class="form-control form-control-color w-100"
                                   id="rp-style-<?= e($fld) ?>" name="<?= e($fld) ?>" value="<?= e($def) ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <h6 class="mt-3 mb-2"><i class="fa-solid fa-font me-1"></i><?= e(t('reports.designer.style_typography')) ?></h6>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label for="rp-style-font-family" class="form-label small"><?= e(t('reports.designer.style_font')) ?></label>
                            <select class="form-select form-select-sm" id="rp-style-font-family" name="font_family">
                                <?php foreach ($rpStyleFontOptions as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= $val === 'dejavusans' ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="rp-style-font-size" class="form-label small"><?= e(t('reports.designer.style_font_size')) ?></label>
                            <input type="number" class="form-control form-control-sm"
                                   id="rp-style-font-size" name="font_size_base" value="9" min="6" max="18">
                        </div>
                    </div>

                    <h6 class="mt-3 mb-2"><i class="fa-solid fa-image me-1"></i><?= e(t('reports.designer.style_logos')) ?></h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small"><?= e(t('reports.designer.style_logo_primary')) ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       id="rp-designer-logo-file-name"
                                       class="form-control"
                                       value="<?= e(t('reports.designer.style_no_file')) ?>"
                                       readonly
                                       aria-label="<?= e(t('reports.designer.style_logo_primary')) ?>">
                                <label class="btn btn-outline-secondary text-nowrap" for="rp-designer-logo-file">
                                    <i class="fa-solid fa-paperclip me-1"></i><?= e(t('reports.designer.style_choose_file')) ?>
                                </label>
                                <input type="file"
                                       class="visually-hidden"
                                       id="rp-designer-logo-file"
                                       name="logo"
                                       accept="image/png,image/jpeg,image/svg+xml"
                                       data-app-file-target="rp-designer-logo-file-name"
                                       data-app-file-placeholder="<?= e(t('reports.designer.style_no_file')) ?>">
                            </div>
                            <div class="form-text"><?= e(t('reports.designer.style_logo_hint')) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small"><?= e(t('reports.designer.style_logo_secondary')) ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       id="rp-designer-logo-secondary-file-name"
                                       class="form-control"
                                       value="<?= e(t('reports.designer.style_no_file')) ?>"
                                       readonly
                                       aria-label="<?= e(t('reports.designer.style_logo_secondary')) ?>">
                                <label class="btn btn-outline-secondary text-nowrap" for="rp-designer-logo-secondary-file">
                                    <i class="fa-solid fa-paperclip me-1"></i><?= e(t('reports.designer.style_choose_file')) ?>
                                </label>
                                <input type="file"
                                       class="visually-hidden"
                                       id="rp-designer-logo-secondary-file"
                                       name="logo_secondary"
                                       accept="image/png,image/jpeg,image/svg+xml"
                                       data-app-file-target="rp-designer-logo-secondary-file-name"
                                       data-app-file-placeholder="<?= e(t('reports.designer.style_no_file')) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="is_default" id="rp-style-is-default" value="1">
                        <label class="form-check-label" for="rp-style-is-default"><?= e(t('reports.designer.style_is_default')) ?></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('reports.designer.cancel_short')) ?></button>
                    <button type="submit" class="btn btn-primary" id="rp-style-modal-submit">
                        <i class="fa-solid fa-check me-1"></i><?= e(t('reports.designer.save_short')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">
    window.__rpStyleRoutes = {
        store:  <?= json_encode(route('reports.styles.store')) ?>,
        create: <?= json_encode(route('reports.styles.create')) ?>,
        edit:   <?= json_encode(str_replace('__ID__', '0', route('reports.styles.edit', ['id' => '__ID__']))) ?>,
        update: <?= json_encode(str_replace('__ID__', '0', route('reports.styles.update', ['id' => '__ID__']))) ?>,
    };
</script>
<?php endif; ?>

<?php $view->end(); ?>
