<?php
/**
 * Blog admin: comment blacklist management.
 * Variables: $view, $banned, $userOptions
 */
$view->layout('main');
$view->pushStyle('css/blog.css');
$view->start('content');
?>

<div class="container-fluid">
<?php $view->include('partials/pf-hero-admin', [
    'adminIcon'  => 'fa-solid fa-ban',
    'adminTitle' => t('blog.admin.blacklist.breadcrumb'),
]); ?>

<div class="row">
    <!-- Ban form -->
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><?= e(t('blog.admin.blacklist.ban_user')) ?></div>
            <div class="card-body">
                <form method="post" action="<?= e(route('blog.admin.blacklist.ban')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.admin.blacklist.user_label')) ?> <span class="text-danger">*</span></label>
                        <input type="search"
                               class="form-control form-control-sm mb-2"
                               placeholder="<?= e(t('blog.admin.blacklist.user_filter_placeholder')) ?>"
                               data-app-filter-select="bl-blacklist-user-select">
                        <select id="bl-blacklist-user-select" name="user_id" class="form-select" required>
                            <option value=""><?= e(t('blog.admin.blacklist.user_select_placeholder')) ?></option>
                            <?php foreach (($userOptions ?? []) as $u): ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    <?= e($u['name'] ?? t('blog.admin.blacklist.user_id_fallback', ['id' => (int) $u['id']])) ?>
                                    <?php if (!empty($u['email'])): ?>
                                        — <?= e($u['email']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            <?= e(t('blog.admin.blacklist.user_not_found')) ?> <a href="<?= e(route('admin.users.index')) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('blog.admin.blacklist.open_user_management')) ?></a>.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= e(t('blog.admin.blacklist.reason_label')) ?></label>
                        <textarea name="reason" rows="2" class="form-control" maxlength="500"
                                  placeholder="<?= e(t('blog.admin.blacklist.reason_placeholder')) ?>"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm w-100">
                        <i class="fa-solid fa-user-slash me-1"></i> <?= e(t('blog.admin.blacklist.ban_button')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($banned)): ?>
                <div class="text-center text-muted py-5"><?= e(t('blog.admin.blacklist.empty')) ?></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th><?= e(t('blog.admin.blacklist.user_label')) ?></th>
                                <th><?= e(t('blog.admin.blacklist.reason_label')) ?></th>
                                <th><?= e(t('blog.admin.blacklist.banned_by')) ?></th>
                                <th><?= e(t('blog.admin.blacklist.date')) ?></th>
                                <th class="text-end"><?= e(t('blog.author.col_actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banned as $b): ?>
                            <tr>
                                <td>
                                    <strong><?= e($b['user_name'] ?? t('blog.admin.blacklist.user_id_fallback', ['id' => $b['user_id']])) ?></strong>
                                    <?php if (!empty($b['user_email'])): ?>
                                        <br><small class="text-muted"><?= e($b['user_email']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= e($b['reason'] ?? '—') ?></small></td>
                                <td><small><?= e($b['banned_by_name'] ?? '—') ?></small></td>
                                <td><small><?= e(format_date($b['created_at'], 'compact')) ?></small></td>
                                <td class="text-end">
                                    <form method="post"
                                          action="<?= e(route('blog.admin.blacklist.unban', ['userId' => $b['user_id']])) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-outline-success" title="<?= e(t('blog.admin.blacklist.remove_ban')) ?>"
                                                data-app-confirm="<?= e(t('blog.admin.blacklist.remove_ban_confirm')) ?>" data-app-confirm-label="<?= e(t('blog.admin.blacklist.remove')) ?>">
                                            <i class="fa-solid fa-user-check"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>

<?php $view->end(); ?>
