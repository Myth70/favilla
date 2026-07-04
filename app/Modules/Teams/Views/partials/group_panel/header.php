<?php
/**
 * Hero dell'offcanvas: avatar grande + nome + meta + quick actions
 * (mute, cerca in chat, pinned, modifica) + form collapse rinomina/avatar.
 *
 * @var array $info       output di GroupPanelService::getHeaderData
 * @var array $conv       conversazione corrente (per id e my_role)
 * @var bool  $canManage  true se admin gruppo o teams.admin
 */
use App\Modules\Auth\Helpers\AvatarHelper;

$avatarUrl = AvatarHelper::url($info['avatar_path'] ?? null);
$initials  = AvatarHelper::initials($info['name'] ?? t('teams.exception.default_group_name'));
$memberCount  = (int) ($info['member_count'] ?? 0);
$messageCount = (int) ($info['message_count'] ?? 0);
$createdAt    = (string) ($info['created_at'] ?? '');
$creator      = (string) ($info['creator_name'] ?? '');
$isMuted      = !empty($conv['notifications_muted']);
?>
<div class="tm-gp-hero">
    <div class="tm-gp-hero-avatar">
        <?php if ($avatarUrl): ?>
            <img src="<?= e($avatarUrl) ?>" alt="" class="tm-avatar-img-lg" id="tm-group-avatar-preview">
        <?php else: ?>
            <span class="tm-avatar-initials-lg tm-avatar-initials-group"
                  id="tm-group-avatar-preview"
                  style="--tm-avatar-hue: <?= (int) (crc32($info['name'] ?? t('teams.exception.default_group_name')) % 360) ?>">
                <?= e($initials) ?>
            </span>
        <?php endif; ?>
    </div>
    <h5 class="tm-gp-hero-name"><?= e($info['name'] ?? t('teams.exception.default_group_name')) ?></h5>
    <div class="tm-gp-hero-meta">
        <span><i class="fa-solid fa-users me-1"></i><?= e(tc('teams.chat_panel.members_count', $memberCount)) ?></span>
        <span class="tm-gp-meta-sep">·</span>
        <span><i class="fa-regular fa-message me-1"></i><?= e(tc('teams.group_panel.messages_count', $messageCount)) ?></span>
    </div>
    <?php if (!empty($info['description'])): ?>
        <p class="tm-gp-hero-desc"><?= nl2br(e($info['description'])) ?></p>
    <?php endif; ?>
    <?php if ($createdAt !== ''): ?>
        <small class="tm-gp-hero-created">
            <i class="fa-regular fa-calendar me-1"></i>
            <?= e(t('teams.group_panel.created_on', ['date' => format_date($createdAt, 'relative')])) ?>
            <?php if ($creator !== ''): ?>
                <?= e(t('teams.group_panel.by_prefix')) ?> <strong><?= e($creator) ?></strong>
            <?php endif; ?>
        </small>
    <?php endif; ?>
</div>

<div class="tm-gp-quick-actions">
    <span id="tm-mute-btn" class="tm-gp-quick-slot">
        <?php $view->include('Teams/Views/partials/group_panel/mute_button_quick', [
            'convId'  => (int) $conv['id'],
            'isMuted' => $isMuted,
        ]); ?>
    </span>
    <button class="tm-gp-quick-btn" type="button"
            data-bs-toggle="collapse" data-bs-target="#tm-gp-search-collapse"
            aria-expanded="false" aria-controls="tm-gp-search-collapse"
            title="<?= e(t('teams.group_panel.search_messages_tip')) ?>">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span class="tm-gp-quick-label"><?= e(t('teams.group_panel.search_label')) ?></span>
    </button>
    <button class="tm-gp-quick-btn" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#tm-pinned-panel"
            hx-get="<?= e(route('teams.pinned.list', ['id' => $conv['id']])) ?>"
            hx-target="#tm-pinned-panel-body"
            hx-trigger="click, teamsPinnedRefresh from:body"
            title="<?= e(t('teams.index.pinned_panel_title')) ?>">
        <i class="fa-solid fa-thumbtack"></i>
        <span class="tm-gp-quick-label"><?= e(t('teams.group_panel.pinned_label')) ?></span>
    </button>
    <?php if ($canManage): ?>
    <button class="tm-gp-quick-btn" type="button"
            data-bs-toggle="collapse" data-bs-target="#tm-gp-edit-collapse"
            aria-expanded="false" aria-controls="tm-gp-edit-collapse"
            title="<?= e(t('teams.group_panel.edit_group_tip')) ?>">
        <i class="fa-solid fa-pen"></i>
        <span class="tm-gp-quick-label"><?= e(t('teams.message.edit_btn')) ?></span>
    </button>
    <?php else: ?>
    <span class="tm-gp-quick-slot tm-gp-quick-placeholder" aria-hidden="true"></span>
    <?php endif; ?>
</div>

<!-- Cerca in chat (collapse) -->
<div class="collapse tm-gp-collapse-panel" id="tm-gp-search-collapse">
    <div class="tm-gp-collapse-inner">
        <input type="text" class="form-control form-control-sm" placeholder="<?= e(t('teams.index.search_in_messages_placeholder')) ?>"
               name="q" autocomplete="off"
               hx-get="<?= e(route('teams.messages.search', ['id' => $conv['id']])) ?>"
               hx-trigger="keyup changed delay:400ms"
               hx-target="#tm-gp-search-results">
        <div id="tm-gp-search-results" class="tm-gp-search-results mt-2"></div>
    </div>
</div>

<?php if ($canManage): ?>
<!-- Edit collapse (admin): rinomina + avatar -->
<div class="collapse tm-gp-collapse-panel" id="tm-gp-edit-collapse">
    <div class="tm-gp-collapse-inner">
        <div id="tm-group-cropper-config"
             data-crop-url="<?= e(route('api.avatar.crop')) ?>"
             data-context="team"
             data-context-id="<?= e($conv['id']) ?>"
             class="d-none"></div>
        <label class="form-label small text-muted mb-1"><?= e(t('teams.group_panel.avatar_label')) ?></label>
        <div class="input-group input-group-sm mb-2">
            <input type="file" class="form-control form-control-sm" id="tm-group-avatar-input" accept="image/jpeg,image/png,image/gif,image/webp">
            <button type="button" class="btn btn-outline-primary btn-sm" id="tm-group-avatar-btn" title="<?= e(t('teams.group_panel.crop_upload_tip')) ?>">
                <i class="fa-solid fa-crop-simple"></i>
            </button>
        </div>

        <form action="<?= e(route('teams.conversations.update', ['id' => $conv['id']])) ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="_method" value="PUT">
            <label class="form-label small text-muted mb-1"><?= e(t('teams.index.group_name_label')) ?></label>
            <input type="text" class="form-control form-control-sm mb-2" name="name"
                   value="<?= e($conv['name'] ?? '') ?>" required maxlength="255">
            <label class="form-label small text-muted mb-1"><?= e(t('teams.index.description_label')) ?></label>
            <textarea class="form-control form-control-sm mb-2" name="description"
                      rows="2" maxlength="500"
                      placeholder="<?= e(t('teams.group_panel.group_purpose_placeholder')) ?>"><?= e($conv['description'] ?? '') ?></textarea>
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="fa-solid fa-check me-1"></i><?= e(t('teams.group_panel.save_changes_btn')) ?>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
