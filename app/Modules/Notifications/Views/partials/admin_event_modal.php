<?php
$event = $event ?? [];
$eventSlug = (string) ($event['slug'] ?? '');
$eventName = (string) ($event['name'] ?? $eventSlug);
$eventIcon = (string) ($event['icon'] ?? 'fa-solid fa-bell');
$eventColor = (string) ($event['color'] ?? '');
$eventLevel = (string) ($event['default_level'] ?? 'info');
if (!in_array($eventLevel, ['info','success','warning','danger'], true)) { $eventLevel = 'info'; }
$eventDescription = (string) ($event['description'] ?? '');
$channels = $event['channels'] ?? [];
$contextVariables = is_array($event['context_variables'] ?? null) ? $event['context_variables'] : [];
$isSystem = !empty($event['is_system']);
$iconInputId = 'ntas-modal-icon';
$colorInputId = 'ntas-modal-color';

// Lookup maps for human-readable labels (mirror of admin_settings.php catalog)
$iconLabels = [
    'fa-solid fa-bell' => t('notifications.icon_label.bell'), 'fa-solid fa-envelope' => t('notifications.icon_label.envelope'),
    'fa-solid fa-envelope-open' => t('notifications.icon_label.envelope_open'), 'fa-solid fa-paper-plane' => t('notifications.icon_label.paper_plane'),
    'fa-solid fa-comment' => t('notifications.icon_label.comment'), 'fa-solid fa-comments' => t('notifications.icon_label.comments'),
    'fa-solid fa-circle-info' => t('notifications.icon_label.circle_info'), 'fa-solid fa-circle-check' => t('notifications.icon_label.circle_check'),
    'fa-solid fa-triangle-exclamation' => t('notifications.icon_label.triangle_exclamation'), 'fa-solid fa-circle-exclamation' => t('notifications.icon_label.circle_exclamation'),
    'fa-solid fa-circle-xmark' => t('notifications.icon_label.circle_xmark'), 'fa-solid fa-clock' => t('notifications.icon_label.clock'),
    'fa-solid fa-calendar-day' => t('notifications.icon_label.calendar_day'), 'fa-solid fa-list-check' => t('notifications.icon_label.list_check'),
    'fa-solid fa-database' => t('notifications.icon_label.database'), 'fa-solid fa-address-book' => t('notifications.icon_label.address_book'),
    'fa-solid fa-newspaper' => t('notifications.icon_label.newspaper'), 'fa-solid fa-people-group' => t('notifications.icon_label.people_group'),
    'fa-solid fa-heart-pulse' => t('notifications.icon_label.heart_pulse'), 'fa-solid fa-shield-halved' => t('notifications.icon_label.shield_halved'),
    'fa-solid fa-box-archive' => t('notifications.icon_label.box_archive'), 'fa-solid fa-gear' => t('notifications.icon_label.gear'),
    'fa-solid fa-user-check' => t('notifications.icon_label.user_check'), 'fa-solid fa-plus' => t('notifications.icon_label.plus'),
    'fa-solid fa-link' => t('notifications.icon_label.link'),
];
$colorLabels = [
    '' => t('notifications.color_label.default'), '#3b82f6' => t('notifications.color_label.blue'), '#8b5cf6' => t('notifications.color_label.purple'),
    '#ec4899' => t('notifications.color_label.pink'), '#ef4444' => t('notifications.color_label.red'), '#f97316' => t('notifications.color_label.orange'),
    '#22c55e' => t('notifications.color_label.green'), '#14b8a6' => t('notifications.color_label.teal'), '#64748b' => t('notifications.color_label.gray'),
];

$globalVars = [
    'title'               => t('notifications.tplvar.title'),
    'body'                => t('notifications.tplvar.body'),
    'type'                => t('notifications.tplvar.type'),
    'link'                => t('notifications.tplvar.link'),
    'date'                => t('notifications.tplvar.date'),
    'time'                => t('notifications.tplvar.time'),
    'datetime'            => t('notifications.tplvar.datetime'),
    'date_it'             => t('notifications.tplvar.date_it'),
    'time_it'             => t('notifications.tplvar.time_it'),
    'recipient_user_name' => t('notifications.tplvar.recipient_user_name'),
    'sender_user_name'    => t('notifications.tplvar.sender_user_name'),
    'module_slug'         => t('notifications.tplvar.module_slug'),
    'event_slug'          => t('notifications.tplvar.event_slug'),
    'channel_slug'        => t('notifications.tplvar.channel_slug'),
];

$sampleData = [
    'title'               => $eventName,
    'body'                => t('notifications.admin.preview_body'),
    'type'                => $eventLevel,
    'link'                => '/esempio/link',
    'date'                => date('Y-m-d'),
    'time'                => date('H:i'),
    'datetime'            => date('Y-m-d H:i:s'),
    'date_it'             => date('d/m/Y'),
    'time_it'             => date('H:i'),
    'recipient_user_name' => 'Mario Rossi',
    'sender_user_name'    => 'Sistema',
    'module_slug'         => (string) ($event['module_slug'] ?? ''),
    'event_slug'          => $eventSlug,
    'channel_slug'        => 'in_app',
];
foreach ($contextVariables as $key => $label) {
    $sampleData[(string) $key] = 'Esempio ' . (string) $label;
}
?>
<form method="POST"
      action="<?= e(route('admin.notifications.settings.events.update', ['slug' => $eventSlug])) ?>"
      hx-post="<?= e(route('admin.notifications.settings.events.update', ['slug' => $eventSlug])) ?>"
      hx-swap="none"
      id="ntas-modal-form">
    <?= csrf_field() ?>

    <div class="modal-header">
        <div>
            <h5 class="modal-title d-flex align-items-center gap-2">
                <i class="<?= e($eventIcon) ?> js-ntas-modal-icon-preview" id="ntas-modal-icon-header" <?= $eventColor !== '' ? 'style="color:' . e($eventColor) . '"' : '' ?>></i>
                <?= e($eventName) ?>
            </h5>
            <div class="d-flex align-items-center gap-2 mt-1">
                <code class="small"><?= e($eventSlug) ?></code>
                <?php if ($isSystem): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border"><?= e(t('notifications.admin.is_system')) ?></span>
                <?php endif; ?>
                <?php if ($eventDescription !== ''): ?>
                    <span class="text-muted small d-none d-md-inline">&mdash; <?= e($eventDescription) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
    </div>

    <div class="modal-body">
        <div class="row g-4">
            <!-- LEFT: Editor -->
            <div class="col-lg-7">
                <!-- Icon + Color pickers -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold"><?= e(t('notifications.admin.icon')) ?></label>
                        <input type="hidden" id="<?= e($iconInputId) ?>" name="icon" value="<?= e($eventIcon) ?>">
                        <button type="button"
                                class="btn btn-outline-secondary w-100 ntas-picker-btn js-ntas-open-icon-modal"
                                data-target-input="<?= e($iconInputId) ?>"
                                data-preview-scope="ntas-modal">
                            <i class="js-ntas-icon-preview ntas-icon-preview-lg <?= e($eventIcon) ?>" data-preview-scope="ntas-modal"></i>
                            <span class="js-ntas-icon-label" data-preview-scope="ntas-modal"><?= e($iconLabels[$eventIcon] ?? $eventIcon) ?></span>
                            <i class="fa-solid fa-chevron-down ms-auto text-muted ntas-chevron-xs"></i>
                        </button>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold"><?= e(t('notifications.admin.color')) ?></label>
                        <input type="hidden" id="<?= e($colorInputId) ?>" name="color" value="<?= e($eventColor) ?>">
                        <button type="button"
                                class="btn btn-outline-secondary w-100 ntas-picker-btn js-ntas-open-color-modal"
                                data-target-input="<?= e($colorInputId) ?>"
                                data-preview-scope="ntas-modal">
                            <span class="ntas-color-dot ntas-color-dot-lg js-ntas-color-preview <?= $eventColor === '' ? 'is-default' : '' ?>" data-preview-scope="ntas-modal" <?= $eventColor !== '' ? 'style="background-color:' . e($eventColor) . '"' : '' ?>></span>
                            <span class="js-ntas-color-label" data-preview-scope="ntas-modal"><?= e($colorLabels[$eventColor] ?? ($eventColor !== '' ? $eventColor : 'Default sistema')) ?></span>
                            <i class="fa-solid fa-chevron-down ms-auto text-muted ntas-chevron-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Channel tabs -->
                <ul class="nav nav-pills nav-fill ntas-channel-tabs mb-3" id="ntas-channel-tabs" role="tablist">
                    <?php foreach ($channels as $i => $channel): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?= $i === 0 ? 'active' : '' ?>"
                                    data-bs-toggle="pill"
                                    data-bs-target="#ntas-ch-pane-<?= e((string) $channel['slug']) ?>"
                                    type="button" role="tab"
                                    data-channel="<?= e((string) $channel['slug']) ?>">
                                <?php
                                $chIcon = match ((string) $channel['slug']) {
                                    'in_app'   => 'fa-solid fa-bell',
                                    'email'    => 'fa-solid fa-envelope',
                                    'telegram' => 'fa-brands fa-telegram',
                                    default    => 'fa-solid fa-circle',
                                };
                                ?>
                                <i class="<?= e($chIcon) ?> me-1"></i><?= e((string) $channel['name']) ?>
                                <span class="ntas-ch-tab-indicator <?= !empty($channel['enabled']) ? 'is-on' : 'is-off' ?>" id="ntas-ch-indicator-<?= e((string) $channel['slug']) ?>"></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Channel panes -->
                <div class="tab-content" id="ntas-channel-panes">
                    <?php foreach ($channels as $i => $channel): ?>
                        <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="ntas-ch-pane-<?= e((string) $channel['slug']) ?>" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-semibold small"><?= e((string) $channel['name']) ?></span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input ntas-channel-toggle" type="checkbox"
                                           name="channels[<?= e((string) $channel['slug']) ?>][enabled]" value="1"
                                           id="ntas-ch-toggle-<?= e((string) $channel['slug']) ?>"
                                           data-channel="<?= e((string) $channel['slug']) ?>"
                                           <?= !empty($channel['enabled']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="ntas-ch-toggle-<?= e((string) $channel['slug']) ?>"><?= e(t('notifications.admin.channel_active')) ?></label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted"><?= e(t('notifications.admin.subject_template')) ?></label>
                                <input type="text"
                                       class="form-control form-control-sm ntas-template-input font-monospace"
                                       name="channels[<?= e((string) $channel['slug']) ?>][subject_template]"
                                       value="<?= e((string) ($channel['subject_template'] ?? '')) ?>"
                                       placeholder="{{title}}"
                                       data-channel="<?= e((string) $channel['slug']) ?>"
                                       data-field="subject">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted"><?= e(t('notifications.admin.body_template')) ?></label>
                                <textarea class="form-control form-control-sm ntas-template-input font-monospace"
                                          rows="5"
                                          name="channels[<?= e((string) $channel['slug']) ?>][body_template]"
                                          placeholder="{{body}}"
                                          data-channel="<?= e((string) $channel['slug']) ?>"
                                          data-field="body"><?= e((string) ($channel['body_template'] ?? '')) ?></textarea>
                            </div>

                            <input type="hidden" name="channels[<?= e((string) $channel['slug']) ?>][layout_config]" value="<?= e((string) ($channel['layout_config'] ?? '')) ?>">

                            <!-- Context variable chips -->
                            <div class="mb-2">
                                <div class="form-label small text-muted mb-1">
                                    <i class="fa-solid fa-code me-1"></i><?= e(t('notifications.admin.vars_click_insert')) ?>
                                </div>
                                <div class="ntas-context-chip-wrap">
                                    <?php foreach ($contextVariables as $key => $label): ?>
                                        <button type="button" class="btn btn-sm ntas-context-chip ntas-chip-module" data-template-token="{{<?= e((string) $key) ?>}}" title="<?= e((string) $label) ?>">{{<?= e((string) $key) ?>}}</button>
                                    <?php endforeach; ?>
                                    <?php foreach ($globalVars as $gKey => $gLabel): ?>
                                        <button type="button" class="btn btn-sm ntas-context-chip ntas-chip-global" data-template-token="{{<?= e((string) $gKey) ?>}}" title="<?= e((string) $gLabel) ?>">{{<?= e((string) $gKey) ?>}}</button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- RIGHT: Live Preview -->
            <div class="col-lg-5">
                <div class="ntas-preview-panel">
                    <div class="ntas-preview-header">
                        <i class="fa-solid fa-eye me-1"></i><?= e(t('notifications.admin.live_preview')) ?>
                    </div>
                    <div class="ntas-preview-body" id="ntas-preview-body">
                        <!-- In-App preview (replica esatta di .nt-item) -->
                        <div class="ntas-preview-inapp" id="ntas-preview-inapp">
                            <div class="nt-item nt-unread ntas-preview-nt-item" id="ntas-prev-inapp-item">
                                <div class="nt-indicator nt-<?= e($eventLevel) ?>" id="ntas-prev-inapp-indicator"<?= $eventColor !== '' ? ' style="background-color:' . e($eventColor) . '"' : '' ?>></div>
                                <div class="nt-type-icon nt-<?= e($eventLevel) ?>" id="ntas-prev-inapp-icon"<?= $eventColor !== '' ? ' style="color:' . e($eventColor) . '"' : '' ?>>
                                    <i class="<?= e($eventIcon) ?>"></i>
                                </div>
                                <div class="nt-item-body">
                                    <div class="nt-item-title nt-item-title-full" id="ntas-prev-inapp-subject"><?= e($eventName) ?></div>
                                    <div class="nt-item-text nt-item-text-full" id="ntas-prev-inapp-body"><?= e(t('notifications.admin.preview_body')) ?></div>
                                    <div class="nt-item-meta"><?= e(t('notifications.send.preview_now')) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Email preview -->
                        <div class="ntas-preview-email d-none" id="ntas-preview-email">
                            <div class="ntas-preview-email-wrap">
                                <div class="ntas-preview-email-header" id="ntas-prev-email-subject"><?= e($eventName) ?></div>
                                <div class="ntas-preview-email-greeting"><?= e(t('notifications.admin.preview_greeting')) ?> <strong>Mario Rossi</strong>,</div>
                                <div class="ntas-preview-email-body" id="ntas-prev-email-body"><?= e(t('notifications.admin.preview_body')) ?></div>
                                <div class="ntas-preview-email-cta">
                                    <a href="#" class="ntas-preview-email-btn" id="ntas-prev-email-link"><?= e(t('notifications.admin.preview_email_cta')) ?></a>
                                </div>
                                <div class="ntas-preview-email-footer"><?= e(t('notifications.admin.preview_email_footer')) ?></div>
                            </div>
                        </div>

                        <!-- Telegram preview -->
                        <div class="ntas-preview-telegram d-none" id="ntas-preview-telegram">
                            <div class="ntas-preview-tg-bubble">
                                <div class="ntas-preview-tg-name"><?= e(t('notifications.admin.preview_tg_name')) ?></div>
                                <div class="ntas-preview-tg-text" id="ntas-prev-tg-body"><?= e(t('notifications.admin.preview_body')) ?></div>
                                <div class="ntas-preview-tg-time"><?= e(date('H:i')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('notifications.admin.cancel')) ?></button>
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('notifications.admin.save_event')) ?>
        </button>
    </div>
</form>

<div id="ntas-modal-sample-data" class="ntas-hidden-data"><?= e(json_encode($sampleData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></div>
