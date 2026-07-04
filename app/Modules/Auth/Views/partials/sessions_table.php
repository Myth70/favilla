<?php
/**
 * Profile active sessions partial (HTMX).
 * Variables: $sessions, $currentSessionId
 */
?>

<?php if (empty($sessions)): ?>
    <div class="text-center text-muted py-4">
        <i class="fa-regular fa-window-maximize fa-2x mb-2 d-block"></i>
        <?= e(t('auth.sessions.empty')) ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= e(t('auth.sessions.col_browser')) ?></th>
                    <th><?= e(t('auth.sessions.col_ip')) ?></th>
                    <th><?= e(t('auth.sessions.col_last_activity')) ?></th>
                    <th class="text-end"><?= e(t('common.label.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $s): ?>
                    <?php
                    $isCurrent = ((int)$s['id'] === (int)$currentSessionId);
                    $ua = $s['parsed_ua'];
                    ?>
                    <tr class="<?= $isCurrent ? 'pf-session-current' : '' ?>">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="<?= e($ua['browser_icon']) ?> fa-lg text-muted"></i>
                                <div>
                                    <div class="fw-medium"><?= e($ua['browser']) ?></div>
                                    <?php if ($ua['os']): ?>
                                        <small class="text-muted"><?= e($ua['os']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isCurrent): ?>
                                    <span class="badge bg-success-subtle text-success ms-1"><?= e(t('auth.sessions.current')) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <code class="text-muted"><?= e($s['ip'] ?? '—') ?></code>
                        </td>
                        <td>
                            <?= format_date_it($s['last_activity'], 'relative') ?>
                        </td>
                        <td class="text-end">
                            <?php if (!$isCurrent): ?>
                                <form method="POST"
                                      action="<?= e(route('profile.sessions.revoke', ['id' => $s['id']])) ?>"
                                      class="d-inline"
                                      hx-post="<?= e(route('profile.sessions.revoke', ['id' => $s['id']])) ?>"
                                      hx-target="#pf-sessions-body"
                                      hx-swap="innerHTML"
                                      hx-headers='<?= json_encode(['X-CSRF-TOKEN' => csrf_token()]) ?>'>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                            data-app-confirm="<?= e(t('auth.sessions.revoke_confirm')) ?>"
                                            data-app-confirm-label="<?= e(t('auth.sessions.revoke')) ?>"
                                            data-app-confirm-class="btn-danger">
                                        <i class="fa-solid fa-plug-circle-xmark me-1"></i><?= e(t('auth.sessions.revoke')) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
