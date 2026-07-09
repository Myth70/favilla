<?php
/**
 * Pannello admin — configurazione Web Push (chiavi VAPID + stato subscription).
 * Variabili: $webPush = ['configured' => bool, 'public_key' => ?string,
 *                        'subject' => string, 'stats' => ['subscriptions' => int, 'users' => int]]
 */
$webPush = $webPush ?? ['configured' => false, 'public_key' => null, 'subject' => '', 'stats' => ['subscriptions' => 0, 'users' => 0]];
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="app-card-icon"><i class="fa-solid fa-tower-broadcast"></i></span>
                <span class="fw-semibold"><?= e(t('notifications.admin.webpush.title')) ?></span>
                <?php if (!empty($webPush['configured'])): ?>
                    <span class="badge text-bg-success ms-auto"><i class="fa-solid fa-circle-check me-1"></i><?= e(t('notifications.admin.webpush.status_active')) ?></span>
                <?php else: ?>
                    <span class="badge text-bg-warning ms-auto"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= e(t('notifications.admin.webpush.status_missing')) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="text-secondary small mb-3"><?= e(t('notifications.admin.webpush.intro')) ?></p>

                <?php if (!empty($webPush['configured'])): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="ntas-webpush-key"><?= e(t('notifications.admin.webpush.public_key')) ?></label>
                        <input type="text" class="form-control form-control-sm font-monospace" id="ntas-webpush-key"
                               value="<?= e((string) $webPush['public_key']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <span class="small text-secondary"><?= e(t('notifications.admin.webpush.subject')) ?>:</span>
                        <span class="small fw-semibold"><?= e((string) $webPush['subject']) ?></span>
                    </div>
                    <form method="POST" action="<?= e(route('admin.notifications.webpush.generate')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="force" value="1">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                data-app-confirm="<?= e(t('notifications.admin.webpush.regenerate_confirm')) ?>"
                                data-app-confirm-label="<?= e(t('notifications.admin.webpush.regenerate')) ?>">
                            <i class="fa-solid fa-rotate me-1"></i><?= e(t('notifications.admin.webpush.regenerate')) ?>
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="<?= e(route('admin.notifications.webpush.generate')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-key me-1"></i><?= e(t('notifications.admin.webpush.generate')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="app-card-icon"><i class="fa-solid fa-mobile-screen"></i></span>
                <span class="fw-semibold"><?= e(t('notifications.admin.webpush.devices_title')) ?></span>
            </div>
            <div class="card-body">
                <div class="d-flex gap-4 mb-3">
                    <div>
                        <div class="fs-4 fw-bold"><?= e((string) (int) ($webPush['stats']['subscriptions'] ?? 0)) ?></div>
                        <div class="small text-secondary"><?= e(t('notifications.admin.webpush.stat_subscriptions')) ?></div>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= e((string) (int) ($webPush['stats']['users'] ?? 0)) ?></div>
                        <div class="small text-secondary"><?= e(t('notifications.admin.webpush.stat_users')) ?></div>
                    </div>
                </div>
                <div class="alert alert-info small mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i><?= e(t('notifications.admin.webpush.requirements_hint')) ?>
                </div>
            </div>
        </div>
    </div>
</div>
