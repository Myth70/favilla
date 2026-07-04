<?php
$view->layout('main');
$view->pushScript('js/apexcharts.min.js');
$view->pushScript('js/admin.js');
$view->pushStyle('css/admin.css');
?>
<?php $view->start('content'); ?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-gauge-high',
    'adminTitle'    => t('admin.dashboard.title'),
    'adminSubtitle' => t('admin.dashboard.subtitle'),
]); ?>

<!-- Quick actions -->
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (has_permission('admin.users.view')): ?>
    <a href="<?= e(route('admin.users.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_users_tip')) ?>">
        <i class="fa-solid fa-users fa-fw"></i> <?= e(t('admin.dashboard.q_users')) ?>
    </a>
    <?php endif; ?>
    <?php if (has_permission('admin.users.create')): ?>
    <a href="<?= e(route('admin.users.create')) ?>" class="adm-quick-btn adm-quick-btn--accent" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_new_user_tip')) ?>">
        <i class="fa-solid fa-user-plus fa-fw"></i> <?= e(t('admin.dashboard.q_new_user')) ?>
    </a>
    <?php endif; ?>
    <?php if (has_permission('admin.roles.manage')): ?>
    <a href="<?= e(route('admin.roles.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_roles_tip')) ?>">
        <i class="fa-solid fa-user-tag fa-fw"></i> <?= e(t('admin.dashboard.q_roles')) ?>
    </a>
    <?php endif; ?>
    <?php if (has_permission('admin.logs.view')): ?>
    <a href="<?= e(route('admin.logs.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_audit_tip')) ?>">
        <i class="fa-solid fa-shield-halved fa-fw"></i> <?= e(t('admin.dashboard.q_audit')) ?>
    </a>
    <?php endif; ?>
    <?php if (has_permission('admin.modules.manage')): ?>
    <a href="<?= e(route('admin.modules.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_modules_tip')) ?>">
        <i class="fa-solid fa-cubes fa-fw"></i> <?= e(t('admin.dashboard.q_modules')) ?>
    </a>
    <?php endif; ?>
    <?php if (has_permission('admin.settings.manage')): ?>
    <a href="<?= e(route('admin.settings.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_settings_tip')) ?>">
        <i class="fa-solid fa-gear fa-fw"></i> <?= e(t('admin.dashboard.q_settings')) ?>
    </a>
    <?php endif; ?>
    <?php if (isModuleEnabled('Backup') && has_permission('backup.manage')): ?>
    <a href="<?= e(route('backup.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_backup_tip')) ?>">
        <i class="fa-solid fa-hard-drive fa-fw"></i> <?= e(t('admin.dashboard.q_backup')) ?>
    </a>
    <?php endif; ?>
    <a href="<?= e(route('admin.index')) ?>" class="adm-quick-btn" data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.q_index_tip')) ?>">
        <i class="fa-solid fa-table-cells-large fa-fw"></i> <?= e(t('admin.dashboard.q_index')) ?>
    </a>
</div>

<!-- KPI Stats (HTMX auto-refresh every 60s) -->
<div id="adm-stats"
     hx-get="<?= e(route('admin.dashboard.stats')) ?>"
     hx-trigger="every 60s"
     hx-swap="innerHTML">
    <?php $view->include('Admin/Views/dashboard/partials/stats-widget', compact('stats')); ?>
</div>

<!-- Charts row: 3 colonne -->
<div class="row g-3 mt-1">

    <!-- Sicurezza login — dual area chart (14 giorni) -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <div class="fw-semibold lh-1"><?= t('admin.dashboard.security_title') ?></div>
                        <div class="small text-muted mt-1"><?= e(t('admin.dashboard.security_sub')) ?></div>
                    </div>
                </div>
                <span class="adm-live-badge">
                    <span class="adm-live-dot"></span> <?= e(t('admin.dashboard.live')) ?>
                </span>
            </div>
            <div class="card-body pb-2">
                <div id="adm-security-chart"></div>
            </div>
        </div>
    </div>

    <!-- Distribuzione azioni audit — donut -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2 py-3 border-bottom">
                <span class="app-card-icon"><i class="fa-solid fa-chart-pie"></i></span>
                <div>
                    <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.audit_title')) ?></div>
                    <div class="small text-muted mt-1"><?= e(t('admin.dashboard.audit_sub')) ?></div>
                </div>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (!empty($auditDistrib['values'])): ?>
                    <div id="adm-audit-donut" class="w-100"></div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa-solid fa-chart-pie fa-2x mb-2 d-block opacity-25"></i>
                        <?= e(t('admin.dashboard.no_data')) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top utenti attivi — horizontal bar -->
    <div class="col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2 py-3 border-bottom">
                <span class="app-card-icon"><i class="fa-solid fa-ranking-star"></i></span>
                <div>
                    <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.top_users')) ?></div>
                    <div class="small text-muted mt-1"><?= e(t('admin.dashboard.top_users_sub')) ?></div>
                </div>
            </div>
            <div class="card-body pb-1 d-flex align-items-center">
                <?php if (!empty($topUsers)): ?>
                    <div id="adm-top-users-chart" class="w-100"></div>
                <?php else: ?>
                    <div class="text-center text-muted py-4 w-100">
                        <i class="fa-solid fa-ranking-star fa-2x mb-2 d-block opacity-25"></i>
                        <?= e(t('admin.dashboard.no_data')) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php if (has_permission('admin.security.view')): ?>
<!-- Riepilogo incidenti di sicurezza -->
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-shield-exclamation text-warning"></i></span>
                    <div>
                        <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.incidents_title')) ?></div>
                        <div class="small text-muted mt-1"><?= t('admin.dashboard.incidents_sub') ?></div>
                    </div>
                </div>
                <a href="<?= e(route('admin.security.incidents')) ?>"
                   class="btn btn-sm btn-outline-secondary"
                   data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.incidents_all_tip')) ?>">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e(t('admin.dashboard.detail')) ?>
                </a>
            </div>
            <div class="card-body py-3"
                 id="adm-incidents-summary"
                 hx-get="<?= e(route('admin.security.incidents.summary')) ?>"
                 hx-trigger="load, every 120s"
                 hx-swap="innerHTML">
                <div class="text-center text-muted py-2">
                    <span class="spinner-border spinner-border-sm me-2"></span><?= e(t('admin.dashboard.loading')) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent logs + System info row -->
<div class="row g-3 mt-1">
    <!-- Activity timeline -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                    <div>
                        <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.recent_title')) ?></div>
                        <div class="small text-muted mt-1"><?= e(t('admin.dashboard.recent_sub')) ?></div>
                    </div>
                </div>
                <a href="<?= e(route('admin.logs.index')) ?>"
                   class="btn btn-sm btn-outline-secondary"
                   data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.recent_all_tip')) ?>">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i><?= e(t('admin.dashboard.all')) ?>
                </a>
            </div>
            <div id="adm-recent-logs"
                 hx-get="<?= e(route('admin.dashboard.recent-logs')) ?>"
                 hx-trigger="every 60s"
                 hx-swap="innerHTML">
                <?php $view->include('Admin/Views/dashboard/partials/recent-logs', compact('unifiedTimeline')); ?>
            </div>
        </div>
    </div>

    <!-- Online ora + System info + Modules stacked -->
    <div class="col-lg-5 d-flex flex-column gap-3">

        <!-- Online ora (HTMX auto-refresh 60s) -->
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-circle-dot text-success"></i></span>
                    <div>
                        <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.online_title')) ?></div>
                        <div class="small text-muted mt-1"><?= e(tc('admin.dashboard.online_count', count($onlineSessions))) ?></div>
                    </div>
                </div>
                <span class="adm-live-badge">
                    <span class="adm-live-dot"></span> <?= e(t('admin.dashboard.live')) ?>
                </span>
            </div>
            <div class="card-body p-2"
                 id="adm-online-widget"
                 hx-get="<?= e(route('admin.dashboard.online')) ?>"
                 hx-trigger="every 60s"
                 hx-swap="innerHTML">
                <?php $view->include('Admin/Views/dashboard/partials/online-widget', compact('onlineSessions')); ?>
            </div>
        </div>

        <!-- System health -->
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex align-items-center gap-2 py-3 border-bottom">
                <span class="app-card-icon"><i class="fa-solid fa-server"></i></span>
                <div>
                    <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.infra')) ?></div>
                    <div class="small text-muted mt-1"><?= e(t('admin.dashboard.infra_sub')) ?></div>
                </div>
            </div>
            <?php
            $envBadge   = $systemInfo['environment'] === 'production'
                ? ['success', 'fa-circle-check', ucfirst($systemInfo['environment'])]
                : ['warning', 'fa-triangle-exclamation', ucfirst($systemInfo['environment'])];
            $debugBadge = $systemInfo['debug_mode'] === 'Attivo'
                ? ['warning', 'fa-bug', t('admin.dashboard.debug_active')]
                : ['success', 'fa-circle-check', t('admin.dashboard.debug_inactive')];
            $sysRows = [
                ['fa-code',           t('admin.dashboard.sys_php'),      $systemInfo['php_version'],  null],
                ['fa-database',       t('admin.dashboard.sys_database'), $systemInfo['db_version'],   null],
                ['fa-globe',          t('admin.dashboard.sys_environment'), $envBadge[2],             $envBadge[0]],
                ['fa-bug',            t('admin.dashboard.sys_debug'),    $debugBadge[2],              $debugBadge[0]],
                ['fa-clock',          t('admin.dashboard.sys_timezone'), $systemInfo['timezone'],     null],
            ];
            ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($sysRows as [$icon, $label, $value, $badge]): ?>
                <li class="list-group-item d-flex align-items-center justify-content-between px-3 py-2">
                    <span class="d-flex align-items-center gap-2 text-muted small">
                        <i class="fa-solid <?= e($icon) ?> fa-fw adm-sys-icon"></i>
                        <?= e($label) ?>
                    </span>
                    <?php if ($badge): ?>
                        <span class="badge bg-<?= e($badge) ?>"><?= e($value) ?></span>
                    <?php else: ?>
                        <code class="small text-body-secondary"><?= e($value) ?></code>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Module status -->
        <div class="card border-0 shadow-sm flex-fill">
            <div class="card-header d-flex align-items-center justify-content-between py-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="app-card-icon"><i class="fa-solid fa-cubes"></i></span>
                    <div>
                        <div class="fw-semibold lh-1"><?= e(t('admin.dashboard.modules_title')) ?></div>
                        <div class="small text-muted mt-1"><?= e(t('admin.dashboard.modules_sub', ['count' => (int) $stats['modules_count']])) ?></div>
                    </div>
                </div>
                <?php if (has_permission('admin.modules.manage')): ?>
                <a href="<?= e(route('admin.modules.index')) ?>"
                   class="btn btn-sm btn-outline-secondary"
                   data-bs-toggle="tooltip" title="<?= e(t('admin.dashboard.modules_manage_tip')) ?>">
                    <?= e(t('admin.dashboard.manage')) ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body"
                 id="adm-modules"
                 hx-get="<?= e(route('admin.dashboard.modules')) ?>"
                 hx-trigger="every 120s"
                 hx-swap="innerHTML">
                <?php $view->include('Admin/Views/dashboard/partials/modules-widget', compact('moduleStatus')); ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts init -->
<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';

    var secData      = <?= json_encode($loginSecurity,  JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var donutData    = <?= json_encode($auditDistrib,   JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var topUsersData = <?= json_encode($topUsers,       JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function theme() {
        var dark   = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#3b82f6';
        return {
            dark:   dark,
            fg:     dark ? '#94a3b8' : '#64748b',
            grid:   dark ? '#1e293b' : '#f1f5f9',
            accent: accent,
        };
    }

    /* ── Grafico sicurezza login (dual-area) ───────────────── */
    function renderSecurityChart() {
        var el = document.getElementById('adm-security-chart');
        if (!el || typeof ApexCharts === 'undefined') return;
        var t = theme();
        var opts = {
            series: [
                { name: <?= json_encode(t('admin.dashboard.js_security_ok')) ?>,   data: secData.ok_values },
                { name: <?= json_encode(t('admin.dashboard.js_security_fail')) ?>,  data: secData.fail_values },
            ],
            chart: {
                type: 'area', height: 255,
                fontFamily: 'inherit', toolbar: { show: false }, background: 'transparent',
            },
            colors:     [t.accent, '#ef4444'],
            fill:       { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 100] } },
            stroke:     { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
            markers:    { size: 3, strokeColors: 'var(--bs-body-bg)', strokeWidth: 2, hover: { size: 5 } },
            xaxis: {
                categories: secData.labels,
                labels:     { style: { colors: t.fg, fontSize: '10px' } },
                axisBorder: { show: false },
                axisTicks:  { show: false },
            },
            yaxis: {
                min: 0,
                labels: { style: { colors: t.fg, fontSize: '11px' }, formatter: function (v) { return Math.round(v); } },
            },
            grid:    { borderColor: t.grid, strokeDashArray: 4, padding: { left: 4, right: 8 } },
            legend:  { position: 'top', fontSize: '12px', labels: { colors: t.fg } },
            tooltip: { theme: t.dark ? 'dark' : 'light' },
        };
        if (window._adm_sc) { window._adm_sc.destroy(); }
        window._adm_sc = new ApexCharts(el, opts);
        window._adm_sc.render();
    }

    /* ── Donut distribuzione audit ─────────────────────────── */
    function renderDonutChart() {
        var el = document.getElementById('adm-audit-donut');
        if (!el || typeof ApexCharts === 'undefined' || !donutData.values || !donutData.values.length) return;
        var t = theme();
        var palette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#ec4899'];
        var opts = {
            series:  donutData.values,
            labels:  donutData.labels,
            chart:   { type: 'donut', height: 210, fontFamily: 'inherit', background: 'transparent' },
            colors:  palette,
            legend:  { position: 'bottom', fontSize: '10px', labels: { colors: t.fg } },
            plotOptions: { pie: { donut: { size: '58%', labels: { show: true, total: { show: true, label: <?= json_encode(t('admin.dashboard.js_donut_total')) ?>, color: t.fg, fontSize: '12px', fontWeight: 600 } } } } },
            dataLabels: { enabled: false },
            stroke:  { width: 0 },
            tooltip: { theme: t.dark ? 'dark' : 'light' },
        };
        if (window._adm_dc) { window._adm_dc.destroy(); }
        window._adm_dc = new ApexCharts(el, opts);
        window._adm_dc.render();
    }

    /* ── Top utenti bar orizzontale ────────────────────────── */
    function renderTopUsersChart() {
        var el = document.getElementById('adm-top-users-chart');
        if (!el || typeof ApexCharts === 'undefined' || !topUsersData || !topUsersData.length) return;
        var t = theme();
        var names  = topUsersData.map(function (u) { return u.name.split(' ')[0]; });
        var counts = topUsersData.map(function (u) { return parseInt(u.action_count, 10); });
        var opts = {
            series: [{ name: <?= json_encode(t('admin.dashboard.js_bar_series')) ?>, data: counts }],
            chart:  {
                type: 'bar', height: 195,
                fontFamily: 'inherit', toolbar: { show: false }, background: 'transparent',
            },
            plotOptions: {
                bar: { horizontal: true, borderRadius: 4, barHeight: '55%',
                       dataLabels: { position: 'right' } },
            },
            dataLabels: {
                enabled: true,
                style:   { fontSize: '10px', colors: [t.fg] },
                offsetX: 4,
            },
            colors: [t.accent],
            xaxis:  { categories: names, labels: { style: { colors: t.fg, fontSize: '10px' } } },
            yaxis:  { labels: { style: { colors: t.fg, fontSize: '11px' }, maxWidth: 80 } },
            grid:   { borderColor: t.grid, strokeDashArray: 3,
                      xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: t.dark ? 'dark' : 'light', y: { formatter: function (v) { return <?= json_encode(t('admin.dashboard.js_bar_tooltip', ['count' => '%V'])) ?>.replace('%V', v); } } },
        };
        if (window._adm_tuc) { window._adm_tuc.destroy(); }
        window._adm_tuc = new ApexCharts(el, opts);
        window._adm_tuc.render();
    }

    function renderAll() {
        renderSecurityChart();
        renderDonutChart();
        renderTopUsersChart();
    }

    if (typeof ApexCharts !== 'undefined') {
        renderAll();
    } else {
        var _t = setInterval(function () {
            if (typeof ApexCharts !== 'undefined') { clearInterval(_t); renderAll(); }
        }, 100);
    }

    document.addEventListener('themeChanged', renderAll);
})();
</script>

<?php $view->end(); ?>
