<?php
/**
 * Lista membri di un gruppo, suddivisa in sezioni Admin / Membri.
 *
 * Usato come:
 *  - inner content del tab Membri (#tm-member-list) nell'offcanvas group_panel
 *  - target HTMX di teams.members.store e teams.members.destroy
 *
 * Per il toolbar (filter input + add-member collapse) vedi group_panel/tab_members.php.
 *
 * @var array $conv
 * @var array $members
 * @var int   $currentUserId
 */
use App\Modules\Auth\Helpers\AvatarHelper;

$isAdmin = ($conv['my_role'] ?? '') === 'admin' || has_permission('teams.admin');

$admins  = [];
$regular = [];
foreach ($members as $m) {
    if (($m['role'] ?? 'member') === 'admin') {
        $admins[] = $m;
    } else {
        $regular[] = $m;
    }
}

$renderRow = function (array $m) use ($conv, $currentUserId, $isAdmin): void {
    $isMe = (int) $m['id'] === $currentUserId;
    $avatarUrl = AvatarHelper::url($m['avatar_path'] ?? null);
    $initials  = AvatarHelper::initials($m['name'] ?? 'U');
    $joinedAt  = isset($m['joined_at']) ? format_date((string) $m['joined_at'], 'relative') : '';
    $rowIsAdmin = ($m['role'] ?? 'member') === 'admin';
    ?>
    <div class="tm-gp-member-row"
         data-name="<?= e(strtolower((string) ($m['name'] ?? ''))) ?>"
         data-email="<?= e(strtolower((string) ($m['email'] ?? ''))) ?>">
        <div class="tm-gp-member-avatar">
            <?php if ($avatarUrl): ?>
                <img src="<?= e($avatarUrl) ?>" alt="" class="tm-avatar-img-md">
            <?php else: ?>
                <span class="tm-avatar-initials-md"
                      style="--tm-avatar-hue: <?= (int) (crc32((string) ($m['name'] ?? 'U')) % 360) ?>">
                    <?= e($initials) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="tm-gp-member-info">
            <div class="tm-gp-member-name">
                <?= e($m['name'] ?? t('teams.exception.default_user_name')) ?>
                <?php if ($isMe): ?><span class="text-muted small"><?= e(t('teams.group_panel.you_suffix')) ?></span><?php endif; ?>
            </div>
            <div class="tm-gp-member-meta">
                <?php if ($rowIsAdmin): ?><i class="fa-solid fa-shield-halved me-1" title="<?= e(t('teams.group_panel.admin_role')) ?>"></i><?php endif; ?>
                <?= e($m['email'] ?? '') ?>
                <?php if ($joinedAt !== ''): ?>
                    <span class="tm-gp-meta-sep">·</span>
                    <span title="<?= e(t('teams.group_panel.joined_on_tip', ['date' => $joinedAt])) ?>"><?= e(t('teams.group_panel.joined_since', ['date' => $joinedAt])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isAdmin && !$isMe): ?>
            <button type="button"
                    class="tm-gp-member-remove btn btn-link btn-sm text-danger p-1"
                    hx-delete="<?= e(route('teams.members.destroy', ['id' => $conv['id'], 'userId' => $m['id']])) ?>"
                    hx-target="#tm-member-list"
                    hx-swap="innerHTML"
                    data-app-confirm="<?= e(t('teams.group_panel.confirm_remove_member', ['name' => $m['name'] ?? t('teams.group_panel.this_member_fallback')])) ?>"
                    data-app-confirm-label="<?= e(t('teams.group_panel.remove_btn')) ?>"
                    title="<?= e(t('teams.group_panel.remove_from_group_tip')) ?>">
                <i class="fa-solid fa-xmark"></i>
            </button>
        <?php endif; ?>
    </div>
    <?php
};
?>
<?php if (!empty($admins)): ?>
    <div class="tm-gp-member-section-title">
        <?= e(t('teams.group_panel.admins_section', ['count' => count($admins)])) ?>
    </div>
    <?php foreach ($admins as $m) {
        $renderRow($m);
    } ?>
<?php endif; ?>

<?php if (!empty($regular)): ?>
    <div class="tm-gp-member-section-title">
        <?= e(t('teams.group_panel.members_section', ['count' => count($regular)])) ?>
    </div>
    <?php foreach ($regular as $m) {
        $renderRow($m);
    } ?>
<?php endif; ?>

<?php if (empty($members)): ?>
    <div class="tm-gp-empty">
        <i class="fa-solid fa-users-slash"></i>
        <p><?= e(t('teams.group_panel.no_members')) ?></p>
    </div>
<?php endif; ?>
