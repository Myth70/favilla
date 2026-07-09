<?php
$view->layout('main');
$view->pushStyle('css/nt-settings.css');
$view->pushScript('js/nt-push.js');
$view->start('content');
?>

<div class="container-fluid py-3">

<?php
$ntSettingsButtons  = '<a href="' . e(route('profile')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('notifications.settings.profile')) . '"><i class="fa-solid fa-user"></i> ' . e(t('notifications.settings.profile')) . '</a>';
$ntSettingsButtons .= '<a href="' . e(route('notifications.index')) . '" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="' . e(t('notifications.settings.my_notifications')) . '"><i class="fa-solid fa-list"></i> ' . e(t('notifications.settings.my_notifications')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => t('notifications.settings.title'),
    'moduleIcon'     => 'fa-solid fa-bell',
    'moduleSubtitle' => t('notifications.settings.subtitle'),
    'moduleButtons'  => $ntSettingsButtons,
]);
?>

    <?php if (!empty($notificationSettings['telegram']['available'])): ?>
    <div class="nts-tg-bar mb-3 <?= !empty($notificationSettings['telegram']['linked']) ? 'nts-tg-bar--linked' : '' ?>">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-brands fa-telegram fa-lg nts-tg-icon"></i>
            <span class="nts-tg-label">Telegram</span>
            <?php if (!empty($notificationSettings['telegram']['linked'])): ?>
                <span class="small text-success">
                    <i class="fa-solid fa-circle-check me-1"></i><?= e(t('notifications.settings.linked')) ?><?= !empty($notificationSettings['telegram']['username']) ? ' &bull; @' . e($notificationSettings['telegram']['username']) : '' ?>
                </span>
            <?php else: ?>
                <span class="small text-secondary"><?= t('notifications.settings.not_linked') ?></span>
            <?php endif; ?>
        </div>
        <div>
            <?php if (!empty($notificationSettings['telegram']['linked'])): ?>
                <form method="POST" action="<?= e(route('notifications.settings.telegram.disconnect')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            data-app-confirm="<?= e(t('notifications.settings.disconnect_confirm')) ?>"
                            data-app-confirm-label="<?= e(t('notifications.settings.disconnect')) ?>">
                        <i class="fa-solid fa-link-slash me-1"></i><?= e(t('notifications.settings.disconnect')) ?>
                    </button>
                </form>
            <?php elseif (!empty($notificationSettings['telegram']['deep_link'])): ?>
                <a href="<?= e($notificationSettings['telegram']['deep_link']) ?>"
                   target="_blank" rel="noopener noreferrer"
                   class="btn btn-sm btn-primary">
                    <i class="fa-brands fa-telegram me-1"></i><?= e(t('notifications.settings.connect')) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php $pushSettings = $notificationSettings['web_push'] ?? []; ?>
    <?php if (!empty($pushSettings['available']) && !empty($pushSettings['vapid_public_key'])): ?>
    <div class="nts-tg-bar mb-3 <?= !empty($pushSettings['subscribed']) ? 'nts-tg-bar--linked' : '' ?>"
         id="nts-push"
         data-vapid-key="<?= e((string) $pushSettings['vapid_public_key']) ?>"
         data-subscribe-url="<?= e(route('notifications.settings.push.subscribe')) ?>"
         data-unsubscribe-url="<?= e(route('notifications.settings.push.unsubscribe')) ?>"
         data-device-count="<?= (int) ($pushSettings['device_count'] ?? 0) ?>">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-tower-broadcast fa-lg nts-tg-icon"></i>
            <span class="nts-tg-label"><?= e(t('notifications.settings.push.title')) ?></span>
            <span class="small text-secondary" id="nts-push-status"><?= e(t('notifications.settings.push.status_loading')) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-secondary d-none" id="nts-push-devices"></span>
            <button type="button" class="btn btn-sm btn-primary d-none" id="nts-push-enable">
                <i class="fa-solid fa-bell me-1"></i><?= e(t('notifications.settings.push.enable')) ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger d-none" id="nts-push-disable">
                <i class="fa-solid fa-bell-slash me-1"></i><?= e(t('notifications.settings.push.disable')) ?>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(route('notifications.settings.update')) ?>" id="nts-form">
        <?= csrf_field() ?>

        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="app-card-icon"><i class="fa-solid fa-sliders"></i></span>
                <span class="fw-semibold"><?= e(t('notifications.settings.per_module')) ?></span>
            </div>
            <div class="card-body p-0">

                <?php
                $hiddenModules = ['admin', 'auth', 'backup', 'health_check', 'notifications'];
                foreach (($notificationSettings['modules'] ?? []) as $moduleSetting):
                    if (in_array($moduleSetting['slug'], $hiddenModules, true)) continue;
                ?>
                <?php $evCollapseId = 'nts-ev-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $moduleSetting['slug']); ?>

                <div class="nts-module-row">
                    <div class="nts-module-meta">
                        <div class="nts-module-icon">
                            <i class="<?= e($moduleSetting['icon']) ?>"></i>
                        </div>
                        <div>
                            <div class="nts-module-name"><?= e($moduleSetting['label']) ?></div>
                            <?php if (!empty($moduleSetting['description'])): ?>
                                <div class="nts-module-desc"><?= e($moduleSetting['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="nts-controls">
                        <?php foreach (($moduleSetting['channels'] ?? []) as $channel): ?>
                        <label class="nts-channel-pill <?= empty($channel['available']) ? 'nts-channel-pill--disabled' : '' ?>">
                            <span class="form-check form-switch mb-0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       role="switch"
                                       name="notify[<?= e($moduleSetting['slug']) ?>][<?= e($channel['slug']) ?>]"
                                       value="1"
                                       <?= !empty($channel['enabled']) ? 'checked' : '' ?>
                                       <?= empty($channel['available']) ? 'disabled' : '' ?>>
                            </span>
                            <span class="nts-pill-label"><?= e($channel['name']) ?></span>
                        </label>
                        <?php endforeach; ?>

                        <?php if (!empty($moduleSetting['events'])): ?>
                        <button type="button"
                                class="nts-ev-toggle"
                                data-bs-toggle="collapse"
                                data-bs-target="#<?= e($evCollapseId) ?>"
                                aria-expanded="false"
                                aria-controls="<?= e($evCollapseId) ?>">
                            <i class="fa-solid fa-sliders"></i>
                            <span class="nts-ev-toggle-text"><?= e(t('notifications.settings.customize')) ?></span>
                            <i class="fa-solid fa-chevron-down nts-ev-toggle-arrow"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($moduleSetting['events'])): ?>
                <div class="collapse nts-events-panel" id="<?= e($evCollapseId) ?>">
                    <div class="nts-events-inner">
                        <div class="nts-events-label"><?= e(t('notifications.settings.per_event_channels')) ?></div>

                        <?php foreach (($moduleSetting['events'] ?? []) as $eventSetting): ?>
                        <div class="nts-event-row">
                            <div class="nts-event-meta">
                                <div class="nts-event-icon">
                                    <i class="<?= e($eventSetting['icon']) ?>"></i>
                                </div>
                                <div>
                                    <div class="nts-event-name">
                                        <?= e($eventSetting['name']) ?>
                                        <?php if (!empty($eventSetting['is_system'])): ?>
                                            <span class="badge text-bg-warning nts-sys-badge"><?= e(t('notifications.settings.system_badge')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($eventSetting['description'])): ?>
                                        <div class="nts-event-desc"><?= e($eventSetting['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="nts-event-controls">
                                <?php foreach (($eventSetting['channels'] ?? []) as $channel): ?>
                                <?php
                                    $stateInputName = 'notify_events[' . $moduleSetting['slug'] . '][' . $eventSetting['slug'] . '][' . $channel['slug'] . ']';
                                    $initialState   = (string) ($channel['override_state'] ?? 'inherit');
                                    $checked        = $initialState === 'enabled' || ($initialState === 'inherit' && !empty($channel['resolved_enabled']));
                                ?>
                                <div class="nts-event-ch <?= empty($channel['available']) ? 'nts-event-ch--disabled' : '' ?>">
                                    <span class="nts-event-ch-label"><?= e($channel['name']) ?></span>
                                    <span class="form-check form-switch mb-0">
                                        <input class="form-check-input js-notify-event-toggle"
                                               type="checkbox"
                                               role="switch"
                                               data-target-state="<?= e($stateInputName) ?>"
                                               <?= $checked ? 'checked' : '' ?>
                                               <?= empty($channel['available']) ? 'disabled' : '' ?>>
                                    </span>
                                    <input type="hidden"
                                           name="<?= e($stateInputName) ?>"
                                           value="<?= e($initialState) ?>"
                                           class="js-notify-event-state">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>

            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i><?= e(t('notifications.settings.save')) ?>
            </button>
        </div>
    </form>

</div>

<script nonce="<?= e($_SERVER['CSP_NONCE'] ?? '') ?>">
(function () {
    'use strict';

    function cssEscape(v) {
        return (window.CSS && window.CSS.escape) ? window.CSS.escape(v) : v.replace(/([\\"'\[\]\.\:])/g, '\\$1');
    }

    function findStateInput(name) {
        return document.querySelector('input.js-notify-event-state[name="' + cssEscape(name) + '"]');
    }

    document.querySelectorAll('.js-notify-event-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var input = findStateInput(toggle.getAttribute('data-target-state') || '');
            if (input) input.value = toggle.checked ? 'enabled' : 'disabled';
        });
    });

    document.querySelectorAll('.nts-ev-toggle').forEach(function (btn) {
        var target = document.querySelector(btn.getAttribute('data-bs-target'));
        if (!target) return;
        target.addEventListener('show.bs.collapse', function () {
            btn.classList.add('is-open');
            btn.querySelector('.nts-ev-toggle-text').textContent = <?= json_encode(t('notifications.settings.hide')) ?>;
        });
        target.addEventListener('hide.bs.collapse', function () {
            btn.classList.remove('is-open');
            btn.querySelector('.nts-ev-toggle-text').textContent = <?= json_encode(t('notifications.settings.customize')) ?>;
        });
    });
}());
</script>

<?php $view->end(); ?>
