<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->start('content'); ?>

<?php
$adminButtons = '<form method="POST" action="' . e(route('helponline.admin.sync')) . '" class="d-inline">'
    . csrf_field()
    . '<button type="submit" class="btn btn-sm btn-primary" data-app-confirm="' . e(t('helponline.admin.reindex_confirm')) . '">'
    . '<i class="fa-solid fa-rotate me-1"></i>' . e(t('helponline.admin.reindex')) . '</button></form>';

$tabs = [
    'overview' => ['label' => t('helponline.admin.tab_overview'), 'route' => 'helponline.admin.index'],
    'modules' => ['label' => t('helponline.admin.tab_modules'), 'route' => 'helponline.admin.modules'],
    'entries' => ['label' => t('helponline.admin.tab_entries'), 'route' => 'helponline.admin.entries'],
    'queries' => ['label' => t('helponline.admin.tab_queries'), 'route' => 'helponline.admin.queries'],
];
?>

<div class="container-fluid app-page-wide">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon' => 'fa-solid fa-circle-question',
        'adminTitle' => 'Help Online',
        'adminSubtitle' => t('helponline.admin.subtitle'),
        'adminButtons' => $adminButtons,
    ]); ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap gap-2">
            <?php foreach ($tabs as $tabId => $tab): ?>
                <?php
                $isActive = ($activeTab ?? 'overview') === $tabId
                    || ($tabId === 'modules' && ($activeTab ?? '') === 'module_edit')
                    || ($tabId === 'entries' && ($activeTab ?? '') === 'entry_edit');
                ?>
                <a href="<?= e(route($tab['route'])) ?>" class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= e($tab['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!($schemaReady ?? false)): ?>
        <div class="alert alert-warning shadow-sm">
            <?= t('helponline.admin.schema_warning') ?>
        </div>
    <?php endif; ?>

    <?php if (($activeTab ?? 'overview') === 'overview'): ?>
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= e(t('helponline.admin.stat_qa')) ?></div>
                        <div class="fs-3 fw-semibold"><?= (int) ($summary['entries'] ?? 0) ?></div>
                        <div class="small text-muted"><?= e(t('helponline.admin.stat_qa_sub')) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= e(t('helponline.admin.stat_aliases')) ?></div>
                        <div class="fs-3 fw-semibold"><?= (int) ($summary['aliases'] ?? 0) ?></div>
                        <div class="small text-muted"><?= e(t('helponline.admin.stat_aliases_sub')) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= e(t('helponline.admin.stat_modules')) ?></div>
                        <div class="fs-3 fw-semibold"><?= (int) ($summary['modules'] ?? 0) ?></div>
                        <div class="small text-muted"><?= e(t('helponline.admin.stat_modules_sub')) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?= e(t('helponline.admin.stat_queries')) ?></div>
                        <div class="fs-3 fw-semibold"><?= (int) ($summary['queries'] ?? 0) ?></div>
                        <div class="small text-muted"><?= e(t('helponline.admin.stat_queries_sub')) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($stats) && (int) ($stats['total'] ?? 0) > 0): ?>
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small"><?= e(t('helponline.admin.match_rate')) ?></div>
                            <div class="d-flex align-items-baseline gap-2">
                                <div class="fs-3 fw-semibold"><?= (int) ($stats['match_rate'] ?? 0) ?>%</div>
                                <div class="small text-muted"><?= (int) ($stats['matched'] ?? 0) ?>/<?= (int) ($stats['total'] ?? 0) ?></div>
                            </div>
                            <div class="progress mt-2" style="height:.4rem;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= (int) ($stats['match_rate'] ?? 0) ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small"><?= e(t('helponline.admin.helpful_rate')) ?></div>
                            <div class="d-flex align-items-baseline gap-2">
                                <div class="fs-3 fw-semibold"><?= (int) ($stats['helpful_rate'] ?? 0) ?>%</div>
                                <div class="small text-muted"><?= (int) ($stats['helpful'] ?? 0) ?> / <?= (int) (($stats['helpful'] ?? 0) + ($stats['unhelpful'] ?? 0)) ?></div>
                            </div>
                            <div class="progress mt-2" style="height:.4rem;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= (int) ($stats['helpful_rate'] ?? 0) ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small"><?= e(t('helponline.admin.unmatched')) ?></div>
                            <div class="fs-3 fw-semibold text-warning"><?= (int) ($stats['unmatched'] ?? 0) ?></div>
                            <div class="small text-muted"><?= e(t('helponline.admin.unmatched_sub')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.help_modules')) ?></div>
                    <div class="card-body p-0">
                        <?php $view->include('HelpOnline/Views/admin/partials/modules_table', ['modules' => $modules ?? []]); ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.recent_queries')) ?></div>
                    <div class="card-body p-0">
                        <?php $view->include('HelpOnline/Views/admin/partials/queries_table', ['queries' => $queries ?? []]); ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($topUnmatched)): ?>
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                            <span><?= e(t('helponline.admin.recurring_unmatched')) ?></span>
                            <span class="small text-muted"><?= e(t('helponline.admin.recurring_hint')) ?></span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th><?= e(t('helponline.admin.col_question')) ?></th>
                                    <th><?= e(t('helponline.admin.col_context')) ?></th>
                                    <th class="text-end"><?= e(t('helponline.admin.col_occurrences')) ?></th>
                                    <th><?= e(t('helponline.admin.col_last_seen')) ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($topUnmatched as $row): ?>
                                    <tr>
                                        <td><div class="fw-semibold"><?= e((string) ($row['query_text'] ?? '')) ?></div></td>
                                        <td><?= e((string) ($row['context_module'] ?? t('helponline.admin.context_general'))) ?></td>
                                        <td class="text-end"><span class="badge text-bg-warning"><?= (int) ($row['occurrences'] ?? 0) ?></span></td>
                                        <td><?= e((string) ($row['last_seen'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.qa_records')) ?></div>
                    <div class="card-body p-0">
                        <?php $view->include('HelpOnline/Views/admin/partials/entries_table', ['entries' => $entries ?? []]); ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                        <span><?= e(t('helponline.admin.top_aliases')) ?></span>
                        <span class="small text-muted"><?= e(t('helponline.admin.top_aliases_sub')) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr><th><?= e(t('helponline.admin.col_alias')) ?></th><th><?= e(t('helponline.admin.col_question')) ?></th><th class="text-end"><?= e(t('helponline.admin.col_weight')) ?></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach (($topAliases ?? []) as $alias): ?>
                                <tr>
                                    <td><code><?= e((string) ($alias['alias'] ?? '')) ?></code></td>
                                    <td>
                                        <a href="<?= e(route('helponline.admin.entries.edit', ['id' => (int) ($alias['entry_id'] ?? 0)])) ?>" class="text-decoration-none">
                                            <?= e((string) ($alias['question'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td class="text-end"><span class="badge text-bg-secondary"><?= (int) ($alias['weight_bonus'] ?? 0) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topAliases)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3 small"><?= t('helponline.admin.no_alias') ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (($activeTab ?? '') === 'modules'): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.new_module')) ?></div>
            <div class="card-body">
                <form method="POST" action="<?= e(route('helponline.admin.modules.create')) ?>" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.module_key')) ?></label>
                        <input type="text" name="module_key" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.module_name')) ?></label>
                        <input type="text" name="module_name" class="form-control" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.label')) ?></label>
                        <input type="text" name="label" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.audience')) ?></label>
                        <select name="audience_default" class="form-select"><option value="user"><?= e(t('helponline.admin.audience_user')) ?></option><option value="admin"><?= e(t('helponline.admin.audience_admin')) ?></option></select>
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.locale')) ?></label>
                        <input type="text" name="locale_default" class="form-control" value="it">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.order')) ?></label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="col-6 col-md-1">
                        <label class="form-label small text-muted"><?= e(t('common.label.status')) ?></label>
                        <select name="is_active" class="form-select"><option value="1"><?= e(t('helponline.admin.on')) ?></option><option value="0"><?= e(t('helponline.admin.off')) ?></option></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.description')) ?></label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.route')) ?></label>
                        <input type="text" name="route_name" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.permission')) ?></label>
                        <input type="text" name="permission_slug" class="form-control">
                    </div>
                    <div class="col-12 col-md-4 d-grid">
                        <button type="submit" class="btn btn-primary"><?= e(t('helponline.admin.create_module')) ?></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.help_modules')) ?></div>
            <div class="card-body p-0">
                <?php $view->include('HelpOnline/Views/admin/partials/modules_table', ['modules' => $modules ?? []]); ?>
            </div>
        </div>

    <?php elseif (($activeTab ?? '') === 'module_edit'): ?>
        <?php $view->include('HelpOnline/Views/admin/module_edit', get_defined_vars()); ?>

    <?php elseif (($activeTab ?? '') === 'entries'): ?>
        <?php $hasFilters = ($filters['search'] ?? '') !== '' || ($filters['module'] ?? '') !== '' || ($filters['audience'] ?? '') !== ''; ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="<?= e(route('helponline.admin.entries')) ?>" class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.entries_search')) ?></label>
                        <input type="search" name="search" class="form-control" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="<?= e(t('helponline.admin.entries_search_placeholder')) ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.col_module')) ?></label>
                        <select name="module" class="form-select">
                            <option value=""><?= e(t('helponline.admin.all_modules')) ?></option>
                            <?php foreach (($modules ?? []) as $module): ?>
                                <option value="<?= e((string) $module) ?>" <?= (string) ($filters['module'] ?? '') === (string) $module ? 'selected' : '' ?>><?= e((string) $module) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.audience')) ?></label>
                        <select name="audience" class="form-select">
                            <option value=""><?= e(t('helponline.admin.all')) ?></option>
                            <option value="user" <?= ($filters['audience'] ?? '') === 'user' ? 'selected' : '' ?>><?= e(t('helponline.admin.audience_user')) ?></option>
                            <option value="admin" <?= ($filters['audience'] ?? '') === 'admin' ? 'selected' : '' ?>><?= e(t('helponline.admin.audience_admin')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><?= e(t('helponline.admin.filter')) ?></button>
                        <?php if ($hasFilters): ?><a href="<?= e(route('helponline.admin.entries')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.new_entry')) ?></div>
                    <div class="card-body">
                        <form method="POST" action="<?= e(route('helponline.admin.entries.create')) ?>" class="row g-2 align-items-end">
                            <?= csrf_field() ?>
                            <div class="col-12 col-md-3">
                                <label class="form-label small text-muted"><?= e(t('helponline.admin.col_module')) ?></label>
                                <select name="module_id" class="form-select" required>
                                    <option value=""><?= e(t('helponline.admin.select_module')) ?></option>
                                    <?php foreach (($moduleOptions ?? []) as $module): ?>
                                        <option value="<?= (int) ($module['id'] ?? 0) ?>"><?= e((string) ($module['label'] ?? $module['module_name'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label small text-muted"><?= e(t('helponline.admin.question')) ?></label>
                                <input type="text" name="question" class="form-control" required>
                            </div>
                            <div class="col-6 col-md-1"><label class="form-label small text-muted"><?= e(t('helponline.admin.weight')) ?></label><input type="number" name="ranking_weight" class="form-control" value="0"></div>
                            <div class="col-6 col-md-1"><label class="form-label small text-muted"><?= e(t('helponline.admin.order')) ?></label><input type="number" name="sort_order" class="form-control" value="0"></div>
                            <div class="col-6 col-md-1"><label class="form-label small text-muted"><?= e(t('helponline.admin.audience')) ?></label><select name="audience" class="form-select"><option value="user"><?= e(t('helponline.admin.audience_user')) ?></option><option value="admin"><?= e(t('helponline.admin.audience_admin')) ?></option></select></div>
                            <div class="col-6 col-md-1"><label class="form-label small text-muted"><?= e(t('helponline.admin.locale')) ?></label><input type="text" name="locale" class="form-control" value="it"></div>
                            <div class="col-12">
                                <label class="form-label small text-muted"><?= e(t('helponline.admin.answer_markdown')) ?></label>
                                <textarea name="answer_markdown" class="form-control" rows="5" required></textarea>
                            </div>
                            <div class="col-12"><label class="form-label small text-muted"><?= e(t('helponline.admin.excerpt')) ?></label><input type="text" name="excerpt" class="form-control"></div>
                            <div class="col-12 col-md-4"><label class="form-label small text-muted"><?= e(t('helponline.admin.route')) ?></label><input type="text" name="route_name" class="form-control"></div>
                            <div class="col-12 col-md-4"><label class="form-label small text-muted"><?= e(t('helponline.admin.permission')) ?></label><input type="text" name="permission_slug" class="form-control"></div>
                            <div class="col-12 col-md-4"><label class="form-label small text-muted"><?= e(t('helponline.admin.aliases')) ?></label><input type="text" name="aliases" class="form-control" placeholder="<?= e(t('helponline.admin.aliases_placeholder')) ?>"></div>
                            <div class="col-12 d-grid"><button type="submit" class="btn btn-primary"><?= e(t('helponline.admin.create_entry')) ?></button></div>
                        </form>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-transparent fw-semibold"><?= e(t('helponline.admin.qa_records')) ?></div>
                    <div class="card-body p-0">
                        <?php $view->include('HelpOnline/Views/admin/partials/entries_table', ['entries' => $entries ?? []]); ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                        <span><?= e(t('helponline.admin.top_aliases')) ?></span>
                        <span class="small text-muted"><?= e(t('helponline.admin.top_aliases_sub')) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr><th><?= e(t('helponline.admin.col_alias')) ?></th><th><?= e(t('helponline.admin.col_question')) ?></th><th class="text-end"><?= e(t('helponline.admin.col_weight')) ?></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach (($topAliases ?? []) as $alias): ?>
                                <tr>
                                    <td><code><?= e((string) ($alias['alias'] ?? '')) ?></code></td>
                                    <td>
                                        <a href="<?= e(route('helponline.admin.entries.edit', ['id' => (int) ($alias['entry_id'] ?? 0)])) ?>" class="text-decoration-none">
                                            <?= e((string) ($alias['question'] ?? '')) ?>
                                        </a>
                                    </td>
                                    <td class="text-end"><span class="badge text-bg-secondary"><?= (int) ($alias['weight_bonus'] ?? 0) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topAliases)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3 small"><?= e(t('helponline.admin.no_alias_short')) ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-transparent small text-muted">
                        <?= t('helponline.admin.alias_weight_note') ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (($activeTab ?? '') === 'entry_edit'): ?>
        <?php $view->include('HelpOnline/Views/admin/entry_edit', get_defined_vars()); ?>

    <?php else: ?>
        <?php /* queries (default fallback) */ ?>
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form method="GET" action="<?= e(route('helponline.admin.queries')) ?>" class="row g-2 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.queries_search')) ?></label>
                        <input type="search" name="search" class="form-control" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="<?= e(t('helponline.admin.queries_search_placeholder')) ?>">
                    </div>
                    <div class="col-12 col-md-3 col-lg-3">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.context_module')) ?></label>
                        <select name="module" class="form-select">
                            <option value=""><?= e(t('helponline.admin.all_modules')) ?></option>
                            <?php foreach (($modules ?? []) as $module): ?>
                                <option value="<?= e((string) $module) ?>" <?= (string) ($filters['module'] ?? '') === (string) $module ? 'selected' : '' ?>><?= e((string) $module) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 col-lg-2">
                        <label class="form-label small text-muted"><?= e(t('helponline.admin.outcome')) ?></label>
                        <select name="status" class="form-select">
                            <option value=""><?= e(t('helponline.admin.all')) ?></option>
                            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= e(t('helponline.admin.outcome_pending')) ?></option>
                            <option value="positive" <?= ($filters['status'] ?? '') === 'positive' ? 'selected' : '' ?>><?= e(t('helponline.admin.outcome_positive')) ?></option>
                            <option value="negative" <?= ($filters['status'] ?? '') === 'negative' ? 'selected' : '' ?>><?= e(t('helponline.admin.outcome_negative')) ?></option>
                            <option value="unmatched" <?= ($filters['status'] ?? '') === 'unmatched' ? 'selected' : '' ?>><?= e(t('helponline.admin.outcome_unmatched')) ?></option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary"><?= e(t('helponline.admin.filter')) ?></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php $view->include('HelpOnline/Views/admin/partials/queries_table', ['queries' => $queries ?? []]); ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $view->end(); ?>
