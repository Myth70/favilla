<?php
/**
 * Form invio notifica manuale da Admin.
 * Variables: $users, $roles, $errors, $old, $preselect, $activeChannels, $returnUrl
 */
$view->layout('main');
$view->pushStyle('css/admin.css');
$view->pushStyle('css/nt-send.css');
$view->start('content');

$sendMode     = $old['send_mode'] ?? 'user';
$selectedType = $old['type'] ?? 'info';
$selectedIcon = $old['icon'] ?? '';
$activeChannels = $activeChannels ?? [];
$typesMeta = [
    'info'    => ['label' => t('notifications.send.type_info'),    'color' => '#2563EB', 'icon' => 'fa-solid fa-circle-info'],
    'success' => ['label' => t('notifications.send.type_success'), 'color' => '#16A34A', 'icon' => 'fa-solid fa-circle-check'],
    'warning' => ['label' => t('notifications.send.type_warning'), 'color' => '#CA8A04', 'icon' => 'fa-solid fa-triangle-exclamation'],
    'danger'  => ['label' => t('notifications.send.type_danger'),  'color' => '#DC2626', 'icon' => 'fa-solid fa-circle-exclamation'],
];
?>

<div class="container-fluid">
    <?php $view->include('partials/pf-hero-admin', [
        'adminIcon'  => 'fa-solid fa-paper-plane',
        'adminTitle' => t('notifications.send.title'),
    ]); ?>

    <form method="POST" action="<?= e(route('admin.notifications.store')) ?>" id="nt-send-form">
        <?= csrf_field() ?>

        <div class="row g-4">
            <!-- LEFT: Form -->
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body">

                        <!-- Destinatario -->
                        <fieldset class="mb-4">
                            <legend class="fs-6 fw-semibold mb-2"><i class="fa-solid fa-user-tag me-1 text-muted"></i><?= e(t('notifications.send.recipient')) ?></legend>

                            <div class="btn-group btn-group-sm w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="send_mode" id="send_mode_user"
                                       value="user" <?= $sendMode === 'user' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary" for="send_mode_user">
                                    <i class="fa-solid fa-user me-1"></i><?= e(t('notifications.send.single_user')) ?>
                                </label>
                                <input type="radio" class="btn-check" name="send_mode" id="send_mode_role"
                                       value="role" <?= $sendMode === 'role' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-secondary" for="send_mode_role">
                                    <i class="fa-solid fa-users me-1"></i><?= e(t('notifications.send.role')) ?>
                                </label>
                            </div>

                            <div class="<?= $sendMode === 'role' ? 'd-none' : '' ?>" id="nt-send-user-group">
                                <select name="user_id" id="user_id"
                                        class="form-select <?= !empty($errors['user_id']) ? 'is-invalid' : '' ?>">
                                    <option value=""><?= e(t('notifications.send.select_user')) ?></option>
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>"
                                            <?= ((int)($old['user_id'] ?? $preselect) === (int)$u['id']) ? 'selected' : '' ?>>
                                        <?= e($u['name']) ?> (<?= e($u['email']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['user_id'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['user_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="<?= $sendMode !== 'role' ? 'd-none' : '' ?>" id="nt-send-role-group">
                                <select name="role_slug" id="role_slug"
                                        class="form-select <?= !empty($errors['role_slug']) ? 'is-invalid' : '' ?>">
                                    <option value=""><?= e(t('notifications.send.select_role')) ?></option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= e($role['slug']) ?>"
                                            <?= (($old['role_slug'] ?? '') === $role['slug']) ? 'selected' : '' ?>>
                                        <?= e($role['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!empty($errors['role_slug'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['role_slug']) ?></div>
                                <?php endif; ?>
                            </div>
                        </fieldset>

                        <!-- Tipo + Icona -->
                        <fieldset class="mb-4">
                            <legend class="fs-6 fw-semibold mb-2"><i class="fa-solid fa-palette me-1 text-muted"></i><?= e(t('notifications.send.appearance')) ?></legend>
                            <div class="row g-3">
                                <div class="col-sm-8">
                                    <label class="form-label small text-muted"><?= e(t('notifications.send.type_label')) ?></label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php foreach ($typesMeta as $val => $meta): ?>
                                        <label class="nt-send-type-label <?= $selectedType === $val ? 'is-active' : '' ?>"
                                               style="--type-color: <?= e($meta['color']) ?>">
                                            <input type="radio" name="type" value="<?= e($val) ?>"
                                                   <?= $selectedType === $val ? 'checked' : '' ?> class="nt-send-type-radio visually-hidden">
                                            <i class="<?= e($meta['icon']) ?> me-1"></i><?= e($meta['label']) ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <label for="icon" class="form-label small text-muted"><?= e(t('notifications.send.custom_icon')) ?></label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text" id="nt-send-icon-preview">
                                            <i class="<?= e($selectedIcon ?: 'fa-solid fa-bell') ?>"></i>
                                        </span>
                                        <input type="text" name="icon" id="icon"
                                               class="form-control font-monospace"
                                               value="<?= e($selectedIcon) ?>"
                                               placeholder="fa-solid fa-...">
                                    </div>
                                    <div class="form-text"><?= e(t('notifications.send.icon_empty_hint')) ?></div>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Contenuto -->
                        <fieldset class="mb-4">
                            <legend class="fs-6 fw-semibold mb-2"><i class="fa-solid fa-pen me-1 text-muted"></i><?= e(t('notifications.send.content')) ?></legend>

                            <div class="mb-3">
                                <label for="title" class="form-label small text-muted"><?= e(t('notifications.send.title_field')) ?> <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title"
                                       class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($old['title'] ?? '') ?>"
                                       maxlength="255" required
                                       placeholder="<?= e(t('notifications.send.title_ph')) ?>">
                                <?php if (!empty($errors['title'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="body" class="form-label small text-muted"><?= e(t('notifications.send.message')) ?></label>
                                <textarea name="body" id="body" rows="4"
                                          class="form-control"
                                          placeholder="<?= e(t('notifications.send.message_ph')) ?>"><?= e($old['body'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-0">
                                <label for="link" class="form-label small text-muted"><?= e(t('notifications.send.link')) ?></label>
                                <input type="text" name="link" id="link"
                                       class="form-control <?= !empty($errors['link']) ? 'is-invalid' : '' ?>"
                                       value="<?= e($old['link'] ?? '') ?>"
                                       placeholder="<?= e(t('notifications.send.link_ph')) ?>">
                                <?php if (!empty($errors['link'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['link']) ?></div>
                                <?php endif; ?>
                            </div>
                        </fieldset>

                        <!-- Azioni -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane me-1"></i><?= e(t('notifications.send.submit')) ?>
                            </button>
                            <a href="<?= e($returnUrl ?? route('admin.users.index')) ?>" class="btn btn-outline-secondary">
                                <?= e(t('notifications.send.cancel')) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Preview + Channel info -->
            <div class="col-lg-5">
                <!-- Canali attivi -->
                <div class="card shadow-sm mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="fa-solid fa-tower-broadcast text-muted"></i>
                            <span class="fw-semibold small"><?= e(t('notifications.send.channels')) ?></span>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php
                            $channelMeta = [
                                'in_app'   => ['label' => t('notifications.send.ch_in_app'),   'icon' => 'fa-solid fa-bell'],
                                'email'    => ['label' => t('notifications.send.ch_email'),    'icon' => 'fa-solid fa-envelope'],
                                'telegram' => ['label' => t('notifications.send.ch_telegram'), 'icon' => 'fa-brands fa-telegram'],
                            ];
                            foreach ($channelMeta as $chSlug => $chMeta):
                                $isOn = $activeChannels[$chSlug] ?? false;
                            ?>
                            <span class="badge nt-send-ch-badge <?= $isOn ? 'is-on' : 'is-off' ?>">
                                <i class="<?= e($chMeta['icon']) ?> me-1"></i><?= e($chMeta['label']) ?>
                                <?php if ($isOn): ?>
                                    <i class="fa-solid fa-check ms-1"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-minus ms-1"></i>
                                <?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text mt-2 mb-0">
                            <?= e(t('notifications.send.channels_hint_pre')) ?> <code>system.direct_send</code> <?= e(t('notifications.send.channels_hint_post')) ?>
                            <a href="<?= e(route('admin.notifications.settings')) ?>"><?= e(t('notifications.send.dispatcher')) ?></a>.
                        </div>
                    </div>
                </div>

                <!-- Anteprima live -->
                <div class="card shadow-sm">
                    <div class="card-header py-2">
                        <i class="fa-solid fa-eye me-1 text-muted"></i>
                        <span class="fw-semibold small"><?= e(t('notifications.send.preview')) ?></span>
                    </div>
                    <div class="card-body" id="nt-send-preview">
                        <div class="nt-item nt-unread nt-send-preview-item" id="nt-send-preview-card">
                            <div class="nt-indicator nt-info" id="nt-send-preview-indicator"></div>
                            <div class="nt-type-icon nt-info" id="nt-send-preview-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="nt-item-body">
                                <div class="nt-item-title nt-item-title-full" id="nt-send-preview-title"><?= e(t('notifications.send.preview_title')) ?></div>
                                <div class="nt-item-text nt-item-text-full" id="nt-send-preview-body"><?= e(t('notifications.send.preview_body')) ?></div>
                                <div class="nt-item-meta">
                                    <span><?= e(t('notifications.send.preview_now')) ?></span>
                                    <span class="d-none" id="nt-send-preview-link">
                                        <i class="fa-solid fa-arrow-up-right-from-square fa-xs"></i> <?= e(t('notifications.send.preview_link')) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="application/json" id="nt-send-types-meta"><?= json_encode($typesMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<?php $view->pushScript('js/nt-send.js'); ?>
<?php $view->end(); ?>
