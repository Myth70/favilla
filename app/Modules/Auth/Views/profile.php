<?php $view->layout('main'); ?>
<?php $view->pushStyle('css/cropper.min.css'); ?>
<?php $view->pushStyle('css/avatar-cropper.css'); ?>
<?php $view->pushStyle('css/profile.css'); ?>
<?php $view->pushScript('js/vendor/cropper.min.js'); ?>
<?php $view->pushScript('js/avatar-cropper.js'); ?>
<?php $view->pushScript('js/profile.js'); ?>
<?php $view->start('content'); ?>

<?php
use App\Modules\Auth\Helpers\AvatarHelper;
use App\Modules\Home\Helpers\PatternHelper;

$avatarUrl           = AvatarHelper::url($profileUser['avatar_path'] ?? null);
$initials            = AvatarHelper::initials($profileUser['name'] ?? 'U');
$heroPattern         = PatternHelper::resolveKey();
$allowedHeroPatterns = PatternHelper::allowed();
$patternLabels       = PatternHelper::labels();

$accentPalette  = ['#3b82f6','#8b5cf6','#ec4899','#ef4444','#f97316','#22c55e','#14b8a6','#64748b'];
$accentSlugs    = ['#3b82f6'=>'blue','#8b5cf6'=>'violet','#ec4899'=>'pink','#ef4444'=>'red','#f97316'=>'orange','#22c55e'=>'green','#14b8a6'=>'teal','#64748b'=>'slate'];
$accentNames    = [];
foreach ($accentSlugs as $hex => $slug) {
    $accentNames[$hex] = t('auth.profile.accent.' . $slug);
}
$accentClassMap = ['#3b82f6'=>'pf-swatch-blue','#8b5cf6'=>'pf-swatch-violet','#ec4899'=>'pf-swatch-pink','#ef4444'=>'pf-swatch-red','#f97316'=>'pf-swatch-orange','#22c55e'=>'pf-swatch-green','#14b8a6'=>'pf-swatch-teal','#64748b'=>'pf-swatch-slate'];
$currentAccent       = $preferences['primary_color'] ?? '#3b82f6';
$currentTheme        = $preferences['theme'] ?? 'light';
$currentSkin         = $preferences['theme_skin'] ?? 'default';
$currentFont         = $preferences['font_family'] ?? 'system';
$currentSidebarStyle = $preferences['sidebar_style'] ?? 'default';

$skinCatalog = [
    'default' => ['label' => t('auth.profile.skin.default.label'), 'desc' => t('auth.profile.skin.default.desc')],
    'soft'    => ['label' => t('auth.profile.skin.soft.label'),    'desc' => t('auth.profile.skin.soft.desc')],
    'sharp'   => ['label' => t('auth.profile.skin.sharp.label'),   'desc' => t('auth.profile.skin.sharp.desc')],
    'compact' => ['label' => t('auth.profile.skin.compact.label'), 'desc' => t('auth.profile.skin.compact.desc')],
];

$fontCatalog = [
    'system'    => ['label' => t('auth.profile.font.system.label'),    'desc' => t('auth.profile.font.system.desc'),    'preview' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'],
    'inter'     => ['label' => t('auth.profile.font.inter.label'),     'desc' => t('auth.profile.font.inter.desc'),     'preview' => '"Inter", system-ui, -apple-system, "Segoe UI", sans-serif'],
    'plex'      => ['label' => t('auth.profile.font.plex.label'),      'desc' => t('auth.profile.font.plex.desc'),      'preview' => '"IBM Plex Sans", system-ui, -apple-system, "Segoe UI", sans-serif'],
    'lora'      => ['label' => t('auth.profile.font.lora.label'),      'desc' => t('auth.profile.font.lora.desc'),      'preview' => '"Lora", Georgia, Cambria, serif'],
    'jetbrains' => ['label' => t('auth.profile.font.jetbrains.label'), 'desc' => t('auth.profile.font.jetbrains.desc'), 'preview' => '"JetBrains Mono", ui-monospace, Menlo, Consolas, monospace'],
];

// Base URL per gli uploads (usata dal cropper quando si seleziona dalla libreria)
$uploadsBase = rtrim((string) config('app.url', ''), '/');
$basePath    = trim((string) config('app.base_path', ''), '/');
if ($basePath !== '') {
    $pathPart = trim((string) parse_url($uploadsBase, PHP_URL_PATH), '/');
    if ($pathPart !== $basePath && !str_ends_with($pathPart, '/' . $basePath)) {
        $uploadsBase .= '/' . $basePath;
    }
}
$uploadsBase .= '/uploads';
?>

<div class="container-fluid">

    <!-- ============================================================
         HERO — identita utente (preservato)
         ============================================================ -->
    <?php
    $view->include('partials/pf-hero-user', [
        'userName'         => $profileUser['name'] ?? '',
        'userSubtitle'     => $profileUser['email'] ?? '',
        'userAvatar'       => $avatarUrl ?? null,
        'userInitials'     => $initials,
        'userStats'        => [],
        'statsPartialPath' => 'Auth/Views/partials/stats_cards',
    ]);

    $workspaceLinks = [];
    if (isModuleEnabled('Tasks') && has_permission('tasks.view')) {
        $workspaceLinks[] = [
            'label' => t('auth.profile.link_tasks_label'),
            'icon' => 'fa-solid fa-list-check',
            'secondaryIcon' => 'fa-solid fa-calendar-check',
            'description' => t('auth.profile.link_tasks_desc'),
            'route' => route('tasks.index'),
            'cta' => t('auth.profile.link_tasks_cta'),
            'secondaryRoute' => route('tasks.list') . '?scope=linked',
            'secondaryCta' => t('auth.profile.link_tasks_cta2'),
        ];
    }
    if (isModuleEnabled('Calendar') && has_permission('calendar.view')) {
        $workspaceLinks[] = [
            'label' => t('auth.profile.link_calendar_label'),
            'icon' => 'fa-solid fa-calendar-days',
            'secondaryIcon' => null,
            'description' => t('auth.profile.link_calendar_desc'),
            'route' => route('calendar.index'),
            'cta' => t('auth.profile.link_calendar_cta'),
            'secondaryRoute' => null,
            'secondaryCta' => null,
        ];
    }
    if (isModuleEnabled('Files') && has_permission('files.access')) {
        $workspaceLinks[] = [
            'label' => t('auth.profile.link_files_label'),
            'icon' => 'fa-solid fa-folder-open',
            'secondaryIcon' => 'fa-solid fa-cloud-arrow-up',
            'description' => t('auth.profile.link_files_desc'),
            'route' => route('files.index'),
            'cta' => t('auth.profile.link_files_cta'),
            'secondaryRoute' => route('files.upload'),
            'secondaryCta' => t('auth.profile.link_files_cta2'),
        ];
    }
    if (isModuleEnabled('Contacts') && has_permission('contacts.view')) {
        $workspaceLinks[] = [
            'label' => t('auth.profile.link_contacts_label'),
            'icon' => 'fa-solid fa-address-book',
            'secondaryIcon' => 'fa-solid fa-user-plus',
            'description' => t('auth.profile.link_contacts_desc'),
            'route' => route('contacts.index'),
            'cta' => t('auth.profile.link_contacts_cta'),
            'secondaryRoute' => has_permission('contacts.create') ? route('contacts.create') : null,
            'secondaryCta' => has_permission('contacts.create') ? t('auth.profile.link_contacts_cta2') : null,
        ];
    }
    ?>

    <!-- ============================================================
         WORKSPACE — single card, tab-based, compatto e omogeneo
         ============================================================ -->
    <div class="pf-ws card shadow-sm overflow-hidden">

        <!-- Cropper config (letto da avatar-cropper.js via profile.js) -->
        <div id="pf-cropper-config"
             data-crop-url="<?= e(route('api.avatar.crop')) ?>"
             data-context="profile"
             data-context-id="<?= e($profileUser['id']) ?>"
             data-uploads-base="<?= e($uploadsBase) ?>"
             class="d-none"></div>

        <!-- Tab navigation -->
        <div class="pf-ws-nav" role="tablist" aria-label="<?= e(t('auth.profile.nav_aria')) ?>">
            <button type="button"
                    class="pf-ws-pill active"
                    id="pf-ws-tab-profile"
                    data-bs-toggle="pill"
                    data-bs-target="#pf-ws-panel-profile"
                    role="tab"
                    aria-controls="pf-ws-panel-profile"
                    aria-selected="true">
                <i class="fa-solid fa-user"></i>
                <span><?= e(t('auth.profile.tab_profile')) ?></span>
            </button>
            <button type="button"
                    class="pf-ws-pill"
                    id="pf-ws-tab-security"
                    data-bs-toggle="pill"
                    data-bs-target="#pf-ws-panel-security"
                    role="tab"
                    aria-controls="pf-ws-panel-security"
                    aria-selected="false">
                <i class="fa-solid fa-shield-halved"></i>
                <span><?= e(t('auth.profile.tab_security')) ?></span>
            </button>
            <button type="button"
                    class="pf-ws-pill"
                    id="pf-ws-tab-appearance"
                    data-bs-toggle="pill"
                    data-bs-target="#pf-ws-panel-appearance"
                    role="tab"
                    aria-controls="pf-ws-panel-appearance"
                    aria-selected="false">
                <i class="fa-solid fa-palette"></i>
                <span><?= e(t('auth.profile.tab_appearance')) ?></span>
            </button>
        </div>

        <div class="tab-content pf-ws-panels">

            <!-- ============= PROFILO ============= -->
            <section class="tab-pane fade show active pf-ws-panel"
                     id="pf-ws-panel-profile"
                     role="tabpanel"
                     aria-labelledby="pf-ws-tab-profile"
                     tabindex="0">
                <div class="pf-profile-grid">

                    <!-- Identita: avatar + upload -->
                    <div class="pf-identity">
                        <div class="pf-identity-preview">
                            <?php if ($avatarUrl): ?>
                                <img src="<?= e($avatarUrl) ?>"
                                     alt="<?= e(t('auth.profile.avatar_alt')) ?>"
                                     class="pf-identity-img"
                                     id="pf-avatar-preview"
                                     data-original-src="<?= e($avatarUrl) ?>">
                            <?php else: ?>
                                <div class="pf-identity-img pf-identity-placeholder" id="pf-avatar-preview">
                                    <?= e($initials) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <input type="file" id="pf-avatar-input"
                               accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
                        <input type="hidden" id="pf-avatar-url" value="">

                        <div class="pf-drop-zone pf-identity-drop" id="pf-drop-zone">
                            <i class="fa-solid fa-cloud-arrow-up pf-drop-icon"></i>
                            <div class="pf-drop-text">
                                <?= e(t('auth.profile.drop_text')) ?>
                                <button type="button" class="btn btn-link p-0 pf-drop-browse" id="pf-drop-browse"><?= e(t('auth.profile.drop_browse')) ?></button>
                            </div>
                            <div class="pf-drop-hint"><?= e(t('auth.profile.drop_hint')) ?></div>
                        </div>

                        <div class="pf-identity-actions">
                            <?php if (isModuleEnabled('Files')): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-pf-open-picker="1"
                                    data-picker-input="pf-avatar-url"
                                    data-picker-type="image"
                                    data-bs-toggle="tooltip"
                                    title="<?= e(t('auth.profile.avatar_library')) ?>">
                                <i class="fa-solid fa-folder-open me-1"></i><?= e(t('auth.profile.btn_library')) ?>
                            </button>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['avatar_path'])): ?>
                            <form method="POST" action="<?= e(route('profile.avatar.remove')) ?>"
                                  class="d-inline" id="pf-avatar-remove-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        data-app-confirm="<?= e(t('auth.profile.avatar_confirm')) ?>"
                                        data-app-confirm-label="<?= e(t('common.action.remove')) ?>"
                                        data-bs-toggle="tooltip" title="<?= e(t('auth.profile.avatar_remove')) ?>">
                                    <i class="fa-solid fa-trash me-1"></i><?= e(t('auth.profile.btn_remove')) ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Form info personali -->
                    <div class="pf-info">
                        <form method="POST" action="<?= e(route('profile.update')) ?>" class="pf-info-form">
                            <?= csrf_field() ?>

                            <div class="pf-field">
                                <label for="name" class="form-label"><?= e(t('auth.profile.name_label')) ?></label>
                                <div class="input-group">
                                    <span class="input-group-text app-input-icon"><i class="fa-solid fa-id-badge"></i></span>
                                    <input type="text" name="name" id="name"
                                           class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                                           value="<?= e($old['name'] ?? $profileUser['name'] ?? '') ?>"
                                           required maxlength="100">
                                    <?php if (!empty($errors['name'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="form-text text-muted"><?= e(t('auth.profile.name_help')) ?></small>
                            </div>

                            <div class="pf-field">
                                <label for="pf-display-email" class="form-label"><?= e(t('auth.profile.email_label')) ?></label>
                                <div class="input-group">
                                    <span class="input-group-text app-input-icon"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" id="pf-display-email"
                                           class="form-control pf-field-readonly"
                                           value="<?= e($profileUser['email'] ?? '') ?>" readonly>
                                </div>
                                <small class="form-text text-muted"><?= e(t('auth.profile.email_help')) ?></small>
                            </div>

                            <div class="pf-info-actions">
                                <button type="submit" class="btn btn-primary"
                                        data-bs-toggle="tooltip" title="<?= e(t('auth.profile.save_name')) ?>">
                                    <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('auth.profile.save_changes')) ?>
                                </button>
                            </div>

                            <?php if (!empty($workspaceLinks)): ?>
                            <div class="pf-profile-toolbar-wrap">
                                <div class="pf-profile-toolbar-head">
                                    <span class="pf-profile-toolbar-label">
                                        <i class="fa-solid fa-compass"></i>
                                        <?= e(t('auth.profile.ws_title')) ?>
                                    </span>
                                    <small class="pf-profile-toolbar-hint"><?= e(t('auth.profile.ws_hint')) ?></small>
                                </div>

                                <div class="pf-profile-toolbar" role="toolbar" aria-label="<?= e(t('auth.profile.workspace_aria')) ?>">
                                    <?php foreach ($workspaceLinks as $workspaceLink): ?>
                                    <div class="pf-profile-toolbar-group" role="group" aria-label="<?= e($workspaceLink['label']) ?>">
                                        <a href="<?= e($workspaceLink['route']) ?>"
                                           class="pf-profile-toolbar-btn"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           title="<?= e($workspaceLink['label'] . ': ' . $workspaceLink['cta']) ?>"
                                           aria-label="<?= e($workspaceLink['label'] . ': ' . $workspaceLink['cta']) ?>">
                                            <i class="<?= e($workspaceLink['icon']) ?>"></i>
                                            <span class="visually-hidden"><?= e($workspaceLink['label'] . ': ' . $workspaceLink['cta']) ?></span>
                                        </a>

                                        <?php if (!empty($workspaceLink['secondaryRoute']) && !empty($workspaceLink['secondaryCta'])): ?>
                                        <a href="<?= e($workspaceLink['secondaryRoute']) ?>"
                                           class="pf-profile-toolbar-btn pf-profile-toolbar-btn-secondary"
                                           data-bs-toggle="tooltip"
                                           data-bs-placement="top"
                                           title="<?= e($workspaceLink['label'] . ': ' . $workspaceLink['secondaryCta']) ?>"
                                           aria-label="<?= e($workspaceLink['label'] . ': ' . $workspaceLink['secondaryCta']) ?>">
                                            <i class="<?= e($workspaceLink['secondaryIcon'] ?? 'fa-solid fa-arrow-up-right-from-square') ?>"></i>
                                            <span class="visually-hidden"><?= e($workspaceLink['label'] . ': ' . $workspaceLink['secondaryCta']) ?></span>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>

                </div>
            </section>

            <!-- ============= SICUREZZA ============= -->
            <section class="tab-pane fade pf-ws-panel"
                     id="pf-ws-panel-security"
                     role="tabpanel"
                     aria-labelledby="pf-ws-tab-security"
                     tabindex="0">
                <div class="pf-prefs-grid" id="security">

                    <!-- Password -->
                    <section class="pf-prefs-section" id="cambia-password">
                        <header class="pf-prefs-subhead">
                            <i class="fa-solid fa-key"></i>
                            <span><?= e(t('auth.profile.pw_title')) ?></span>
                        </header>
                        <form method="POST" action="<?= e(route('profile.password.update')) ?>">
                            <?= csrf_field() ?>
                            <div class="row g-3">
                                <div class="col-md-6 col-lg-4">
                                    <label for="current_password" class="form-label"><?= e(t('auth.profile.pw_current')) ?></label>
                                    <div class="input-group">
                                        <input type="password" name="current_password" id="current_password"
                                               class="form-control <?= !empty($errors['current_password']) ? 'is-invalid' : '' ?>"
                                               autocomplete="current-password" required>
                                        <button class="btn btn-outline-secondary pf-pw-eye" type="button"
                                                data-pf-target="current_password" tabindex="-1"
                                                data-bs-toggle="tooltip" title="<?= e(t('auth.profile.toggle_pw')) ?>">
                                            <i class="fa-solid fa-eye fa-sm"></i>
                                        </button>
                                        <?php if (!empty($errors['current_password'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['current_password']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <label for="password" class="form-label"><?= e(t('auth.profile.pw_new')) ?></label>
                                    <div class="input-group">
                                        <input type="password" name="password" id="password"
                                               class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                                               autocomplete="new-password" required minlength="8">
                                        <button class="btn btn-outline-secondary pf-pw-eye" type="button"
                                                data-pf-target="password" tabindex="-1"
                                                data-bs-toggle="tooltip" title="<?= e(t('auth.profile.toggle_pw')) ?>">
                                            <i class="fa-solid fa-eye fa-sm"></i>
                                        </button>
                                        <?php if (!empty($errors['password'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pf-pw-strength d-none" id="pf-pw-strength">
                                        <div class="pf-pw-bar">
                                            <div class="pf-pw-fill" id="pf-pw-fill"></div>
                                        </div>
                                        <small class="pf-pw-label" id="pf-pw-label"></small>
                                    </div>
                                </div>
                                <div class="col-md-12 col-lg-4">
                                    <label for="password_confirmation" class="form-label"><?= e(t('auth.profile.pw_confirm_label')) ?></label>
                                    <div class="input-group">
                                        <input type="password" name="password_confirmation" id="password_confirmation"
                                               class="form-control <?= !empty($errors['password_confirmation']) ? 'is-invalid' : '' ?>"
                                               autocomplete="new-password" required minlength="8">
                                        <button class="btn btn-outline-secondary pf-pw-eye" type="button"
                                                data-pf-target="password_confirmation" tabindex="-1"
                                                data-bs-toggle="tooltip" title="<?= e(t('auth.profile.toggle_pw')) ?>">
                                            <i class="fa-solid fa-eye fa-sm"></i>
                                        </button>
                                        <?php if (!empty($errors['password_confirmation'])): ?>
                                            <div class="invalid-feedback"><?= e($errors['password_confirmation']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="pf-pw-match d-none" id="pf-pw-match">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span></span>
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2"><?= e(t('auth.profile.pw_hint')) ?></small>
                            <div class="mt-3 d-grid d-sm-flex justify-content-sm-end">
                                <button type="submit" class="btn btn-primary"
                                        data-bs-toggle="tooltip" title="<?= e(t('auth.profile.confirm_pw')) ?>">
                                    <i class="fa-solid fa-key me-1"></i><?= e(t('auth.profile.pw_submit')) ?>
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- MFA -->
                    <section class="pf-prefs-section">
                        <header class="pf-prefs-subhead">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span><?= e(t('auth.profile.mfa_title')) ?></span>
                        </header>
                        <div id="mfa-status-container"
                             hx-get="<?= e(route('mfa.status')) ?>"
                             hx-trigger="load"
                             hx-swap="innerHTML">
                            <div class="text-center text-muted py-3">
                                <i class="fa-solid fa-spinner fa-spin me-1"></i><?= e(t('auth.profile.mfa_loading')) ?>
                            </div>
                        </div>
                    </section>

                    <!-- Token API (modulo Api) -->
                    <?php if (isModuleEnabled('Api')): ?>
                    <section class="pf-prefs-section">
                        <header class="pf-prefs-subhead">
                            <i class="fa-solid fa-plug"></i>
                            <span><?= e(t('api.tokens.title')) ?></span>
                        </header>
                        <p class="text-muted small mb-3"><?= e(t('api.tokens.subtitle')) ?></p>
                        <div class="d-grid d-sm-flex justify-content-sm-start">
                            <a href="<?= e(route('api.tokens.index')) ?>" class="btn btn-outline-primary">
                                <i class="fa-solid fa-plug me-1"></i><?= e(t('api.tokens.manage_cta')) ?>
                            </a>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Cronologia e dispositivi: 3 collapsibles consolidati -->
                    <section class="pf-prefs-section">
                        <header class="pf-prefs-subhead">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span><?= e(t('auth.profile.history_title')) ?></span>
                        </header>
                        <div class="pf-collapsibles">

                            <details class="pf-sec-collapsible pf-collapsible">
                                <summary class="pf-collapsible-head">
                                    <span class="pf-collapsible-icon"><i class="fa-solid fa-desktop"></i></span>
                                    <span class="pf-collapsible-label"><?= e(t('auth.profile.sessions_label')) ?></span>
                                    <span class="pf-collapsible-hint"><?= e(t('auth.profile.sessions_hint')) ?></span>
                                    <i class="fa-solid fa-chevron-down pf-sec-chevron" aria-hidden="true"></i>
                                </summary>
                                <div class="pf-collapsible-body"
                                     hx-get="<?= e(route('profile.sessions')) ?>"
                                     hx-trigger="toggle once from:closest details"
                                     hx-swap="innerHTML">
                                    <div class="text-center text-muted py-3">
                                        <i class="fa-solid fa-spinner fa-spin me-1"></i><?= e(t('auth.profile.sessions_loading')) ?>
                                    </div>
                                </div>
                            </details>

                            <details class="pf-sec-collapsible pf-collapsible">
                                <summary class="pf-collapsible-head">
                                    <span class="pf-collapsible-icon"><i class="fa-solid fa-right-to-bracket"></i></span>
                                    <span class="pf-collapsible-label"><?= e(t('auth.profile.login_label')) ?></span>
                                    <span class="pf-collapsible-hint"><?= e(t('auth.profile.login_hint')) ?></span>
                                    <i class="fa-solid fa-chevron-down pf-sec-chevron" aria-hidden="true"></i>
                                </summary>
                                <div class="pf-collapsible-body"
                                     hx-get="<?= e(route('profile.login-history')) ?>"
                                     hx-trigger="toggle once from:closest details"
                                     hx-swap="innerHTML">
                                    <div class="text-center text-muted py-3">
                                        <i class="fa-solid fa-spinner fa-spin me-1"></i><?= e(t('auth.profile.login_loading')) ?>
                                    </div>
                                </div>
                            </details>

                            <details class="pf-sec-collapsible pf-collapsible">
                                <summary class="pf-collapsible-head">
                                    <span class="pf-collapsible-icon"><i class="fa-solid fa-wave-square"></i></span>
                                    <span class="pf-collapsible-label"><?= e(t('auth.profile.activity_label')) ?></span>
                                    <span class="pf-collapsible-hint"><?= e(t('auth.profile.activity_hint')) ?></span>
                                    <i class="fa-solid fa-chevron-down pf-sec-chevron" aria-hidden="true"></i>
                                </summary>
                                <div class="pf-collapsible-body"
                                     hx-get="<?= e(route('profile.activity')) ?>"
                                     hx-trigger="toggle once from:closest details"
                                     hx-swap="innerHTML">
                                    <div class="text-center text-muted py-3">
                                        <i class="fa-solid fa-spinner fa-spin me-1"></i><?= e(t('auth.profile.activity_loading')) ?>
                                    </div>
                                </div>
                            </details>

                        </div>
                    </section>

                </div>
            </section>

            <!-- ============= ASPETTO ============= -->
            <section class="tab-pane fade pf-ws-panel"
                     id="pf-ws-panel-appearance"
                     role="tabpanel"
                     aria-labelledby="pf-ws-tab-appearance"
                     tabindex="0">
                <div class="pf-ws-grid">

                    <!-- Riga 4-up: Tema | Colore principale | Menu laterale | Colore menu -->
                    <div class="pf-ws-row-aspect">

                    <!-- Tema light/dark -->
                    <div class="pf-ws-group">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-circle-half-stroke"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.theme_title')) ?></h3>
                        </div>
                        <div class="pf-option-grid pf-option-grid-2">
                            <button type="button" id="pf-theme-light"
                                    class="pf-option-tile <?= $currentTheme === 'light' ? 'active' : '' ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('auth.profile.theme_light')) ?>"
                                    aria-label="<?= e(t('auth.profile.theme_light_aria')) ?>">
                                <span class="pf-option-preview pf-option-preview-theme-light" aria-hidden="true">
                                    <i class="fa-solid fa-sun"></i>
                                </span>
                                <span class="pf-option-tile-label"><?= e(t('auth.profile.theme_light_label')) ?></span>
                            </button>
                            <button type="button" id="pf-theme-dark"
                                    class="pf-option-tile <?= $currentTheme === 'dark' ? 'active' : '' ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(t('auth.profile.theme_dark')) ?>"
                                    aria-label="<?= e(t('auth.profile.theme_dark_aria')) ?>">
                                <span class="pf-option-preview pf-option-preview-theme-dark" aria-hidden="true">
                                    <i class="fa-solid fa-moon"></i>
                                </span>
                                <span class="pf-option-tile-label"><?= e(t('auth.profile.theme_dark_label')) ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Colore principale -->
                    <div class="pf-ws-group">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-droplet"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.accent_title')) ?></h3>
                        </div>
                        <div class="pf-accent-grid" role="group" aria-label="<?= e(t('auth.profile.accent_aria')) ?>">
                            <?php foreach ($accentPalette as $swatchColor): ?>
                            <button type="button"
                                    class="accent-swatch pf-accent-swatch <?= e($accentClassMap[$swatchColor] ?? '') ?> <?= $swatchColor === $currentAccent ? 'active' : '' ?>"
                                    data-color="<?= e($swatchColor) ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="<?= e($accentNames[$swatchColor] ?? $swatchColor) ?>"
                                    aria-label="<?= e(t('auth.profile.accent_color_aria', ['name' => $accentNames[$swatchColor] ?? $swatchColor])) ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Menu laterale (collapsed/expanded) — variante compatta -->
                    <div class="pf-ws-group pf-ws-group-compact" id="pf-sidebar-settings">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-table-columns"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.sidebar_title')) ?></h3>
                        </div>
                        <div class="pf-mini-list" role="group" aria-label="<?= e(t('auth.profile.sidebar_state_aria')) ?>">
                            <button type="button"
                                    class="pf-mini-option pf-sidebar-tile <?= empty($preferences['sidebar_collapsed']) ? 'active' : '' ?>"
                                    data-sidebar="0"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    title="<?= e(t('auth.profile.sidebar_full')) ?>"
                                    aria-label="<?= e(t('auth.profile.sidebar_full')) ?>">
                                <span class="pf-mini-rail pf-mini-rail-wide" aria-hidden="true"></span>
                                <span class="pf-mini-label"><?= e(t('auth.profile.sidebar_full_label')) ?></span>
                            </button>
                            <button type="button"
                                    class="pf-mini-option pf-sidebar-tile <?= !empty($preferences['sidebar_collapsed']) ? 'active' : '' ?>"
                                    data-sidebar="1"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    title="<?= e(t('auth.profile.sidebar_compact')) ?>"
                                    aria-label="<?= e(t('auth.profile.sidebar_compact')) ?>">
                                <span class="pf-mini-rail pf-mini-rail-narrow" aria-hidden="true"></span>
                                <span class="pf-mini-label"><?= e(t('auth.profile.sidebar_compact_label')) ?></span>
                            </button>
                        </div>
                        <input type="checkbox" id="profile-sidebar-toggle" class="d-none"
                               <?= !empty($preferences['sidebar_collapsed']) ? 'checked' : '' ?>>
                        <span id="profile-sidebar-label" class="d-none"
                              data-label-expanded="<?= e(t('auth.profile.sidebar_full_label')) ?>" data-label-collapsed="<?= e(t('auth.profile.sidebar_compact_label')) ?>"><?= !empty($preferences['sidebar_collapsed']) ? e(t('auth.profile.sidebar_compact_label')) : e(t('auth.profile.sidebar_full_label')) ?></span>
                    </div>

                    <!-- Colore menu (default/light/accent) — variante compatta -->
                    <div class="pf-ws-group pf-ws-group-compact" id="pf-sidebar-style-settings"
                         data-sidebar-style-url="<?= e(route('preferences.sidebar_style')) ?>">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-bars-staggered"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.sidebar_color_title')) ?></h3>
                        </div>
                        <div class="pf-mini-list" role="group" aria-label="<?= e(t('auth.profile.sidebar_style_aria')) ?>">
                            <button type="button"
                                    class="pf-mini-option pf-sidebar-style-tile <?= $currentSidebarStyle === 'default' ? 'active' : '' ?>"
                                    data-sidebar-style="default"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    title="<?= e(t('auth.profile.sidebar_dark')) ?>"
                                    aria-label="<?= e(t('auth.profile.sidebar_dark')) ?>">
                                <span class="pf-mini-rail pf-mini-rail-style-default" aria-hidden="true"></span>
                                <span class="pf-mini-label"><?= e(t('auth.profile.sidebar_dark_label')) ?></span>
                            </button>
                            <button type="button"
                                    class="pf-mini-option pf-sidebar-style-tile <?= $currentSidebarStyle === 'light' ? 'active' : '' ?>"
                                    data-sidebar-style="light"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    title="<?= e(t('auth.profile.sidebar_light')) ?>"
                                    aria-label="<?= e(t('auth.profile.sidebar_light')) ?>">
                                <span class="pf-mini-rail pf-mini-rail-style-light" aria-hidden="true"></span>
                                <span class="pf-mini-label"><?= e(t('auth.profile.sidebar_light_label')) ?></span>
                            </button>
                            <button type="button"
                                    class="pf-mini-option pf-sidebar-style-tile <?= $currentSidebarStyle === 'accent' ? 'active' : '' ?>"
                                    data-sidebar-style="accent"
                                    data-bs-toggle="tooltip" data-bs-placement="right"
                                    title="<?= e(t('auth.profile.sidebar_tinted')) ?>"
                                    aria-label="<?= e(t('auth.profile.sidebar_tinted')) ?>">
                                <span class="pf-mini-rail pf-mini-rail-style-accent" aria-hidden="true"></span>
                                <span class="pf-mini-label"><?= e(t('auth.profile.sidebar_tinted_label')) ?></span>
                            </button>
                        </div>
                    </div>

                    </div><!-- /.pf-ws-row-aspect -->

                    <!-- Riga 2-up: Stile pagina | Carattere -->
                    <div class="pf-ws-row-design">

                    <!-- Stile pagina (skin) -->
                    <div class="pf-ws-group" id="pf-skin-settings"
                         data-skin-url="<?= e(route('preferences.skin')) ?>">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-swatchbook"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.skin_title')) ?></h3>
                        </div>
                        <div class="pf-skin-grid">
                            <?php foreach ($skinCatalog as $slug => $meta): ?>
                            <button type="button"
                                    class="pf-skin-tile <?= $slug === $currentSkin ? 'active' : '' ?>"
                                    data-skin="<?= e($slug) ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="<?= e($meta['desc']) ?>"
                                    aria-label="<?= e(t('auth.profile.skin_aria', ['label' => $meta['label'], 'desc' => $meta['desc']])) ?>">
                                <span class="pf-skin-preview" data-theme-skin="<?= e($slug) ?>" aria-hidden="true">
                                    <span class="pf-skin-preview-card">
                                        <span class="pf-skin-preview-head">
                                            <span class="pf-skin-preview-dot"></span>
                                            <span class="pf-skin-preview-line"></span>
                                        </span>
                                        <span class="pf-skin-preview-btn">Aa</span>
                                    </span>
                                </span>
                                <span class="pf-skin-tile-label"><?= e($meta['label']) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Carattere -->
                    <div class="pf-ws-group" id="pf-font-settings"
                         data-font-url="<?= e(route('preferences.font')) ?>">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-font"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.font_title')) ?></h3>
                        </div>
                        <div class="pf-font-grid">
                            <?php foreach ($fontCatalog as $fSlug => $fMeta): ?>
                            <button type="button"
                                    class="pf-font-tile <?= $fSlug === $currentFont ? 'active' : '' ?>"
                                    data-font="<?= e($fSlug) ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="<?= e($fMeta['desc']) ?>"
                                    aria-label="<?= e(t('auth.profile.font_aria', ['label' => $fMeta['label'], 'desc' => $fMeta['desc']])) ?>">
                                <span class="pf-font-preview" style="font-family: <?= e($fMeta['preview']) ?>" aria-hidden="true">Aa Bb Cc 123</span>
                                <span class="pf-font-tile-label"><?= e($fMeta['label']) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    </div><!-- /.pf-ws-row-design -->

                    <!-- Sfondo intestazioni (pattern) — riga singola full-width -->
                    <div class="pf-ws-group pf-ws-group-wide" id="pf-pattern-settings"
                         data-pattern-url="<?= e(route('preferences.pattern')) ?>"
                         data-allowed-patterns="<?= e(PatternHelper::toJson()) ?>">
                        <div class="pf-ws-group-head">
                            <span class="pf-ws-group-icon"><i class="fa-solid fa-shapes"></i></span>
                            <h3 class="pf-ws-group-title"><?= e(t('auth.profile.pattern_title')) ?></h3>
                        </div>
                        <div class="pf-pattern-grid" role="group" aria-label="<?= e(t('auth.profile.pattern_aria')) ?>">
                            <?php foreach ($allowedHeroPatterns as $pattern): ?>
                            <button type="button"
                                    class="pf-pattern-btn pf-pattern-tile <?= $heroPattern === $pattern ? 'active' : '' ?>"
                                    data-pattern="<?= e($pattern) ?>"
                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                    title="<?= e($patternLabels[$pattern] ?? ucfirst($pattern)) ?>"
                                    aria-label="<?= e($patternLabels[$pattern] ?? ucfirst($pattern)) ?>">
                                <span class="pf-pattern-preview pf-pattern-<?= e($pattern) ?>" aria-hidden="true"></span>
                                <span class="pf-pattern-tile-label"><?= e($patternLabels[$pattern] ?? ucfirst($pattern)) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </section>

        </div>
    </div>

</div>

<?php $view->include('Auth/Views/partials/cropper_modal'); ?>

<?php $view->end(); ?>
