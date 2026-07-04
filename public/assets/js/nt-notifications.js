(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Badge: aggiorna conteggio quando il server invia HX-Trigger
    // ------------------------------------------------------------------
    document.body.addEventListener('notifCountUpdated', function (e) {
        var count = e.detail ? e.detail.value : 0;
        var badge = document.getElementById('nt-badge-count');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('d-none');
            var bell = document.querySelector('.nt-bell-btn');
            if (bell) {
                bell.classList.remove('nt-bell-shake');
                void bell.offsetWidth; // reflow per riavviare animazione
                bell.classList.add('nt-bell-shake');
            }
        } else {
            badge.textContent = '0';
            badge.classList.add('d-none');
        }
    });

    document.body.addEventListener('notifAllRead', function () {
        var badge = document.getElementById('nt-badge-count');
        if (badge) {
            badge.textContent = '0';
            badge.classList.add('d-none');
        }
    });

    // ------------------------------------------------------------------
    // Dropdown: mark-as-read prima di navigare al link della notifica
    // ------------------------------------------------------------------
    function getToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function getSelectedNotificationIds() {
        return Array.prototype.map.call(document.querySelectorAll('.nt-row-check:checked'), function (checkbox) {
            return checkbox.value;
        });
    }

    function syncNotificationBulkButtons(ids) {
        var selectedIds = Array.isArray(ids) ? ids : getSelectedNotificationIds();
        var hasSelection = selectedIds.length > 0;

        document.querySelectorAll('[data-nt-bulk-submit]').forEach(function (button) {
            button.disabled = !hasSelection;
        });
    }

    function syncNotificationBulkInputs(form, ids) {
        form.querySelectorAll('input[data-nt-bulk-id="1"]').forEach(function (input) {
            input.remove();
        });

        ids.forEach(function (id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'notification_ids[]';
            input.value = id;
            input.setAttribute('data-nt-bulk-id', '1');
            form.appendChild(input);
        });
    }

    function initNotificationBulkSelect() {
        if (typeof BulkSelect === 'undefined') return;
        if (!document.getElementById('nt-bulk-check-all')) return;

        BulkSelect.init({
            checkboxSelector: '.nt-row-check',
            selectAllId: 'nt-bulk-check-all',
            countId: 'nt-selected-count',
            onSelectionChange: syncNotificationBulkButtons
        });

        if (typeof BulkSelect._refresh === 'function') {
            BulkSelect._refresh();
            return;
        }

        syncNotificationBulkButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotificationBulkSelect);
    } else {
        initNotificationBulkSelect();
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.matches('[data-nt-bulk-form="1"]')) {
            return;
        }

        var ids = getSelectedNotificationIds();
        syncNotificationBulkInputs(form, ids);

        if (ids.length > 0) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        if (typeof window.notify === 'function') {
            window.notify({
                message: t('js.notifications.select_at_least_one', 'Seleziona almeno una notifica.'),
                type: 'warning',
                channel: 'toast',
                source: 'notifications'
            });
        }
    }, true);

    document.body.addEventListener('htmx:afterSwap', function () {
        initNotificationBulkSelect();
    });

    document.body.addEventListener('click', function (e) {
        var link = e.target.closest('[data-nt-read-url]');
        if (!link) return;

        e.preventDefault();
        var readUrl = link.getAttribute('data-nt-read-url');
        var dest    = link.href;
        var token   = getToken();

        fetch(readUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': token
            },
            body: '_token=' + encodeURIComponent(token)
        }).finally(function () {
            window.location.href = dest;
        });
    });
})();
