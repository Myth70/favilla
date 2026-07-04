<?php
/**
 * Dashboard partial - Sessioni online in tempo reale.
 * Variables: $onlineSessions (array from AdminDashboardService::getOnlineSessions())
 * Usato sia in index.php che via HTMX (admin.dashboard.online).
 */
use App\Modules\Auth\Helpers\AvatarHelper;
?>
<?php if (empty($onlineSessions)): ?>
<div class="text-center text-muted py-3">
    <i class="fa-solid fa-moon fa-sm mb-1 d-block opacity-50"></i>
    <span class="adm-online-empty-label"><?= e(t('admin.online.empty')) ?></span>
</div>
<?php else: ?>
<ul class="list-unstyled mb-0">
    <?php foreach ($onlineSessions as $sess):
        $avatarUrl = AvatarHelper::url($sess['avatar_path'] ?? null);
        $initials  = mb_strtoupper(mb_substr($sess['name'], 0, 1));
    ?>
    <li class="adm-online-item d-flex align-items-center gap-2 px-2 py-2 rounded">
        <div class="adm-online-avatar flex-shrink-0">
            <?php if ($avatarUrl): ?>
            <img src="<?= e($avatarUrl) ?>" alt="" class="adm-online-avatar-img">
            <?php else: ?>
            <div class="adm-online-avatar-initials"><?= e($initials) ?></div>
            <?php endif; ?>
            <span class="adm-online-dot"></span>
        </div>
        <div class="flex-fill adm-online-min-w-0">
            <div class="small fw-semibold text-truncate lh-1"><?= e($sess['name']) ?></div>
            <div class="text-muted mt-1 adm-online-meta-text"><?= e($sess['ip'] ?? '-') ?></div>
        </div>
        <div class="text-muted flex-shrink-0 adm-online-meta-text"
             data-bs-toggle="tooltip" title="<?= e(t('admin.online.last_activity_tip', ['time' => $sess['last_activity']])) ?>">
            <?= format_date($sess['last_activity'], 'relative') ?>
        </div>
    </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

