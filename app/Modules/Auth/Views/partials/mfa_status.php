<?php
/**
 * MFA status partial — HTMX fragment for profile page.
 * Variables: $mfaEnabled, $backupRemaining
 */
?>
<div class="d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-2">
        <i class="fa-solid fa-shield-halved <?= $mfaEnabled ? 'text-success' : 'text-muted' ?>"></i>
        <div>
            <span class="fw-semibold"><?= e(t('auth.mfa.label')) ?></span><br>
            <?php if ($mfaEnabled): ?>
                <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i><?= e(t('auth.mfa.active')) ?></span>
                <span class="text-muted small ms-2"><?= e(t('auth.mfa.backup_count', ['count' => (int) $backupRemaining])) ?></span>
            <?php else: ?>
                <span class="badge bg-secondary"><?= e(t('auth.mfa.inactive')) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($mfaEnabled): ?>
                        <form method="POST" action="<?= e(route('mfa.backup.regenerate')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit"
                        class="btn btn-outline-secondary btn-sm"
                        data-app-confirm="<?= e(t('auth.mfa.regenerate_confirm')) ?>"
                        data-app-confirm-label="<?= e(t('auth.mfa.regenerate_tip')) ?>"
                        data-app-confirm-class="btn-warning"
                        data-bs-toggle="tooltip"
                        title="<?= e(t('auth.mfa.regenerate_tip')) ?>">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </form>
            <button type="button"
                    class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#mfa-disable-modal"
                    title="<?= e(t('auth.mfa.disable_tip')) ?>">
                <i class="fa-solid fa-ban"></i>
            </button>
        <?php else: ?>
            <a href="<?= e(route('mfa.setup')) ?>"
               class="btn btn-primary btn-sm"
               data-bs-toggle="tooltip"
               title="<?= e(t('auth.mfa.configure_tip')) ?>">
                <i class="fa-solid fa-plus me-1"></i><?= e(t('auth.mfa.configure_btn')) ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($mfaEnabled): ?>
<!-- Modal disattivazione MFA -->
<div class="modal fade" id="mfa-disable-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= e(route('mfa.disable')) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><?= e(t('auth.mfa.disable_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        <?= e(t('auth.mfa.disable_warning')) ?>
                    </div>
                    <label for="mfa-disable-pw" class="form-label fw-semibold"><?= e(t('auth.mfa.disable_pw_label')) ?></label>
                    <input type="password"
                           class="form-control"
                           id="mfa-disable-pw"
                           name="password"
                           required
                           autocomplete="current-password"
                           placeholder="<?= e(t('auth.mfa.disable_pw_ph')) ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.action.cancel')) ?></button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa-solid fa-ban me-1"></i><?= e(t('auth.mfa.disable_btn')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
