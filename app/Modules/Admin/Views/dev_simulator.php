<?php
$view->layout('main');
?>
<?php $view->start('content'); ?>

<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'     => 'fa-solid fa-eye',
    'adminTitle'    => t('admin.dev_simulator.page_title'),
    'adminSubtitle' => t('admin.dev_simulator.subtitle'),
]); ?>

<?php /* ── PAGINE AUTH ── */ ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fa-solid fa-lock fa-fw text-body-secondary"></i>
        <strong><?= e(t('admin.dev_simulator.auth_title')) ?></strong>
    </div>
    <div class="card-body">
        <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-3" role="alert">
            <i class="fa-solid fa-circle-info mt-1 flex-shrink-0"></i>
            <span><?= t('admin.dev_simulator.auth_note') ?></span>
        </div>

        <?php
        $authPages = [
            ['label' => t('admin.dev_simulator.auth.login'),             'icon' => 'fa-right-to-bracket', 'url' => route('login')],
            ['label' => t('admin.dev_simulator.auth.register'),          'icon' => 'fa-user-plus',        'url' => route('registrazione')],
            ['label' => t('admin.dev_simulator.auth.register_done'),     'icon' => 'fa-circle-check',     'url' => route('registrazione.completata')],
            ['label' => t('admin.dev_simulator.auth.password_forgot'),   'icon' => 'fa-envelope',         'url' => route('password.forgot')],
            ['label' => t('admin.dev_simulator.auth.password_reset'),    'icon' => 'fa-key',              'url' => route('password.reset.form', ['token' => 'token-preview'])],
            ['label' => t('admin.dev_simulator.auth.password_change'),   'icon' => 'fa-lock',             'url' => route('password.change')],
            ['label' => t('admin.dev_simulator.auth.mfa_challenge'),     'icon' => 'fa-mobile-screen',    'url' => route('mfa.challenge')],
            ['label' => t('admin.dev_simulator.auth.totp_setup'),        'icon' => 'fa-shield-halved',    'url' => route('mfa.setup')],
            ['label' => t('admin.dev_simulator.auth.totp_setup_forced'), 'icon' => 'fa-shield-halved',    'url' => route('mfa.setup.forced')],
            ['label' => t('admin.dev_simulator.auth.backup_codes'),      'icon' => 'fa-list-ol',          'url' => route('mfa.backup.show')],
        ];
        ?>
        <div class="row g-2">
            <?php foreach ($authPages as $p): ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"
                   class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-body sim-card">
                    <i class="fa-solid <?= e($p['icon']) ?> fa-fw text-body-secondary flex-shrink-0"></i>
                    <span class="small"><?= e($p['label']) ?></span>
                    <i class="fa-solid fa-arrow-up-right-from-square fa-xs ms-auto text-body-tertiary"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php /* ── PAGINE ERRORE ── */ ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fa-solid fa-triangle-exclamation fa-fw text-body-secondary"></i>
        <strong><?= e(t('admin.dev_simulator.error_title')) ?></strong>
    </div>
    <div class="card-body">
        <div class="alert alert-success d-flex gap-2 align-items-start py-2 mb-3" role="alert">
            <i class="fa-solid fa-circle-check mt-1 flex-shrink-0"></i>
            <span><?= e(t('admin.dev_simulator.error_note')) ?></span>
        </div>

        <?php
        $errorPages = [
            ['label' => t('admin.dev_simulator.error.e404'),        'icon' => 'fa-magnifying-glass',     'code' => '404'],
            ['label' => t('admin.dev_simulator.error.e403'),        'icon' => 'fa-ban',                  'code' => '403'],
            ['label' => t('admin.dev_simulator.error.e405'),        'icon' => 'fa-circle-xmark',         'code' => '405'],
            ['label' => t('admin.dev_simulator.error.e500'),        'icon' => 'fa-bomb',                 'code' => '500'],
            ['label' => t('admin.dev_simulator.error.maintenance'), 'icon' => 'fa-screwdriver-wrench',   'code' => 'maintenance'],
        ];
        ?>
        <div class="row g-2">
            <?php foreach ($errorPages as $p): ?>
            <?php $url = route('admin.dev.error-preview', ['code' => $p['code']]); ?>
            <div class="col-6 col-md-4 col-lg-3">
                <a href="<?= e($url) ?>" target="_blank" rel="noopener"
                   class="d-flex align-items-center gap-2 p-2 rounded border text-decoration-none text-body sim-card">
                    <i class="fa-solid <?= e($p['icon']) ?> fa-fw text-body-secondary flex-shrink-0"></i>
                    <span class="small"><?= e($p['label']) ?></span>
                    <i class="fa-solid fa-arrow-up-right-from-square fa-xs ms-auto text-body-tertiary"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.sim-card { transition: background-color .15s; }
.sim-card:hover { background-color: var(--bs-tertiary-bg); }
</style>

<?php $view->end(); ?>
