<?php $view->layout('main'); ?>

<?php $view->start('content'); ?>
<?php $view->pushStyle('css/admin.css'); ?>
<?php $view->pushScript('js/admin.js'); ?>

<?php
// Dati sistema dal DB
$systemSettings = $groups['system'] ?? [];
$systemByKey = [];
foreach ($systemSettings as $s) {
    $systemByKey[$s['key']] = $s;
}
// Rimuovi system dal rendering generico
$renderGroups = array_diff_key($groups, ['system' => true]);
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'  => 'fa-solid fa-sliders',
        'adminTitle' => t('admin.settings.config_title'),
    ]); ?>
    <?php $view->include('Admin/Views/partials/admin-subnav'); ?>

    <!-- Search -->
    <div class="d-flex align-items-center mb-3 gap-2">
        <div class="input-group input-group-sm adm-search-input">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" id="settings-search" class="form-control"
                   placeholder="<?= e(t('admin.settings.search_placeholder')) ?>" autocomplete="off" spellcheck="false">
            <button type="button" class="btn btn-outline-secondary d-none" id="settings-search-clear"
                    data-bs-toggle="tooltip" title="<?= e(t('admin.settings.search_clear')) ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <span id="settings-search-count" class="small text-muted d-none"></span>
    </div>

    <!-- Tab navigation -->
    <ul class="nav nav-tabs mb-4" id="configTabs" data-adm-lazy role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-sistema" data-bs-toggle="tab"
                    data-bs-target="#pane-sistema" type="button" role="tab">
                <i class="fa-solid fa-server me-1"></i><?= e(t('admin.settings.tab_system')) ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-generali" data-bs-toggle="tab"
                    data-bs-target="#pane-generali" type="button" role="tab">
                <i class="fa-solid fa-sliders me-1"></i><?= e(t('admin.settings.tab_general')) ?>
            </button>
        </li>
        <?php if (has_permission('admin.mail.manage')): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-email" data-bs-toggle="tab"
                    data-bs-target="#pane-email" type="button" role="tab">
                <i class="fa-solid fa-envelope me-1"></i><?= e(t('admin.settings.tab_email')) ?>
            </button>
        </li>
        <?php endif; ?>
    </ul>

    <div class="tab-content">
        <!-- Sistema tab -->
        <div class="tab-pane fade show active" id="pane-sistema" role="tabpanel">

            <!-- Toggle istantanei (fuori dal form) -->
            <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
                <!-- Debug -->
                <div class="col" data-setting-search="modalita debug app_debug mostra errori dettagliati stack trace produzione">
                    <div class="adm-toggle-card">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="adm-section-icon">
                                    <i class="fa-solid fa-bug"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= e(t('admin.settings.debug_title')) ?></h6>
                                    <small class="text-muted adm-key-hint">APP_DEBUG</small>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <div id="toggle-wrap-app_debug">
                                    <input class="form-check-input" type="checkbox"
                                           id="setting-app_debug"
                                           <?= ((int)($systemByKey['app_debug']['value'] ?? 0)) ? 'checked' : '' ?>
                                           hx-post="<?= e(route('admin.settings.system.toggle')) ?>"
                                           hx-vals='{"key": "app_debug"}'
                                           hx-target="#toggle-wrap-app_debug"
                                           hx-swap="innerHTML">
                                </div>
                            </div>
                        </div>
                        <p class="text-muted small mb-2">
                            <?= t('admin.settings.debug_desc') ?>
                        </p>
                        <span class="adm-instant-badge">
                            <i class="fa-solid fa-bolt"></i> <?= e(t('admin.settings.instant_effect')) ?>
                        </span>
                    </div>
                </div>

                <!-- Manutenzione -->
                <div class="col" data-setting-search="modalita manutenzione maintenance_mode amministratori pagina manutenzione">
                    <div class="adm-toggle-card">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="adm-section-icon">
                                    <i class="fa-solid fa-wrench"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?= e(t('admin.settings.maint_title')) ?></h6>
                                    <small class="text-muted adm-key-hint">MAINTENANCE_MODE</small>
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <div id="toggle-wrap-maintenance_mode">
                                    <input class="form-check-input" type="checkbox"
                                           id="setting-maintenance_mode"
                                           <?= ((int)($systemByKey['maintenance_mode']['value'] ?? 0)) ? 'checked' : '' ?>
                                           hx-post="<?= e(route('admin.settings.system.toggle')) ?>"
                                           hx-vals='{"key": "maintenance_mode"}'
                                           hx-target="#toggle-wrap-maintenance_mode"
                                           hx-swap="innerHTML">
                                </div>
                            </div>
                        </div>
                        <p class="text-muted small mb-2">
                            <?= e(t('admin.settings.maint_desc')) ?>
                        </p>
                        <span class="adm-instant-badge">
                            <i class="fa-solid fa-bolt"></i> <?= e(t('admin.settings.instant_effect')) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Form configurazione -->
            <form method="POST" action="<?= e(route('admin.settings.system.update')) ?>">
                <?= csrf_field() ?>
                <div class="card adm-card mb-4">
                    <div class="card-header adm-card-header">
                        <h5 class="mb-0"><i class="fa-solid fa-gear me-2"></i><?= e(t('admin.settings.config_card')) ?></h5>
                    </div>
                    <div class="card-body">

                        <!-- Ambiente -->
                        <div class="row mb-3 align-items-center" data-setting-search="ambiente applicazione app_env determina il livello di dettaglio negli errori e nei log">
                            <label class="col-md-3 col-form-label adm-label" for="setting-app_env">
                                <?= e(t('admin.settings.env_label')) ?>
                            </label>
                            <div class="col-md-6">
                                <select class="form-select" name="app_env" id="setting-app_env">
                                    <option value="development" <?= ($systemByKey['app_env']['value'] ?? '') === 'development' ? 'selected' : '' ?>>
                                        Development
                                    </option>
                                    <option value="production" <?= ($systemByKey['app_env']['value'] ?? '') === 'production' ? 'selected' : '' ?>>
                                        Production
                                    </option>
                                </select>
                                <div class="form-text">
                                    <?= e(t('admin.settings.env_help')) ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted adm-key-hint">APP_ENV</small>
                            </div>
                        </div>

                        <!-- Timeout impersonazione -->
                        <div class="row mb-3 align-items-center" data-setting-search="timeout impersonazione minuti impersonation_timeout sessione admin">
                            <label class="col-md-3 col-form-label adm-label" for="setting-impersonation_timeout">
                                <?= e(t('admin.settings.imp_timeout_label')) ?>
                            </label>
                            <div class="col-md-6">
                                <input type="number" class="form-control adm-input-sm"
                                       name="impersonation_timeout" id="setting-impersonation_timeout"
                                       value="<?= e($systemByKey['impersonation_timeout']['value'] ?? '30') ?>"
                                       min="1" max="480">
                                <div class="form-text">
                                    <?= e(t('admin.settings.imp_timeout_help')) ?>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted adm-key-hint">impersonation_timeout</small>
                            </div>
                        </div>

                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary"
                                    data-bs-toggle="tooltip" title="<?= e(t('admin.settings.save_system_tip')) ?>">
                                <i class="fa-solid fa-save me-1"></i><?= e(t('admin.settings.save_config')) ?>
                            </button>
                        </div>

                    </div>
                </div>
            </form>

            <!-- Stato .env compatto -->
            <div class="adm-env-status mb-4">
                <i class="fa-solid fa-circle-info text-muted"></i>
                <span class="text-muted fw-medium"><?= e(t('admin.settings.env_file')) ?></span>
                <code>APP_ENV=<?= e($envValues['APP_ENV'] ?? '?') ?></code>
                <code>APP_DEBUG=<?= e($envValues['APP_DEBUG'] ?? '?') ?></code>
                <code>MAINTENANCE_MODE=<?= e($envValues['MAINTENANCE_MODE'] ?? '?') ?></code>
            </div>

        </div>

        <!-- Generali tab -->
        <div class="tab-pane fade" id="pane-generali" role="tabpanel">
            <form method="POST" action="<?= e(route('admin.settings.update')) ?>">
                <?= csrf_field() ?>

                <?php
                $groupLabels = [
                    'general'  => ['label' => t('admin.settings.group_general'),  'icon' => 'fa-sliders'],
                    'mail'     => ['label' => t('admin.settings.group_mail'),     'icon' => 'fa-envelope'],
                    'security' => ['label' => t('admin.settings.group_security'), 'icon' => 'fa-shield-halved'],
                    'sso'      => ['label' => t('admin.settings.group_sso'),      'icon' => 'fa-key'],
                ];
                ?>

                <?php foreach ($renderGroups as $groupKey => $settings): ?>
                <?php $gInfo = $groupLabels[$groupKey] ?? ['label' => ucfirst($groupKey), 'icon' => 'fa-gear']; ?>
                <div class="card adm-card mb-4">
                    <div class="card-header adm-card-header">
                        <h5 class="mb-0"><i class="fa-solid <?= e($gInfo['icon']) ?> me-2"></i><?= e($gInfo['label']) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($settings as $setting): ?>
                        <div class="row mb-3 align-items-center" data-setting-search="<?= e(strtolower(($setting['label'] ?? $setting['key']) . ' ' . $setting['key'] . ' ' . ($setting['description'] ?? ''))) ?>">
                            <label class="col-md-3 col-form-label adm-label" for="setting-<?= e($setting['key']) ?>">
                                <?= e($setting['label'] ?? $setting['key']) ?>
                            </label>
                            <div class="col-md-6">
                                <?php if ($setting['type'] === 'bool'): ?>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox"
                                               name="<?= e($setting['key']) ?>"
                                               id="setting-<?= e($setting['key']) ?>"
                                               value="1"
                                               <?= ((int)$setting['value']) ? 'checked' : '' ?>>
                                    </div>
                                <?php elseif ($setting['key'] === 'app_edition'): ?>
                                    <select class="form-select" name="<?= e($setting['key']) ?>" id="setting-<?= e($setting['key']) ?>">
                                        <?php foreach (array_keys(config('editions.profiles', [])) as $editionKey): ?>
                                        <option value="<?= e($editionKey) ?>" <?= $setting['value'] === $editionKey ? 'selected' : '' ?>>
                                            <?= e(t('admin.settings.edition_' . $editionKey)) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <?= e(t('admin.settings.edition_help')) ?>
                                    </div>
                                <?php elseif ($setting['key'] === 'mail_driver'): ?>
                                    <select class="form-select" name="<?= e($setting['key']) ?>" id="setting-<?= e($setting['key']) ?>">
                                        <option value="log" <?= $setting['value'] === 'log' ? 'selected' : '' ?>><?= e(t('admin.settings.mail_driver_log')) ?></option>
                                        <option value="smtp" <?= $setting['value'] === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                    </select>
                                <?php elseif ($setting['key'] === 'smtp_encryption'): ?>
                                    <select class="form-select" name="<?= e($setting['key']) ?>" id="setting-<?= e($setting['key']) ?>">
                                        <option value="tls" <?= $setting['value'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= $setting['value'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="none" <?= $setting['value'] === 'none' ? 'selected' : '' ?>><?= e(t('admin.settings.smtp_enc_none')) ?></option>
                                    </select>
                                <?php elseif ($setting['key'] === 'smtp_password'): ?>
                                    <input type="password" class="form-control"
                                           name="<?= e($setting['key']) ?>"
                                           id="setting-<?= e($setting['key']) ?>"
                                           value="<?= e($setting['value'] ?? '') ?>"
                                           autocomplete="off">
                                <?php elseif ($setting['key'] === 'sso_oidc_client_secret'): ?>
                                    <input type="password" class="form-control"
                                           name="<?= e($setting['key']) ?>"
                                           id="setting-<?= e($setting['key']) ?>"
                                           value=""
                                           placeholder="<?= !empty($oidcSecretSet) ? '••••••••' : '' ?>"
                                           autocomplete="new-password">
                                    <div class="form-text"><?= e(t('admin.settings.sso_secret_help')) ?></div>
                                <?php elseif ($setting['key'] === 'sso_oidc_jit_default_role'): ?>
                                    <select class="form-select" name="<?= e($setting['key']) ?>" id="setting-<?= e($setting['key']) ?>">
                                        <?php foreach (($jitRoles ?? []) as $role): ?>
                                        <option value="<?= e($role['slug']) ?>" <?= $setting['value'] === $role['slug'] ? 'selected' : '' ?>>
                                            <?= e($role['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($setting['type'] === 'int'): ?>
                                    <input type="number" class="form-control"
                                           name="<?= e($setting['key']) ?>"
                                           id="setting-<?= e($setting['key']) ?>"
                                           value="<?= e($setting['value'] ?? '') ?>">
                                <?php else: ?>
                                    <input type="text" class="form-control"
                                           name="<?= e($setting['key']) ?>"
                                           id="setting-<?= e($setting['key']) ?>"
                                           value="<?= e($setting['value'] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted adm-key-hint"><?= e($setting['key']) ?></small>
                            </div>
                            <?php if ($setting['key'] === 'sso_only'): ?>
                            <div class="col-md-6 offset-md-3">
                                <div class="form-text text-warning-emphasis">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i><?= e(t('admin.settings.sso_only_warning')) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>

                        <?php if ($groupKey === 'sso'): ?>
                        <hr>
                        <div class="row mb-3 align-items-center">
                            <label class="col-md-3 col-form-label adm-label" for="sso-redirect-uri">
                                <?= e(t('admin.settings.sso_redirect_uri_label')) ?>
                            </label>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sso-redirect-uri" readonly
                                           value="<?= e($oidcRedirectUri ?? '') ?>">
                                    <button type="button" class="btn btn-outline-secondary" id="sso-copy-uri"
                                            data-bs-toggle="tooltip" title="<?= e(t('admin.settings.sso_copy_uri')) ?>">
                                        <i class="fa-solid fa-copy"></i>
                                    </button>
                                </div>
                                <div class="form-text"><?= e(t('admin.settings.sso_redirect_uri_help')) ?></div>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-primary btn-sm"
                                        hx-post="<?= e(route('admin.settings.sso.test')) ?>"
                                        hx-include="#setting-sso_oidc_issuer"
                                        hx-swap="none">
                                    <i class="fa-solid fa-plug-circle-check me-1"></i><?= e(t('admin.settings.sso_test_button')) ?>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="text-end mb-4">
                    <button type="submit" class="btn btn-primary"
                            data-bs-toggle="tooltip" title="<?= e(t('admin.settings.save_all_tip')) ?>">
                        <i class="fa-solid fa-save me-1"></i><?= e(t('admin.settings.save_all')) ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Email tab (lazy-loaded) -->
        <?php if (has_permission('admin.mail.manage')): ?>
        <div class="tab-pane fade" id="pane-email" role="tabpanel">
            <div hx-get="<?= e(route('admin.mail.panel')) ?>"
                 hx-trigger="load"
                 hx-swap="innerHTML">
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> <?= e(t('admin.settings.loading')) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';
    var inp = document.getElementById('settings-search');
    var clr = document.getElementById('settings-search-clear');
    var cnt = document.getElementById('settings-search-count');
    if (!inp) return;

    var L_countOne  = <?= json_encode(t('admin.settings.search_count_one')) ?>;
    var L_countMany = <?= json_encode(t('admin.settings.search_count_many')) ?>;

    function filter() {
        var q = inp.value.trim().toLowerCase();
        clr.classList.toggle('d-none', !q);
        var total = 0;
        document.querySelectorAll('.tab-pane').forEach(function (pane) {
            pane.querySelectorAll('[data-setting-search]').forEach(function (row) {
                var match = !q || row.dataset.settingSearch.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) total++;
            });
            pane.querySelectorAll('.card.adm-card').forEach(function (card) {
                var hasVisible = card.querySelector('[data-setting-search]:not([style*="none"])') !== null;
                card.style.display = hasVisible ? '' : 'none';
            });
        });
        if (q) {
            var firstMatch = document.querySelector('.tab-pane [data-setting-search]:not([style*="none"])');
            if (firstMatch) {
                var paneId = firstMatch.closest('.tab-pane').id;
                var tabBtn = document.querySelector('[data-bs-target="#' + paneId + '"]');
                if (tabBtn && !tabBtn.classList.contains('active')) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                }
            }
        }
        cnt.textContent = q ? (total === 1 ? L_countOne : L_countMany).replace(':count', total) : '';
        cnt.classList.toggle('d-none', !q);
    }

    inp.addEventListener('input', filter);
    clr.addEventListener('click', function () { inp.value = ''; filter(); inp.focus(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            e.preventDefault(); inp.focus();
        }
        if (e.key === 'Escape' && document.activeElement === inp) { inp.value = ''; filter(); inp.blur(); }
    });

    // SSO: copia redirect URI negli appunti
    var ssoCopy = document.getElementById('sso-copy-uri');
    var ssoUri  = document.getElementById('sso-redirect-uri');
    if (ssoCopy && ssoUri && navigator.clipboard) {
        ssoCopy.addEventListener('click', function () {
            navigator.clipboard.writeText(ssoUri.value).then(function () {
                var icon = ssoCopy.querySelector('i');
                icon.className = 'fa-solid fa-check';
                setTimeout(function () { icon.className = 'fa-solid fa-copy'; }, 1500);
            });
        });
    }
})();
</script>

<?php $view->end(); ?>
