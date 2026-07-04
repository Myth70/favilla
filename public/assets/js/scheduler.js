(function () {
    'use strict';

    var state = {
        active: false,
        queue: [],
        index: 0,
        currentJob: null,
        activeButtonId: null,
        mode: 'all',
        tickTimer: 0,
        clearTimer: 0
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function getBatchButtons() {
        return Array.prototype.slice.call(document.querySelectorAll('.sch-batch-run-btn[data-scheduler-batch-mode]'));
    }

    function getButtonById(id) {
        return id ? byId(id) : null;
    }

    function getStatusEl() {
        return byId('scheduler-run-all-status');
    }

    function getJobsTable() {
        return byId('jobs-table');
    }

    function getPollInterval() {
        var table = getJobsTable();
        var value = table ? parseInt(table.getAttribute('data-poll-interval-ms') || '4000', 10) : 4000;

        return value > 0 ? value : 4000;
    }

    function getRunningRows() {
        return Array.prototype.slice.call(
            document.querySelectorAll('[data-scheduler-job-row][data-job-running="1"]')
        );
    }

    function hasRunningJobs() {
        return getRunningRows().length > 0;
    }

    function getRunningLabel() {
        var rows = getRunningRows();

        if (!rows.length) {
            return '';
        }

        if (rows.length === 1) {
            return rows[0].getAttribute('data-job-name') || t('js.scheduler.running_job_fallback', 'il job in corso');
        }

        return t('js.scheduler.jobs_running', ':count job in esecuzione').replace(':count', rows.length);
    }

    function collectQueue(mode) {
        return Array.prototype.slice.call(document.querySelectorAll('.sch-run-job-btn[data-job-id]'))
            .filter(function (button) {
                if (button.getAttribute('data-job-running') === '1') {
                    return false;
                }

                if (button.getAttribute('data-job-enabled') !== '1') {
                    return false;
                }

                if (mode === 'due') {
                    return button.getAttribute('data-job-due') === '1';
                }

                return true;
            })
            .map(function (button) {
                return {
                    id: button.getAttribute('data-job-id'),
                    name: button.getAttribute('data-job-name') || t('js.scheduler.job_fallback_name', 'Job #:id').replace(':id', button.getAttribute('data-job-id'))
                };
            });
    }

    function getActiveButton() {
        return getButtonById(state.activeButtonId);
    }

    function setStatus(message) {
        var statusEl = getStatusEl();

        if (statusEl) {
            statusEl.textContent = message || '';
        }
    }

    function notify(message, type) {
        if (typeof window.notify === 'function') {
            window.notify(message, type || 'info');
        }
    }

    function syncBatchButtons() {
        getBatchButtons().forEach(function (button) {
            var isActiveButton = state.active && button.id === state.activeButtonId;
            var spinner = button.querySelector('.sch-run-all-spinner');
            var icon = button.querySelector('.sch-run-all-icon');
            var label = button.querySelector('.sch-run-all-label');

            button.disabled = state.active;
            button.classList.toggle('is-running', isActiveButton);

            if (spinner) {
                spinner.classList.toggle('d-none', !isActiveButton);
            }

            if (icon) {
                icon.classList.toggle('d-none', isActiveButton);
            }

            if (label) {
                label.textContent = isActiveButton
                    ? (button.getAttribute('data-running-label') || button.getAttribute('data-idle-label') || '')
                    : (button.getAttribute('data-idle-label') || t('js.scheduler.run_label', 'Esegui'));
            }
        });
    }

    function clearTimers() {
        if (state.tickTimer) {
            window.clearTimeout(state.tickTimer);
            state.tickTimer = 0;
        }

        if (state.clearTimer) {
            window.clearTimeout(state.clearTimer);
            state.clearTimer = 0;
        }
    }

    function scheduleTick(delay) {
        if (!state.active) {
            return;
        }

        if (state.tickTimer) {
            window.clearTimeout(state.tickTimer);
        }

        state.tickTimer = window.setTimeout(function () {
            state.tickTimer = 0;
            tick();
        }, delay);
    }

    function focusHistoryPanel(target) {
        var panel = target && target.id === 'job-log-panel' ? target : getJobsTable();
        var card = panel ? panel.querySelector('.sch-log-panel-card') : null;

        if (!panel || !card) {
            return;
        }

        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        window.requestAnimationFrame(function () {
            if (typeof card.focus === 'function') {
                card.focus({ preventScroll: true });
            }
        });
    }

    function finish(summaryMessage) {
        var total = state.queue.length;

        clearTimers();
        state.active = false;
        state.queue = [];
        state.index = 0;
        state.currentJob = null;
        state.activeButtonId = null;
        state.mode = 'all';

        syncBatchButtons();
        setStatus(summaryMessage || '');

        if (summaryMessage) {
            state.clearTimer = window.setTimeout(function () {
                setStatus('');
                state.clearTimer = 0;
            }, 6000);
        }

        if (total > 0) {
            notify(t('js.scheduler.sequence_completed_with_count', 'Sequenza completata. :count job avviati.').replace(':count', total), 'success');
        }
    }

    function launchNextJob() {
        var job;
        var button;

        if (!state.active) {
            return;
        }

        if (state.index >= state.queue.length) {
            if (!hasRunningJobs()) {
                finish(t('js.scheduler.sequence_completed', 'Sequenza completata.'));
            }
            return;
        }

        if (hasRunningJobs()) {
            if (state.index === 0) {
                setStatus(t('js.scheduler.waiting_for', 'In attesa che termini :label.').replace(':label', getRunningLabel()));
            } else {
                setStatus(t('js.scheduler.started_waiting', 'Avviati :done/:total. Attendo :label.')
                    .replace(':done', state.index).replace(':total', state.queue.length).replace(':label', getRunningLabel()));
            }
            scheduleTick(getPollInterval());
            return;
        }

        job = state.queue[state.index];
        button = document.querySelector('.sch-run-job-btn[data-job-id="' + job.id + '"]');

        if (!button) {
            state.index += 1;
            setStatus(t('js.scheduler.skipping', 'Salto :name: azione non disponibile.').replace(':name', job.name));
            scheduleTick(150);
            return;
        }

        state.currentJob = job;
        state.index += 1;
        setStatus(t('js.scheduler.starting', 'Avvio :name (:done/:total)...')
            .replace(':name', job.name).replace(':done', state.index).replace(':total', state.queue.length));
        button.click();
    }

    function tick() {
        if (!state.active) {
            return;
        }

        if (state.index >= state.queue.length && !hasRunningJobs()) {
            finish(t('js.scheduler.sequence_completed', 'Sequenza completata.'));
            return;
        }

        launchNextJob();
    }

    function handleBatchStart(event) {
        var button = event.currentTarget;
        var mode = button.getAttribute('data-scheduler-batch-mode') || 'all';
        var queue = collectQueue(mode);
        var emptyMessage = button.getAttribute('data-empty-message') || t('js.scheduler.empty_message', 'Nessun job disponibile da avviare.');

        if (state.active) {
            return;
        }

        if (!queue.length) {
            notify(emptyMessage, 'info');
            return;
        }

        window.appConfirm({
            title: button.getAttribute('data-confirm-title') || t('js.scheduler.confirm_title', 'Eseguire i job?'),
            body: button.getAttribute('data-confirm-body') || '',
            confirmLabel: button.getAttribute('data-confirm-label') || t('js.scheduler.confirm_label', 'Avvia'),
            confirmClass: 'btn-primary'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            clearTimers();
            state.active = true;
            state.queue = queue;
            state.index = 0;
            state.currentJob = null;
            state.activeButtonId = button.id || null;
            state.mode = mode;

            syncBatchButtons();
            setStatus(t('js.scheduler.queue_ready', 'Coda pronta: :count job.').replace(':count', queue.length));
            tick();
        });
    }

    function bindButtons() {
        getBatchButtons().forEach(function (button) {
            if (button.dataset.schedulerBatchBound === '1') {
                return;
            }

            button.dataset.schedulerBatchBound = '1';
            button.addEventListener('click', handleBatchStart);
        });

        syncBatchButtons();
    }

    document.body.addEventListener('htmx:afterSwap', function (event) {
        var target = event.detail && event.detail.target ? event.detail.target : null;

        if (target && target.id === 'job-log-panel') {
            focusHistoryPanel(target);
            return;
        }

        if (!state.active || !target || target.id !== 'jobs-table') {
            return;
        }

        scheduleTick(150);
    });

    document.body.addEventListener('htmx:responseError', function (event) {
        var source = event.detail && event.detail.elt ? event.detail.elt : null;

        if (!state.active || !source || !source.matches('.sch-run-job-btn')) {
            return;
        }

        notify(t('js.scheduler.start_failed', 'Avvio non riuscito per :name.').replace(':name', source.getAttribute('data-job-name') || t('js.scheduler.selected_job_fallback', 'il job selezionato')), 'warning');
        scheduleTick(250);
    });

    document.body.addEventListener('htmx:sendError', function (event) {
        var source = event.detail && event.detail.elt ? event.detail.elt : null;

        if (!state.active || !source || !source.matches('.sch-run-job-btn')) {
            return;
        }

        notify(t('js.scheduler.network_error_starting', 'Errore di rete durante l\'avvio di :name.').replace(':name', source.getAttribute('data-job-name') || t('js.scheduler.a_job_fallback', 'un job')), 'warning');
        scheduleTick(250);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindButtons);
    } else {
        bindButtons();
    }
}());