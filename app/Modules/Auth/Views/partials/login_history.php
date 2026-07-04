<?php
/**
 * Profile login history partial (HTMX).
 * Variables: $attempts (array of login_attempts rows)
 */
?>

<?php if (empty($attempts)): ?>
    <div class="text-center text-muted py-4">
        <i class="fa-regular fa-clock fa-2x mb-2 d-block"></i>
        <?= e(t('auth.login_history.empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(t('auth.login_history.col_datetime')) ?></th>
                    <th><?= e(t('auth.login_history.col_ip')) ?></th>
                    <th><?= e(t('auth.login_history.col_outcome')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $a): ?>
                    <tr>
                        <td>
                            <span data-bs-toggle="tooltip" title="<?= e(date('d/m/Y H:i:s', strtotime($a['created_at']))) ?>">
                                <?= format_date_it($a['created_at'], 'relative') ?>
                            </span>
                        </td>
                        <td>
                            <code class="text-muted"><?= e($a['ip_address']) ?></code>
                        </td>
                        <td>
                            <?php if ($a['success']): ?>
                                <span class="badge bg-success-subtle text-success">
                                    <i class="fa-solid fa-circle-check me-1"></i><?= e(t('auth.login_history.success')) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger">
                                    <i class="fa-solid fa-circle-xmark me-1"></i><?= e(t('auth.login_history.failed')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
