<?php
/**
 * Wrapper offcanvas "Info gruppo" stile Telegram.
 *
 * Trigger esterno (chat_panel.php header): data-bs-target="#tm-group-panel".
 * Sezioni: hero + quick actions + edit collapse + tab navigation + danger zone.
 *
 * Variabili attese:
 *   @var array $conv          la conversazione (output di TeamsService::getConversationData)
 *   @var array $members       lista membri attivi
 *   @var int   $currentUserId id dell'utente corrente
 *   @var array $headerInfo    output di GroupPanelService::getHeaderData (incluso in getConversationData)
 */
$canManage = ($conv['my_role'] ?? '') === 'admin' || has_permission('teams.admin');
?>
<div class="offcanvas offcanvas-end tm-group-panel"
     tabindex="-1"
     id="tm-group-panel"
     aria-labelledby="tm-group-panel-title"
     data-bs-scroll="true"
     data-conversation-id="<?= (int) $conv['id'] ?>">
    <div class="offcanvas-header tm-gp-close-header">
        <h6 class="visually-hidden" id="tm-group-panel-title"><?= e(t('teams.group_panel.info_title')) ?></h6>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="<?= e(t('teams.index.close')) ?>"></button>
    </div>

    <div class="offcanvas-body p-0">
        <?php $view->include('Teams/Views/partials/group_panel/header', [
            'info'      => $headerInfo,
            'conv'      => $conv,
            'canManage' => $canManage,
        ]); ?>

        <?php $view->include('Teams/Views/partials/group_panel/tabs', [
            'conv'          => $conv,
            'members'       => $members,
            'currentUserId' => $currentUserId,
            'canManage'     => $canManage,
        ]); ?>

        <div class="tm-gp-danger-zone">
            <?php if ($canManage): ?>
            <form action="<?= e(route('teams.conversations.archive', ['id' => $conv['id']])) ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-warning btn-sm w-100"
                        data-app-confirm="<?= e(t('teams.group_panel.confirm_archive')) ?>"
                        data-app-confirm-label="<?= e(t('teams.group_panel.archive_confirm_label')) ?>"
                        data-app-confirm-class="btn-warning">
                    <i class="fa-solid fa-box-archive me-1"></i><?= e(t('teams.group_panel.archive_group_btn')) ?>
                </button>
            </form>
            <?php endif; ?>
            <form action="<?= e(route('teams.conversations.leave', ['id' => $conv['id']])) ?>" method="POST" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm w-100"
                        data-app-confirm="<?= e(t('teams.group_panel.confirm_leave')) ?>"
                        data-app-confirm-label="<?= e(t('teams.group_panel.leave_group_btn')) ?>">
                    <i class="fa-solid fa-right-from-bracket me-1"></i><?= e(t('teams.group_panel.leave_group_btn')) ?>
                </button>
            </form>
        </div>
    </div>
</div>
