<?php
$view->layout('main');
$view->pushStyle('css/cropper.min.css');
$view->pushStyle('css/avatar-cropper.css');
$view->pushStyle('css/teams.css');
$view->pushScript('js/vendor/cropper.min.js');
$view->pushScript('js/avatar-cropper.js');
$view->pushScript('js/teams.js');
?>
<?php $view->start('content'); ?>
<div class="tm-container"
     data-heartbeat-url="<?= e(route('teams.presence.heartbeat')) ?>"
     data-typing-url="<?= e(route('teams.typing')) ?>"
     data-conv-list-url="<?= e(route('teams.conversations')) ?>"
     data-search-url="<?= e(route('teams.search')) ?>"
     data-base-url="<?= e(rtrim(route('teams.index'), '/')) ?>"
     data-user-id="<?= (int) auth()['id'] ?>">

    <div class="tm-layout">
        <!-- Sidebar: lista conversazioni -->
        <div class="tm-sidebar" id="tm-sidebar">
            <div class="tm-sidebar-header">
                <h5 class="mb-0">
                    <i class="fa-solid fa-comments me-2"></i><?= e(t('teams.index.chat_title')) ?>
                </h5>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary <?= !empty($showHidden) ? 'active' : '' ?>"
                            id="tm-toggle-hidden-btn"
                            title="<?= !empty($showHidden) ? e(t('teams.index.hide_hidden_conversations')) : e(t('teams.index.show_hidden_conversations')) ?>">
                        <i class="fa-solid fa-eye<?= !empty($showHidden) ? '' : '-slash' ?>"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="tm-search-global-btn" title="<?= e(t('teams.search.page_title')) ?>">
                        <i class="fa-solid fa-search"></i>
                    </button>
                    <?php if (has_permission('teams.create')): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#tm-new-conv-modal" title="<?= e(t('teams.index.new_conversation_tip')) ?>">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ricerca conversazioni -->
            <div class="tm-sidebar-search">
                <input type="text" class="form-control form-control-sm" id="tm-conv-search"
                       placeholder="<?= e(t('teams.index.search_conversations_placeholder')) ?>"
                       name="conv_search"
                       hx-get="<?= e(route('teams.conversations')) ?>"
                       hx-trigger="keyup changed delay:400ms"
                       hx-target="#tm-conv-list">
            </div>

            <!-- Lista conversazioni (refresh on-demand: il polling /state emette
                 HX-Trigger: teamsConvRefresh quando una qualsiasi conv dell'utente
                 è cambiata; sostituisce il vecchio polling fisso every 10s). -->
            <div id="tm-conv-list"
                 class="tm-conv-list"
                 hx-get="<?= e(route('teams.conversations')) ?>"
                 hx-trigger="teamsConvRefresh from:body"
                 hx-target="#tm-conv-list"
                 hx-swap="innerHTML"
                 hx-include="#tm-conv-search">
                <?php $view->include('Teams/Views/partials/conversation_list', [
                    'conversations' => $conversations,
                    'activeId'      => $activeId ?? null,
                    'showHidden'    => $showHidden ?? false,
                ]); ?>
            </div>
        </div>

        <!-- Main: pannello chat -->
        <div class="tm-main" id="tm-chat-panel">
            <?php if (isset($searchMode) && $searchMode): ?>
                <!-- Modalita' ricerca -->
                <div class="tm-search-panel">
                    <div class="tm-search-header">
                        <h5><i class="fa-solid fa-search me-2"></i><?= e(t('teams.search.page_title')) ?></h5>
                        <a href="<?= e(route('teams.index')) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-times"></i>
                        </a>
                    </div>
                    <div class="p-3">
                        <input type="text" class="form-control" placeholder="<?= e(t('teams.index.search_in_messages_placeholder')) ?>"
                               name="q" value="<?= e($searchQuery ?? '') ?>"
                               hx-get="<?= e(route('teams.search')) ?>"
                               hx-trigger="keyup changed delay:400ms"
                               hx-target="#tm-search-results"
                               autofocus>
                    </div>
                    <div id="tm-search-results" class="tm-search-results">
                        <?php if (!empty($searchResults)): ?>
                            <?php $view->include('Teams/Views/partials/search_results', [
                                'results' => $searchResults,
                                'q'       => $searchQuery ?? '',
                            ]); ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif (isset($activeConversation) && $activeConversation): ?>
                <?php $view->include('Teams/Views/partials/chat_panel', [
                    'activeConversation' => $activeConversation,
                    'messages'           => $messages,
                    'members'            => $members,
                    'hasOlderMessages'   => $hasOlderMessages,
                    'othersMaxReadAt'    => $othersMaxReadAt ?? null,
                    'pinnedCount'        => $pinnedCount ?? 0,
                    'groupHeaderInfo'    => $groupHeaderInfo ?? [],
                ]); ?>
            <?php else: ?>
                <div class="tm-empty-state">
                    <svg class="tm-empty-illustration tm-empty-illustration-lg" viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <circle cx="80" cy="80" r="70" fill="currentColor" opacity="0.06"/>
                        <path d="M40 55 q0 -15 15 -15 h50 q15 0 15 15 v30 q0 15 -15 15 h-30 l-15 15 v-15 q-15 0 -15 -15 z" fill="currentColor" opacity="0.25"/>
                        <circle cx="65" cy="70" r="3.5" fill="#fff"/>
                        <circle cx="80" cy="70" r="3.5" fill="#fff"/>
                        <circle cx="95" cy="70" r="3.5" fill="#fff"/>
                    </svg>
                    <h5 class="tm-empty-title"><?= e(t('teams.index.welcome_title')) ?></h5>
                    <p class="text-muted mb-3"><?= e(t('teams.index.welcome_subtitle')) ?></p>
                    <?php if (has_permission('teams.create')): ?>
                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                            <button type="button" class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal" data-bs-target="#tm-new-conv-modal">
                                <i class="fa-solid fa-comment-medical me-1"></i><?= e(t('teams.index.new_chat_btn')) ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Offcanvas: Messaggi in evidenza (pinned) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="tm-pinned-panel" aria-labelledby="tm-pinned-panel-title">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="tm-pinned-panel-title">
            <i class="fa-solid fa-thumbtack me-2"></i><?= e(t('teams.index.pinned_panel_title')) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(t('teams.index.close')) ?>"></button>
    </div>
    <div class="offcanvas-body" id="tm-pinned-panel-body">
        <p class="text-muted text-center py-3">
            <span class="spinner-border spinner-border-sm me-2"></span><?= e(t('teams.index.loading')) ?>
        </p>
    </div>
</div>

<!-- Popover floating: chi ha letto il messaggio (singleton, riposizionato via JS) -->
<div id="tm-readers-popover" class="tm-readers-popover d-none" role="dialog" aria-label="<?= e(t('teams.index.readers_popover_aria')) ?>">
    <div id="tm-readers-popover-body">
        <div class="text-center text-muted py-3">
            <span class="spinner-border spinner-border-sm me-2"></span><?= e(t('teams.index.loading')) ?>
        </div>
    </div>
</div>

<!-- Modal: Nuova conversazione -->
<?php if (has_permission('teams.create')): ?>
<div class="modal fade" id="tm-new-conv-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= e(t('teams.index.new_conversation_modal_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Tab: Direct / Gruppo -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tm-tab-direct" type="button">
                            <i class="fa-solid fa-user me-1"></i><?= e(t('teams.index.tab_direct')) ?>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tm-tab-group" type="button">
                            <i class="fa-solid fa-users me-1"></i><?= e(t('teams.index.tab_group')) ?>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content: Direct -->
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tm-tab-direct">
                        <form action="<?= e(route('teams.conversations.store-direct')) ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label"><?= e(t('teams.index.search_user_label')) ?></label>
                                <input type="text" class="form-control" placeholder="<?= e(t('teams.index.search_user_placeholder')) ?>"
                                       hx-get="<?= e(route('teams.users.search')) ?>"
                                       hx-trigger="keyup changed delay:300ms"
                                       hx-target="#tm-direct-user-results"
                                       name="q" autocomplete="off">
                            </div>
                            <div id="tm-direct-user-results" class="tm-user-results"></div>
                            <input type="hidden" name="user_id" id="tm-direct-user-id" value="">
                            <div id="tm-direct-selected" class="tm-selected-user d-none mb-3"></div>
                            <button type="submit" class="btn btn-primary w-100" id="tm-direct-submit" disabled>
                                <i class="fa-solid fa-paper-plane me-1"></i><?= e(t('teams.index.start_chat_btn')) ?>
                            </button>
                        </form>
                    </div>

                    <!-- Tab Content: Gruppo -->
                    <div class="tab-pane fade" id="tm-tab-group">
                        <form action="<?= e(route('teams.conversations.store')) ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label"><?= e(t('teams.index.group_name_label')) ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required maxlength="255">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= e(t('teams.index.description_label')) ?></label>
                                <textarea class="form-control" name="description" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= e(t('teams.index.add_members_label')) ?></label>
                                <input type="text" class="form-control" placeholder="<?= e(t('teams.index.search_users_placeholder')) ?>"
                                       hx-get="<?= e(route('teams.users.search')) ?>"
                                       hx-trigger="keyup changed delay:300ms"
                                       hx-target="#tm-group-user-results"
                                       name="q" autocomplete="off">
                                <div id="tm-group-user-results" class="tm-user-results mt-1"></div>
                            </div>
                            <div id="tm-group-selected-members" class="tm-selected-members mb-3"></div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-users me-1"></i><?= e(t('teams.index.create_group_btn')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php $view->include('Auth/Views/partials/cropper_modal'); ?>

<?php $view->end(); ?>
