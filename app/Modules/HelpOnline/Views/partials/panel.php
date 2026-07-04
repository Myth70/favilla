<?php
$schemaReady = (bool) ($schemaReady ?? false);
$contextModule = $contextModule ?? null;
$contextModuleTitle = $contextModuleTitle ?? $contextModule;
$contextLabel = (string) ($contextLabel ?? 'Guida generale di Favilla');
$quickPrompts = $quickPrompts ?? [];
$topics = $topics ?? [];
$fullGuideUrl = (string) ($fullGuideUrl ?? route('helponline.index'));
?>
<div class="ho-panel" id="ho-offcanvas-label" data-context-module="<?= e((string) ($contextModule ?? '')) ?>">
    <header class="ho-panel-header">
        <div class="ho-panel-header-glow" aria-hidden="true"></div>
        <div class="ho-panel-header-row">
            <div class="ho-panel-identity">
                <span class="ho-panel-avatar" aria-hidden="true">
                    <i class="fa-solid fa-circle-question"></i>
                </span>
                <div class="ho-panel-titles">
                    <span class="ho-panel-eyebrow"><?= e(t('helponline.panel.eyebrow')) ?></span>
                    <h2 class="ho-panel-title"><?= e(t('helponline.panel.title')) ?></h2>
                </div>
            </div>
            <div class="ho-panel-header-actions">
                <button type="button"
                        class="ho-panel-iconbtn"
                        data-ho-action="reset"
                        title="<?= e(t('helponline.panel.new_conversation')) ?>"
                        aria-label="<?= e(t('helponline.panel.new_conversation')) ?>">
                    <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                </button>
                <a class="ho-panel-iconbtn"
                   href="<?= e($fullGuideUrl) ?>"
                   title="<?= e(t('helponline.panel.open_full')) ?>"
                   aria-label="<?= e(t('helponline.panel.open_full')) ?>">
                    <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                </a>
                <button type="button" class="btn-close ho-panel-close" data-bs-dismiss="offcanvas" aria-label="<?= e(t('common.action.close')) ?>"></button>
            </div>
        </div>
        <div class="ho-panel-context" role="status" aria-live="polite">
            <?php if (!$schemaReady): ?>
                <i class="fa-solid fa-triangle-exclamation text-warning" aria-hidden="true"></i>
                <span><?= e(t('helponline.panel.schema')) ?></span>
            <?php else: ?>
                <i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                <span><?= e($contextLabel) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="ho-panel-body">
        <div class="ho-chat-scroll" id="ho-chat-scroll">
            <div class="ho-chat-messages" id="ho-chat-messages" role="log" aria-live="polite" aria-label="<?= e(t('helponline.panel.chat_aria')) ?>" aria-atomic="false">
                <article class="ho-message ho-message-assistant ho-message-welcome">
                    <div class="ho-message-avatar" aria-hidden="true">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>
                    <div class="ho-message-card">
                        <div class="ho-message-title"><?= e(t('helponline.panel.welcome_title')) ?></div>
                        <div class="ho-message-body">
                            <?php if ($contextModuleTitle !== null): ?>
                                <?= t('helponline.panel.welcome_context', ['module' => e((string) $contextModuleTitle)]) ?>
                            <?php else: ?>
                                <?= e(t('helponline.panel.welcome_general')) ?>
                            <?php endif; ?>
                            <span class="d-block text-muted small mt-2">
                                <i class="fa-regular fa-keyboard me-1" aria-hidden="true"></i>
                                <?= t('helponline.panel.panel_hint') ?>
                            </span>
                        </div>
                    </div>
                </article>
            </div>

            <?php if ($schemaReady && !empty($quickPrompts)): ?>
                <section class="ho-suggestions" aria-label="<?= e(t('helponline.panel.quick_questions')) ?>" data-ho-starter>
                    <div class="ho-section-eyebrow">
                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                        <span><?= e(t('helponline.panel.quick_questions')) ?><?= $contextModuleTitle !== null ? ' · ' . e((string) $contextModuleTitle) : '' ?></span>
                    </div>
                    <div class="ho-suggestion-chips">
                        <?php foreach ($quickPrompts as $prompt): ?>
                            <?php
                            $promptLabel = is_array($prompt) ? (string) ($prompt['label'] ?? $prompt['message'] ?? '') : (string) $prompt;
                            $promptMessage = is_array($prompt) ? (string) ($prompt['message'] ?? $promptLabel) : (string) $prompt;
                            $promptChunk = is_array($prompt) ? (int) ($prompt['chunk'] ?? 0) : 0;
                            ?>
                            <button type="button" class="ho-chip" data-ho-suggestion="<?= e($promptMessage) ?>"<?= $promptChunk > 0 ? ' data-ho-chunk="' . e((string) $promptChunk) . '"' : '' ?>>
                                <i class="fa-regular fa-comment-dots" aria-hidden="true"></i>
                                <span><?= e($promptLabel) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($topics)): ?>
                <section class="ho-topics" aria-label="<?= e(t('helponline.panel.recommended')) ?>" data-ho-starter>
                    <div class="ho-section-eyebrow">
                        <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
                        <span><?= e(t('helponline.panel.recommended')) ?></span>
                    </div>
                    <div class="ho-topic-list">
                        <?php foreach ($topics as $topic): ?>
                            <a href="<?= e((string) $topic['url']) ?>" class="ho-topic">
                                <div class="ho-topic-icon" aria-hidden="true"><i class="fa-solid fa-book-open"></i></div>
                                <div class="ho-topic-text">
                                    <?php $topicModule = (string) ($topic['module_title'] ?? $topic['module_name'] ?? ''); ?>
                                    <?php if ($topicModule !== ''): ?>
                                        <div class="ho-topic-eyebrow">
                                            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                                            <span><?= e($topicModule) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ho-topic-title"><?= e((string) $topic['title']) ?></div>
                                    <div class="ho-topic-sub"><?= e((string) $topic['subtitle']) ?></div>
                                </div>
                                <i class="fa-solid fa-chevron-right ho-topic-chevron" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <footer class="ho-panel-footer">
            <form id="ho-chat-form" class="ho-chat-form" novalidate>
                <label for="ho-chat-input" class="visually-hidden"><?= e(t('helponline.panel.input_label')) ?></label>
                <div class="ho-chat-input-wrap">
                    <i class="fa-regular fa-comment-dots ho-chat-input-icon" aria-hidden="true"></i>
                    <textarea id="ho-chat-input"
                              name="message"
                              class="ho-chat-input"
                              placeholder="<?= e(t('helponline.panel.input_placeholder')) ?>"
                              autocomplete="off"
                              maxlength="500"
                              rows="1"
                              aria-describedby="ho-chat-help ho-chat-counter"></textarea>
                    <button type="submit" class="ho-chat-send" aria-label="<?= e(t('helponline.panel.send')) ?>" disabled>
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="ho-chat-meta">
                    <span id="ho-chat-help" class="ho-chat-hint">
                        <i class="fa-regular fa-circle-question me-1" aria-hidden="true"></i>
                        <?= e(t('helponline.panel.chat_hint')) ?>
                    </span>
                    <span id="ho-chat-counter" class="ho-chat-counter" aria-live="off">0 / 500</span>
                </div>
            </form>
        </footer>
    </div>
</div>
