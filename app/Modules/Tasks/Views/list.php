<?php
/**
 * Attività — Vista Lista
 *
 * Variabili: $items, $total, $pages, $page, $filters, $stats,
 *            $statuses, $priorities
 */
$view->layout('main');
$view->pushStyle('css/tasks.css');
$view->pushScript('js/tasks.js');

use App\Modules\Auth\Helpers\AvatarHelper;

$attProfileName = $user['name'] ?? t('common.user.fallback_name');
$attAvatarUrl   = AvatarHelper::url($_SESSION['user_avatar'] ?? null);
$attInitials    = AvatarHelper::initials($attProfileName);

$attHeroStats = [
    ['value' => (int) ($stats['total'] ?? 0),   'label' => t('tasks.stats.total'),  'icon' => 'fa-solid fa-clipboard-list', 'color' => 'primary'],
    ['value' => (int) ($stats['active'] ?? 0),  'label' => t('tasks.stats.active'), 'icon' => 'fa-solid fa-spinner',        'color' => 'info'],
    ['value' => (int) ($stats['done'] ?? 0),    'label' => t('tasks.stats.done'),   'icon' => 'fa-solid fa-check',          'color' => 'success'],
];
if (($stats['overdue'] ?? 0) > 0) {
    $attHeroStats[] = ['value' => (int) $stats['overdue'], 'label' => t('tasks.stats.overdue'), 'icon' => 'fa-solid fa-exclamation-triangle', 'color' => 'danger'];
}

$activeStatus   = $filters['status']   ?? '';
$activePriority = $filters['priority'] ?? '';
$activeScope    = $filters['scope']    ?? '';
$hasFilters     = !empty($filters['q']) || $activeStatus !== '' || $activePriority !== '' || $activeScope !== '';
$scopeBaseParams = array_diff_key($filters, ['scope' => '', 'page' => '']);

$scopeLink = static function (string $scope, string $label, string $icon, string $activeScope, array $params): string {
    $query = $params;
    if ($scope !== '') {
        $query['scope'] = $scope;
    }
    $qs = http_build_query($query);

    return '<a href="' . e(route('tasks.list')) . ($qs !== '' ? '?' . e($qs) : '') . '"'
        . ' class="att-status-pill ' . ($activeScope === $scope ? 'active' : '') . '">'
        . '<i class="fa-solid ' . e($icon) . '"></i>'
        . '<span>' . e($label) . '</span>'
        . '</a>';
};
?>

<?php $view->start('content'); ?>

<div class="container-fluid">

<?php
$listHeroButtons = '<a href="' . e(route('tasks.index')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('tasks.tooltip.kanban_view')) . '">' .
                   '<i class="fa-solid fa-columns me-1"></i>' . e(t('tasks.actions.kanban')) . '</a>';
if (has_permission('tasks.create')) {
    $listHeroButtons .= '<a href="' . e(route('tasks.create')) . '" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="' . e(t('tasks.tooltip.new')) . '">' .
                        '<i class="fa-solid fa-plus me-1"></i>' . e(t('tasks.actions.new')) . '</a>';
}
$view->include('partials/pf-hero-user', [
    'userName'     => t('tasks.list_title'),
    'userSubtitle' => $attProfileName . ' - ' . t('tasks.list_subtitle'),
    'userAvatar'   => $attAvatarUrl ?? null,
    'userInitials' => $attInitials,
    'userStats'    => $attHeroStats,
    'userButtons'  => $listHeroButtons,
]);
?>

    <!-- Panoramica stati + Filtri in card unificata -->
    <div class="card shadow-sm mb-3 att-list-card">
        <div class="card-body">
            <div class="app-section-grid">

                <section class="app-section">
                    <header class="app-section-subhead">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span><?= e(t('tasks.list.filter_status')) ?></span>
                        <?php if ($hasFilters): ?>
                            <small class="app-section-subhead-hint ms-auto">
                                <?= e(t('tasks.list.results', ['count' => (int) $total])) ?>
                            </small>
                        <?php endif; ?>
                    </header>
                    <div class="att-filter-bar">
                        <?php $qsBase = array_diff_key($filters, ['status' => '', 'page' => '']); ?>
                        <?php $qsAll  = http_build_query($qsBase); ?>
                        <a href="<?= e(route('tasks.list')) ?><?= $qsAll ? '?' . e($qsAll) : '' ?>"
                           class="att-status-pill <?= $activeStatus === '' ? 'active' : '' ?>">
                            <i class="fa-solid fa-layer-group"></i>
                            <span><?= e(t('tasks.list.all')) ?></span>
                            <span class="att-pill-count"><?= (int) ($stats['total'] ?? 0) ?></span>
                        </a>
                        <?php foreach ($statuses as $key => $s):
                            $qsParams = array_merge($qsBase, ['status' => $key]);
                            $qsLink   = http_build_query($qsParams);
                            $count    = (int) ($stats['by_status'][$key] ?? 0);
                        ?>
                        <a href="<?= e(route('tasks.list')) ?>?<?= e($qsLink) ?>"
                           class="att-status-pill att-status-pill-<?= e($s['color']) ?> <?= $activeStatus === $key ? 'active' : '' ?>">
                            <i class="fa-solid <?= e($s['icon']) ?>"></i>
                            <span><?= e($s['label']) ?></span>
                            <span class="att-pill-count"><?= $count ?></span>
                        </a>
                        <?php endforeach; ?>
                        <?php if (($stats['overdue'] ?? 0) > 0): ?>
                            <span class="att-filter-sep"></span>
                            <span class="att-status-pill att-status-pill-danger att-status-pill-static" data-bs-toggle="tooltip" title="<?= e(t('tasks.list.overdue_tooltip')) ?>">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <span><?= e(t('tasks.stats.overdue')) ?></span>
                                <span class="att-pill-count"><?= (int) $stats['overdue'] ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="app-section">
                    <header class="app-section-subhead">
                        <i class="fa-solid fa-filter"></i>
                        <span><?= e(t('common.chrome.search_filter')) ?></span>
                    </header>
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-6 col-lg-7">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text app-input-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="text" class="form-control"
                                       name="q" value="<?= e($filters['q'] ?? '') ?>"
                                       placeholder="<?= e(t('tasks.list.search_placeholder')) ?>"
                                       autocomplete="off"
                                       hx-get="<?= e(route('tasks.list')) ?>"
                                       hx-trigger="keyup changed delay:400ms, search"
                                       hx-target="#att-list-table"
                                       hx-push-url="true"
                                        hx-include="[name='status'],[name='priority'],[name='scope']">
                                <?php if (!empty($filters['q'])): ?>
                                <?php $qsClear = http_build_query(array_diff_key($filters, ['q' => '', 'page' => ''])); ?>
                                <a href="<?= e(route('tasks.list')) ?><?= $qsClear ? '?' . e($qsClear) : '' ?>"
                                   class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="<?= e(t('tasks.list.clear_search')) ?>">
                                    <i class="fa-solid fa-xmark"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-8 col-md-4 col-lg-3">
                            <select name="priority" class="form-select form-select-sm"
                                    hx-get="<?= e(route('tasks.list')) ?>"
                                    hx-trigger="change"
                                    hx-target="#att-list-table"
                                    hx-push-url="true"
                                    hx-include="[name='q'],[name='status'],[name='scope']">
                                <option value=""><?= e(t('tasks.form.all_priorities')) ?></option>
                                <?php foreach ($priorities as $key => $p): ?>
                                <option value="<?= e($key) ?>" <?= $activePriority === $key ? 'selected' : '' ?>>
                                    <?= e($p['label']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4 col-md-2 col-lg-2">
                            <a href="<?= e(route('tasks.list')) ?>"
                               class="btn btn-sm btn-outline-secondary w-100 <?= !$hasFilters ? 'disabled' : '' ?>"
                               data-bs-toggle="tooltip" title="<?= e(t('tasks.list.reset_tooltip')) ?>">
                                <i class="fa-solid fa-rotate-left me-1"></i><?= e(t('common.action.reset')) ?>
                            </a>
                        </div>

                        <!-- Hidden sync for status (controlled via pills above) -->
                        <input type="hidden" name="status" value="<?= e($activeStatus) ?>">
                        <input type="hidden" name="scope" value="<?= e($activeScope) ?>">
                    </div>

                    <div class="att-filter-bar mt-3">
                        <?= $scopeLink('', t('tasks.list.scope_all'), 'compass', $activeScope, $scopeBaseParams) ?>
                        <?= $scopeLink('today', t('tasks.list.scope_today'), 'calendar-day', $activeScope, $scopeBaseParams) ?>
                        <?= $scopeLink('week', t('tasks.list.scope_week'), 'calendar-week', $activeScope, $scopeBaseParams) ?>
                        <?= $scopeLink('linked', t('tasks.list.scope_linked'), 'calendar-check', $activeScope, $scopeBaseParams) ?>
                        <?= $scopeLink('overdue', t('tasks.list.scope_overdue'), 'triangle-exclamation', $activeScope, $scopeBaseParams) ?>
                    </div>
                </section>

            </div>
        </div>
    </div>

    <!-- Tabella -->
    <div id="att-list-table">
        <?php $view->include('Tasks/Views/partials/table', get_defined_vars()); ?>
    </div>

</div>

<?php $view->end(); ?>
