<?php
/**
 * Edit history partial — shows chronological edits of a message.
 * Variables: $msg, $edits
 */
?>
<?php if (empty($edits)): ?>
    <p class="text-muted text-center py-3"><?= e(t('teams.edit_history.no_edits')) ?></p>
<?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($edits as $i => $edit): ?>
        <div class="list-group-item px-0">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <small class="text-muted">
                    <i class="fa-solid fa-user me-1"></i><?= e($edit['editor_name'] ?? t('teams.exception.default_user_name')) ?>
                </small>
                <small class="text-muted"><?= e(format_date($edit['edited_at'], 'short')) ?></small>
            </div>
            <div class="small text-body-secondary tm-prewrap"><?= e($edit['old_body']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="border-top pt-2 mt-2">
        <small class="text-muted fw-medium"><?= e(t('teams.edit_history.current_version')) ?></small>
        <div class="small mt-1 tm-prewrap"><?= e($msg['body'] ?? '') ?></div>
    </div>
<?php endif; ?>
