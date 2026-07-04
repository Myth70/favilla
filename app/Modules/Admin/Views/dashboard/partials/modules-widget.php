<?php
$enabledCount  = count(array_filter($moduleStatus, fn($m) => $m['enabled']));
$disabledCount = count($moduleStatus) - $enabledCount;
?>
<?php if (empty($moduleStatus)): ?>
    <p class="text-muted text-center py-3 mb-0"><?= e(t('admin.modules_widget.empty')) ?></p>
<?php else: ?>
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($moduleStatus as $mod): ?>
            <?php
            $bgClass = $mod['enabled'] ? 'bg-success' : 'bg-danger';
            $tipText = e($mod['name'])
                . ($mod['enabled'] ? e(t('admin.modules_widget.active_suffix')) : e(t('admin.modules_widget.inactive_suffix')))
                . (($mod['testing'] ?? false) ? e(t('admin.modules_widget.testing_suffix')) : '');
            ?>
            <span class="badge <?= $bgClass ?> adm-mod-pill"
                  data-bs-toggle="tooltip"
                  data-bs-placement="top"
                  title="<?= $tipText ?>">
                <?= e($mod['name']) ?>
                <?php if ($mod['new_count'] > 0): ?>
                    <span class="ms-1 badge bg-white text-dark"><?= (int) $mod['new_count'] ?></span>
                <?php endif; ?>
                <?php if ($mod['testing'] ?? false): ?>
                    <i class="fa-solid fa-flask fa-xs ms-1"></i>
                <?php endif; ?>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="d-flex gap-3 mt-2 pt-2 border-top small text-muted">
        <span>
            <i class="fa-solid fa-circle text-success adm-dot-icon me-1"></i><?= e(t('admin.modules_widget.enabled_count', ['count' => $enabledCount])) ?>
        </span>
        <?php if ($disabledCount > 0): ?>
        <span>
            <i class="fa-solid fa-circle text-danger adm-dot-icon me-1"></i><?= e(t('admin.modules_widget.disabled_count', ['count' => $disabledCount])) ?>
        </span>
        <?php endif; ?>
    </div>
<?php endif; ?>
