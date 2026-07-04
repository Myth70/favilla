<?php
/**
 * HTMX partial: role assignment for a user.
 * Variables: $profileUser, $allRoles, $userRoleIds (from show) OR $roleIds (from updateRoles)
 * Supports both variable names for compatibility.
 */
$currentRoleIds = $userRoleIds ?? $roleIds ?? [];
$canEdit = has_permission('admin.users.edit');
?>

<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-user-tag me-1"></i> <?= e(t('admin.users.assigned_roles')) ?>
    </div>
    <div class="card-body p-2">
        <?php if ($canEdit): ?>
        <form hx-post="<?= e(route('admin.users.roles.update', ['id' => $profileUser['id']])) ?>"
              hx-target="#roles-card"
              hx-swap="innerHTML">
            <?= csrf_field() ?>
        <?php endif; ?>

            <?php if (empty($allRoles)): ?>
            <p class="text-muted"><?= e(t('admin.users.no_roles_avail')) ?></p>
            <?php else: ?>
            <div class="row g-2 mb-3">
                <?php foreach ($allRoles as $role): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox"
                               name="role_ids[]"
                               value="<?= e($role['id']) ?>"
                               id="role_cb_<?= e($role['id']) ?>"
                               <?= in_array((int) $role['id'], array_map('intval', $currentRoleIds), true) ? 'checked' : '' ?>
                               <?= !$canEdit ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="role_cb_<?= e($role['id']) ?>">
                            <?= e($role['name']) ?>
                            <?php if (!empty($role['description'])): ?>
                                <small class="text-muted d-block"><?= e($role['description']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($canEdit): ?>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-floppy-disk me-1"></i> <?= e(t('admin.users.save_roles')) ?>
            </button>
            <?php endif; ?>

        <?php if ($canEdit): ?>
        </form>
        <?php endif; ?>
    </div>
</div>
