<?php
/**
 * Profile activity — compact list (HTMX partial).
 * Variables: $activities (array of audit_log rows with 'meta' key)
 */
?>

<?php if (empty($activities)): ?>
    <div class="text-center text-muted py-4">
        <i class="fa-regular fa-clock fa-2x mb-2 d-block"></i>
        <?= e(t('auth.widget.activity_empty')) ?>
    </div>
<?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($activities as $activity): ?>
            <?php $meta = $activity['meta']; ?>
            <div class="list-group-item px-3 py-2 d-flex align-items-center gap-2 border-0 pf-activity-row">
                <span class="pf-activity-dot bg-<?= $meta['color'] ?>-subtle text-<?= $meta['color'] ?>">
                    <i class="<?= $meta['icon'] ?> pf-activity-icon"></i>
                </span>
                <span class="pf-activity-label flex-grow-1"><?= e($meta['label']) ?></span>
                <small class="text-muted text-nowrap"><?= format_date_it($activity['created_at'], 'relative') ?></small>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
