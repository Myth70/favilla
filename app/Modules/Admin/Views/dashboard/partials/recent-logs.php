<?php
/**
 * Timeline attivita' unificata: audit_logs + login_attempts falliti.
 * Variabile: $unifiedTimeline - array di eventi misti.
 */
$actionConfig = [
    'login'                  => ['fa-door-open',           'success',   t('admin.actions.login')],
    'logout'                 => ['fa-door-closed',          'secondary', t('admin.actions.logout')],
    'password_reset'         => ['fa-key',                  'warning',   t('admin.actions.password_reset')],
    'password_changed'       => ['fa-lock',                 'info',      t('admin.actions.password_changed')],
    'password_forgot_reset'  => ['fa-unlock',               'warning',   t('admin.actions.password_forgot_reset')],
    'user_disabled'          => ['fa-user-slash',           'danger',    t('admin.actions.user_disabled')],
    'user_activated'         => ['fa-user-check',           'success',   t('admin.actions.user_activated')],
    'create'                 => ['fa-circle-plus',          'primary',   t('admin.actions.create')],
    'update'                 => ['fa-pen-to-square',        'info',      t('admin.actions.update')],
    'delete'                 => ['fa-trash',                'danger',    t('admin.actions.delete')],
    'bulk_user_activated'    => ['fa-users',                'success',   t('admin.actions.bulk_user_activated')],
    'bulk_user_deactivated'  => ['fa-users-slash',          'warning',   t('admin.actions.bulk_user_deactivated')],
    'bulk_role_assigned'     => ['fa-user-tag',             'info',      t('admin.actions.bulk_role_assigned')],
    // Evento sintetico per login falliti
    'login_failed'           => ['fa-shield-halved',        'danger',    t('admin.actions.login_failed')],
];
?>
<?php if (empty($unifiedTimeline)): ?>
    <div class="text-center text-muted py-5">
        <i class="fa-solid fa-inbox fa-2x mb-2 d-block opacity-25"></i>
        <?= e(t('admin.actions.empty')) ?>
    </div>
<?php else: ?>
    <div class="adm-timeline">
        <?php foreach ($unifiedTimeline as $log):
            [$icon, $color, $label] = $actionConfig[$log['action']] ?? ['fa-circle', 'secondary', ucfirst(str_replace('_', ' ', $log['action']))];
            $isSecurityEvent = ($log['source'] ?? 'audit') === 'login_fail';
        ?>
            <div class="adm-timeline-item d-flex align-items-start gap-3 px-3 py-2">
                <div class="adm-timeline-icon bg-<?= e($color) ?> bg-opacity-10 text-<?= e($color) ?> flex-shrink-0">
                    <i class="fa-solid <?= e($icon) ?> fa-xs"></i>
                </div>
                <div class="flex-fill min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-semibold small text-truncate">
                            <?= $isSecurityEvent && !empty($log['detail']) ? e($log['detail']) : e($log['user_name']) ?>
                        </span>
                        <span class="badge bg-<?= e($color) ?> bg-opacity-10 text-<?= e($color) ?> adm-action-label">
                            <?= e($label) ?>
                        </span>
                        <?php if ($isSecurityEvent): ?>
                        <span class="badge bg-danger bg-opacity-15 text-danger adm-security-badge">
                            <i class="fa-solid fa-triangle-exclamation fa-xs me-1"></i><?= e(t('admin.actions.security_badge')) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center mt-1 gap-2">
                        <?php if (!$isSecurityEvent && $log['entity']): ?>
                            <code class="small text-muted">
                                <?= e($log['entity']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?>
                            </code>
                        <?php endif; ?>
                        <code class="small text-muted ms-auto flex-shrink-0"><?= e($log['ip'] ?? '-') ?></code>
                        <span class="small text-muted flex-shrink-0"><?= e(date('d/m H:i', strtotime($log['created_at']))) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

