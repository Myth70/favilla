<?php
/**
 * Admin sub-navigation contestuale.
 * Auto-rileva la sezione corrente dalla REQUEST_URI.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$sections = [
    'identita' => [
        'label'   => t('admin.subnav.section.identita'),
        'icon'    => 'fa-users',
        'pattern' => '#/(admin/users|admin/roles)#',
        'links'   => [
            ['label' => t('admin.subnav.link.users'),    'route' => 'admin.users.index',  'icon' => 'fa-users',     'perm' => 'admin.users.view'],
            ['label' => t('admin.subnav.link.roles'),    'route' => 'admin.roles.index',  'icon' => 'fa-user-tag',  'perm' => 'admin.roles.manage'],
            ['label' => t('admin.subnav.link.new_user'), 'route' => 'admin.users.create', 'icon' => 'fa-user-plus', 'perm' => 'admin.users.create'],
            ['label' => t('admin.subnav.link.new_role'), 'route' => 'admin.roles.create', 'icon' => 'fa-plus',      'perm' => 'admin.roles.manage'],
        ],
    ],
    'sistema' => [
        'label'   => t('admin.subnav.section.sistema'),
        'icon'    => 'fa-server',
        'pattern' => '#/(admin/modules|admin/settings|admin/scheduler|admin/backup)#',
        'links'   => [
            ['label' => t('admin.subnav.link.modules'),  'route' => 'admin.modules.index',  'icon' => 'fa-puzzle-piece', 'perm' => 'admin.modules.manage'],
            ['label' => t('admin.subnav.link.settings'), 'route' => 'admin.settings.index', 'icon' => 'fa-sliders',      'perm' => 'admin.settings.manage'],
            ['label' => t('admin.subnav.link.import'),   'route' => 'admin.modules.import', 'icon' => 'fa-upload',       'perm' => 'admin.modules.manage'],
        ],
    ],
    'comunicazioni' => [
        'label'   => t('admin.subnav.section.comunicazioni'),
        'icon'    => 'fa-envelope',
        'pattern' => '#/(admin/mail|admin/changelog|admin/notifications)#',
        'links'   => [
            ['label' => t('admin.subnav.link.email'),     'route' => 'admin.mail.index',         'icon' => 'fa-envelope',    'perm' => 'admin.mail.manage'],
            ['label' => t('admin.subnav.link.changelog'), 'route' => 'admin.changelog.index',    'icon' => 'fa-code-branch', 'perm' => 'admin.changelog.manage'],
            ['label' => t('admin.subnav.link.notifications'), 'route' => 'admin.notifications.send', 'icon' => 'fa-bell',    'perm' => 'notifications.admin.send'],
            ['label' => t('admin.subnav.link.mail_log'),  'route' => 'admin.mail.log',           'icon' => 'fa-list',        'perm' => 'admin.mail.log'],
        ],
    ],
    'sicurezza' => [
        'label'   => t('admin.subnav.section.sicurezza'),
        'icon'    => 'fa-shield-halved',
        'pattern' => '#/(admin/logs|admin/security|admin/retention)#',
        'links'   => [
            ['label' => t('admin.subnav.link.logs'),      'route' => 'admin.logs.index',           'icon' => 'fa-list-check',           'perm' => 'admin.logs.view'],
            ['label' => t('admin.subnav.link.incidents'), 'route' => 'admin.security.incidents',   'icon' => 'fa-triangle-exclamation', 'perm' => 'admin.security.view'],
            ['label' => t('admin.subnav.link.hardening'), 'route' => 'admin.security.hardening',   'icon' => 'fa-lock',                 'perm' => 'admin.security.view'],
            ['label' => t('admin.subnav.link.assets'),    'route' => 'admin.security.assets',      'icon' => 'fa-boxes-stacked',        'perm' => 'admin.security.view'],
            ['label' => t('admin.subnav.link.keys'),      'route' => 'admin.security.keys',        'icon' => 'fa-key',                  'perm' => 'admin.security.view'],
            ['label' => t('admin.subnav.link.sod'),       'route' => 'admin.security.sod',         'icon' => 'fa-users-between-lines',  'perm' => 'admin.security.view'],
            ['label' => t('admin.subnav.link.logs_status'), 'route' => 'admin.security.logs',      'icon' => 'fa-scroll',               'perm' => 'admin.security.view'],
            ['label' => t('admin.subnav.link.retention'), 'route' => 'admin.retention.index',      'icon' => 'fa-clock-rotate-left',    'perm' => 'admin.security.view'],
        ],
    ],
];

$currentSection    = null;
foreach ($sections as $sec) {
    if (preg_match($sec['pattern'], $uri)) {
        $currentSection = $sec;
        break;
    }
}
if (!$currentSection) return;

$visibleLinks = array_filter($currentSection['links'], function ($l) {
    try {
        route($l['route']); // verifica che la route esista
        return has_permission($l['perm']);
    } catch (\Throwable $e) {
        return false;
    }
});
if (empty($visibleLinks)) return;
?>
<nav class="adm-subnav mb-3" aria-label="<?= e(t('admin.subnav.section_nav', ['section' => $currentSection['label']])) ?>">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= e(route('admin.dashboard')) ?>"
           class="adm-subnav-back"
           data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?= e(t('admin.subnav.back')) ?>">
            <i class="fa-solid fa-arrow-left fa-xs"></i>
        </a>
        <span class="adm-subnav-section">
            <i class="fa-solid <?= e($currentSection['icon']) ?> fa-xs me-1" aria-hidden="true"></i>
            <?= e($currentSection['label']) ?>
        </span>
        <span class="adm-subnav-sep" aria-hidden="true">/</span>
        <?php foreach ($visibleLinks as $link):
            $lUrl    = route($link['route']);
            $lPath   = rtrim(parse_url($lUrl, PHP_URL_PATH) ?? '', '/');
            $lActive = str_starts_with(rtrim($uri, '/'), $lPath);
        ?>
        <a href="<?= e($lUrl) ?>"
           class="adm-subnav-link <?= $lActive ? 'adm-subnav-link--active' : '' ?>"
           data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?= e($link['label']) ?>">
            <i class="fa-solid <?= e($link['icon']) ?> fa-xs me-1" aria-hidden="true"></i>
            <?= e($link['label']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
