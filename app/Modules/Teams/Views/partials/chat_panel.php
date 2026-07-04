<?php

$conv = $activeConversation;
$isGroup = $conv['type'] === 'group';
$convDisplayName = $isGroup ? ($conv['name'] ?? t('teams.exception.default_group_name')) : ($conv['other_user_name'] ?? t('teams.exception.default_user_name'));
$currentUserId = (int) auth()['id'];
$isMuted = !empty($conv['notifications_muted']);
$messages = is_array($messages ?? null) ? $messages : [];
$lastMsgAt = !empty($messages) ? end($messages)['created_at'] : '';
reset($messages);
?>
<div class="tm-chat"
     id="tm-chat"
     data-conversation-id="<?= (int) $conv['id'] ?>"
     data-last-message-at="<?= e($lastMsgAt) ?>">

    <!-- Header -->
    <div class="tm-chat-header">
        <button class="btn btn-link text-body d-md-none tm-chat-back" id="tm-back-btn"
                type="button"
                title="<?= e(t('teams.chat_panel.back_tip')) ?>" aria-label="<?= e(t('teams.chat_panel.back_tip')) ?>"
                data-bs-toggle="tooltip" data-bs-placement="bottom">
            <i class="fa-solid fa-arrow-left"></i>
        </button>
        <?php if ($isGroup): ?>
            <?php /* Gruppo: tutta la zona info è cliccabile e apre l'offcanvas info. */ ?>
            <button type="button"
                    class="tm-chat-header-info tm-chat-header-info-btn"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#tm-group-panel"
                    aria-label="<?= e(t('teams.chat_panel.group_info_aria')) ?>">
                <span class="tm-chat-header-icon"><i class="fa-solid fa-users"></i></span>
                <span class="tm-chat-header-text">
                    <span class="tm-chat-header-name"><?= e($convDisplayName) ?></span>
                    <span class="tm-chat-header-subtitle"><?= e(tc('teams.chat_panel.members_count', count($members))) ?></span>
                </span>
            </button>
        <?php else: ?>
            <div class="tm-chat-header-info">
                <span class="tm-chat-header-icon"><i class="fa-solid fa-user"></i></span>
                <span class="tm-chat-header-text">
                    <span class="tm-chat-header-name"><?= e($convDisplayName) ?></span>
                    <span class="tm-chat-header-subtitle"><?= e(t('teams.chat_panel.direct_chat_subtitle')) ?></span>
                </span>
            </div>
        <?php endif; ?>
        <div class="tm-chat-header-actions">
            <!-- Pinned messages -->
            <button type="button"
                    class="btn tm-chat-action-btn tm-pinned-toggle"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#tm-pinned-panel"
                    hx-get="<?= e(route('teams.pinned.list', ['id' => $conv['id']])) ?>"
                    hx-target="#tm-pinned-panel-body"
                    hx-trigger="click, teamsPinnedRefresh from:body"
                    title="<?= e(t('teams.index.pinned_panel_title')) ?>" aria-label="<?= e(t('teams.index.pinned_panel_title')) ?>"
                    data-bs-placement="bottom">
                <i class="fa-solid fa-thumbtack"></i>
                <span class="tm-pinned-count<?= empty($pinnedCount) ? ' d-none' : '' ?>" id="tm-pinned-count"><?= (int) ($pinnedCount ?? 0) ?></span>
            </button>
            <?php /* Mute toggle: per i gruppi è nell'offcanvas (quick actions);
                       qui resta solo per le chat 1:1 dove non c'è offcanvas. */ ?>
            <?php if (!$isGroup): ?>
            <span id="tm-mute-btn">
                <button type="button"
                        class="btn tm-chat-action-btn<?= $isMuted ? ' tm-chat-action-btn-active' : '' ?>"
                        hx-post="<?= e(route('teams.conversations.mute', ['id' => $conv['id']])) ?>"
                        hx-target="#tm-mute-btn"
                        hx-swap="outerHTML"
                        title="<?= $isMuted ? e(t('teams.show.notifications_muted')) : e(t('teams.chat_panel.mute_notifications_tip')) ?>"
                        aria-label="<?= $isMuted ? e(t('teams.show.notifications_muted')) : e(t('teams.chat_panel.mute_notifications_tip')) ?>"
                        data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="fa-solid <?= $isMuted ? 'fa-bell-slash' : 'fa-bell' ?>"></i>
                </button>
            </span>
            <?php endif; ?>
            <!-- Nascondi conversazione -->
            <button type="button"
                    class="btn tm-chat-action-btn"
                    hx-post="<?= e(route('teams.conversations.hide', ['id' => $conv['id']])) ?>"
                    title="<?= e(t('teams.chat_panel.hide_conversation_tip')) ?>" aria-label="<?= e(t('teams.chat_panel.hide_conversation_tip')) ?>"
                    data-bs-toggle="tooltip" data-bs-placement="bottom">
                <i class="fa-solid fa-eye-slash"></i>
            </button>
        </div>
    </div>

    <!-- Messaggi -->
    <div class="tm-messages" id="tm-messages">
        <?php if ($hasOlderMessages && !empty($messages)): ?>
        <div class="tm-load-older"
             id="tm-load-older"
             hx-get="<?= e(route('teams.messages.index', ['id' => $conv['id']])) ?>?before=<?= (int) $messages[0]['id'] ?>"
             hx-trigger="revealed"
             hx-target="#tm-load-older"
             hx-swap="outerHTML">
            <div class="text-center py-2">
                <span class="spinner-border spinner-border-sm text-primary"></span>
                <small class="text-muted ms-1"><?= e(t('teams.index.loading')) ?></small>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($messages)): ?>
        <div class="tm-messages-empty">
            <div class="tm-messages-empty-icon">👋</div>
            <p class="tm-empty-title"><?= e(t('teams.chat_panel.no_messages_yet')) ?></p>
            <small class="text-muted">
                <?php if ($isGroup): ?>
                    <?= e(t('teams.chat_panel.greet_group')) ?>
                <?php else: ?>
                    <?= e(t('teams.chat_panel.greet_user', ['name' => $convDisplayName])) ?>
                <?php endif; ?>
            </small>
            <button type="button" class="btn btn-sm btn-link mt-2 tm-empty-greet-btn"
                    data-greeting="<?= e(t('teams.chat_panel.greeting_text')) ?>">
                <?= e(t('teams.chat_panel.start_with_greeting_btn')) ?>
            </button>
        </div>
        <?php endif; ?>

        <?php
        $prevDate    = '';
$prevUserId  = null;
$prevMsgTime = null;
$convRepo    = app(\App\Modules\Teams\Repositories\ConversationRepository::class);
$msgRepo2    = app(\App\Modules\Teams\Repositories\MessageRepository::class);
$msgIds      = array_column($messages, 'id');
$reactionsMap = !empty($msgIds) ? $msgRepo2->getReactionsForMessages($msgIds) : [];
$canPinMessages = ($conv['my_role'] ?? '') === 'admin' || has_permission('teams.admin');
// Batch: precalcola "letto da N membri" per i miei messaggi in un'unica
// query, evitando N+1 di countReadBy nel loop sotto.
$myCreatedAts = [];
foreach ($messages as $_m) {
    if ((int) $_m['user_id'] === $currentUserId) {
        $myCreatedAts[] = (string) $_m['created_at'];
    }
}
$readByMap = !empty($myCreatedAts)
    ? $convRepo->countReadByForMessages($conv['id'], $currentUserId, $myCreatedAts)
    : [];
foreach ($messages as $msg):
    $msgDate      = date('Y-m-d', strtotime($msg['created_at']));
    $msgTimestamp = strtotime($msg['created_at']);
    $isDateChange = $msgDate !== $prevDate;
    if ($isDateChange):
        $prevDate = $msgDate;
        ?>
            <div class="tm-date-divider">
                <span><?= e(format_date($msg['created_at'], 'relative')) ?></span>
            </div>
        <?php
    endif;
    $isSystemMsg = ($msg['type'] ?? 'text') === 'system';
    $isConsecutive = !$isDateChange
        && !$isSystemMsg
        && $prevUserId === (int) $msg['user_id']
        && $prevMsgTime !== null
        && ($msgTimestamp - $prevMsgTime) < 300;
    $readByCount = (int) $msg['user_id'] === $currentUserId
        ? ($readByMap[(string) $msg['created_at']] ?? 0)
        : 0;
    $view->include('Teams/Views/partials/message_bubble', [
        'msg'              => $msg,
        'currentUserId'    => $currentUserId,
        'showAvatar'       => !$isConsecutive,
        'isConsecutive'    => $isConsecutive,
        'othersMaxReadAt'  => $othersMaxReadAt ?? null,
        'readByCount'      => $readByCount,
        'reactions'        => $reactionsMap[(int) $msg['id']] ?? [],
        'canPin'           => $canPinMessages,
    ]);
    $prevUserId  = (int) $msg['user_id'];
    $prevMsgTime = $msgTimestamp;
endforeach;
?>

        <!-- Sentinel polling unificato (messaggi + mutazioni + typing + conv-dirty trigger) -->
        <div id="tm-new-messages-target"
             hx-get="<?= e(route('teams.state', ['id' => $conv['id']])) ?>"
             hx-trigger="every 3s"
             hx-target="#tm-new-messages-target"
             hx-swap="beforebegin">
        </div>
        <!-- Sentinel state messaggi: aggiornato OOB dal polling per filtrare
             le mutazioni successive (reaction/edit/delete/read). -->
        <span id="tm-poll-state-sentinel"
              data-state-ts="<?= e(date('Y-m-d H:i:s')) ?>"
              aria-hidden="true"
              style="display:none"></span>
        <!-- Sentinel conv-state: aggiornato OOB dal polling; il server lo usa per
             il dirty-check della lista conv (emette HX-Trigger: teamsConvRefresh). -->
        <span id="tm-conv-state-sentinel"
              data-state-ts="<?= e(date('Y-m-d H:i:s')) ?>"
              aria-hidden="true"
              style="display:none"></span>
    </div>

    <!-- Typing indicator (popolato OOB dal polling /state, no hx-trigger proprio) -->
    <div id="tm-typing-indicator"></div>

    <!-- Input messaggio -->
    <?php if (has_permission('teams.create')): ?>
    <div class="tm-input-area" data-drop-label="<?= e(t('teams.chat_panel.drop_to_attach')) ?>">
        <form id="tm-msg-form"
              hx-post="<?= e(route('teams.messages.store', ['id' => $conv['id']])) ?>"
              hx-target="#tm-new-messages-target"
              hx-swap="beforebegin"
              hx-include="#tm-msg-input,#tm-msg-attachment,#tm-reply-to-id"
              hx-encoding="multipart/form-data"
              enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="reply_to_id" id="tm-reply-to-id" value="">
            <div id="tm-reply-banner" class="tm-reply-banner d-none">
                <div class="tm-reply-banner-content">
                    <i class="fa-solid fa-reply me-2 text-muted"></i>
                    <div class="tm-reply-banner-info">
                        <small class="tm-reply-banner-label"><?= e(t('teams.chat_panel.reply_to_label')) ?> <span id="tm-reply-banner-name"></span></small>
                        <div class="tm-reply-banner-preview" id="tm-reply-banner-preview"></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-sm" id="tm-reply-banner-close" aria-label="<?= e(t('teams.chat_panel.cancel_reply_aria')) ?>"></button>
            </div>

            <?php $emojiTabs = \App\Modules\Teams\Support\EmojiCatalog::inputPicker(); ?>

            <!-- Chip anteprima allegati (popolato lato JS quando ci sono file) -->
            <div id="tm-attachment-chips" class="tm-attachment-chips d-none"></div>

            <!-- Toolbar formattazione fluttuante (mostrata su selezione testo) -->
            <div id="tm-format-bubble" class="tm-format-bubble d-none" role="toolbar" aria-label="<?= e(t('teams.chat_panel.format_selection_aria')) ?>">
                <button type="button" class="tm-fmt-btn" data-fmt="bold" title="<?= e(t('teams.chat_panel.bold_tip')) ?>"><strong>B</strong></button>
                <button type="button" class="tm-fmt-btn" data-fmt="italic" title="<?= e(t('teams.chat_panel.italic_tip')) ?>"><em>I</em></button>
                <button type="button" class="tm-fmt-btn" data-fmt="code" title="<?= e(t('teams.chat_panel.code_tip')) ?>">&lt;/&gt;</button>
                <button type="button" class="tm-fmt-btn" data-fmt="quote" title="<?= e(t('teams.chat_panel.quote_tip')) ?>">&gt;</button>
            </div>

            <div class="tm-input-row">
                <!-- 1. Emoji picker -->
                <div class="tm-emoji-wrap">
                    <button type="button" id="tm-emoji-btn" class="btn btn-outline-secondary mb-0"
                            title="<?= e(t('teams.chat_panel.insert_emoji_tip')) ?>" aria-expanded="false" aria-haspopup="dialog">
                        <i class="fa-regular fa-face-smile"></i>
                    </button>
                    <div id="tm-emoji-popover" class="tm-emoji-popover d-none" role="dialog" aria-label="<?= e(t('teams.chat_panel.emoji_picker_aria')) ?>">
                        <div class="tm-emoji-tabs" role="tablist">
                            <?php foreach ($emojiTabs as $key => $tab): ?>
                                <button type="button"
                                        class="tm-emoji-tab<?= $key === 'smileys' ? ' active' : '' ?>"
                                        data-tab="<?= e($key) ?>"
                                        role="tab"
                                        title="<?= e($tab['label']) ?>"
                                        aria-label="<?= e($tab['label']) ?>">
                                    <i class="fa-solid <?= e($tab['icon']) ?>"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <?php foreach ($emojiTabs as $key => $tab): ?>
                            <div class="tm-emoji-grid<?= $key !== 'smileys' ? ' d-none' : '' ?>"
                                 data-tab-panel="<?= e($key) ?>" role="tabpanel">
                                <?php foreach ($tab['emojis'] as $em): ?>
                                    <button type="button" class="tm-emoji-pick"
                                            data-emoji="<?= e($em) ?>"
                                            title="<?= e($em) ?>"><?= e($em) ?></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. Allegato file -->
                <label for="tm-msg-attachment" class="btn btn-outline-secondary mb-0" title="<?= e(t('teams.chat_panel.attach_file_tip')) ?>">
                    <i class="fa-solid fa-paperclip"></i>
                </label>
                <input type="file" id="tm-msg-attachment" name="attachments[]" class="d-none" multiple>

                <!-- 3. Textarea -->
                <textarea id="tm-msg-input"
                          name="body"
                          class="form-control"
                          rows="1"
                          placeholder="<?= e(t('teams.chat_panel.message_placeholder')) ?>"
                          maxlength="5000"
                          autocomplete="off"></textarea>

                <!-- 4. Invia -->
                <button type="submit" class="btn btn-primary tm-send-btn"
                        id="tm-send-btn" disabled>
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>

            <div class="tm-char-counter" id="tm-char-counter" aria-live="polite">0/5000</div>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Offcanvas: Info gruppo (stile Telegram: hero + quick actions + tab Membri/Media/File/Link) -->
<?php if ($isGroup): ?>
    <?php $view->include('Teams/Views/partials/group_panel/index', [
'conv'          => $conv,
'members'       => $members,
'currentUserId' => $currentUserId,
'headerInfo'    => $groupHeaderInfo ?? [],
    ]); ?>
<?php endif; ?>

<!-- Modal: Cronologia modifiche messaggio -->
<div class="modal fade" id="tm-edit-history-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fa-solid fa-clock-rotate-left me-2"></i><?= e(t('teams.chat_panel.edit_history_title')) ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="tm-edit-history-body">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>
