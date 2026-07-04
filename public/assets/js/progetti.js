/**
 * Progetti — JavaScript
 * Tab navigation, SortableJS kanban, quick-status, timesheet HTMX.
 */
(function () {
    'use strict';

    /* ── Utility ──────────────────────────────────────────────── */

    function toast(message, type) {
        type = type || 'success';
        if (typeof window.showToast === 'function') { window.showToast(message, type); return; }
        document.body.dispatchEvent(new CustomEvent('notify', { detail: { message: message, type: type } }));
    }

    function reinitTooltips(root) {
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    function htmlFetch(url) {
        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'HX-Request': 'true' }
        }).then(function (r) { return r.text(); });
    }

    function ajaxHtmlFetch(url, options) {
        var headers = { 'X-Requested-With': 'XMLHttpRequest' };
        var cfg = options || {};
        cfg.headers = Object.assign(headers, cfg.headers || {});
        return fetch(url, cfg).then(function (r) {
            return r.text().then(function (html) {
                return { ok: r.ok, status: r.status, html: html, response: r };
            });
        });
    }

    function jsonPost(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, data: d }; });
        });
    }

    function updateColumnCount(itemsEl) {
        var col = itemsEl.closest('.prj-kanban-column');
        if (!col) return;
        var badge = col.querySelector('.prj-kanban-header .badge');
        if (badge) badge.textContent = itemsEl.querySelectorAll('.prj-kanban-card').length;
    }

    // Aggiorna la progress bar dell'hero dopo spostamenti nel kanban
    function updateHeroProgress() {
        var fill = document.querySelector('.prj-hero-progress-fill');
        if (!fill) return;
        var total = document.querySelectorAll('.prj-kanban-card').length;
        var done  = document.querySelectorAll('.prj-kanban-column[data-status="done"] .prj-kanban-card').length;
        var pct   = total > 0 ? Math.round(done / total * 100) : 0;
        fill.setAttribute('data-prj-pct', pct);
        fill.style.setProperty('--prj-pct', pct + '%');
        var pctEl    = document.querySelector('.prj-hero-progress-pct');
        var detailEl = document.querySelector('.prj-hero-progress-detail');
        if (pctEl)    pctEl.textContent    = pct + '%';
        if (detailEl) detailEl.textContent = done + ' / ' + total + ' ' + t('js.progetti.tasks_word', 'attivita');
    }

    // Aggiorna la barra checklist sulla card kanban (o la crea/rimuove)
    function clUpdateKanbanCard(taskId, total, done) {
        var card = document.querySelector('.prj-kanban-card[data-task-id="' + taskId + '"]');
        if (!card) return;
        var bar = card.querySelector('.prj-kanban-checklist-bar');
        if (total > 0) {
            if (!bar) {
                var titleEl = card.querySelector('.prj-kanban-card-title');
                if (titleEl) {
                    bar = document.createElement('div');
                    bar.className = 'prj-kanban-checklist-bar mt-1';
                    bar.setAttribute('data-bs-toggle', 'tooltip');
                    bar.innerHTML = '<div class="progress" style="height:4px;">'
                                  + '<div class="progress-bar bg-success" style="width:0%"></div></div>'
                                  + '<small class="text-muted prj-kanban-cl-label"></small>';
                    titleEl.insertAdjacentElement('afterend', bar);
                }
            }
            if (bar) {
                var pct = Math.round(done / total * 100);
                var pb  = bar.querySelector('.progress-bar');
                var lbl = bar.querySelector('.prj-kanban-cl-label');
                if (pb)  pb.style.width = pct + '%';
                if (lbl) lbl.textContent = done + '/' + total;
                bar.setAttribute('title', t('js.progetti.checklist_tooltip', 'Checklist: :done/:total voci completate').replace(':done', done).replace(':total', total));
                bar.style.display = '';
            }
        } else if (bar) {
            bar.style.display = 'none';
        }
    }

    function applyDynamicStyles(root) {
        var scope = root || document;

        scope.querySelectorAll('[data-prj-pct]').forEach(function (el) {
            var raw = parseFloat(el.getAttribute('data-prj-pct') || '0');
            var val = Number.isFinite(raw) ? Math.max(0, Math.min(100, raw)) : 0;
            el.style.setProperty('--prj-pct', val + '%');
        });

        // Barre semplici nella lista progetti (table.php)
        scope.querySelectorAll('[data-prj-progress]').forEach(function (el) {
            var raw = parseFloat(el.getAttribute('data-prj-progress') || '0');
            var val = Number.isFinite(raw) ? Math.max(0, Math.min(100, raw)) : 0;
            el.style.width = val + '%';
        });

        scope.querySelectorAll('.prj-gantt-grid[data-prj-weeks]').forEach(function (grid) {
            var weeks = parseInt(grid.getAttribute('data-prj-weeks') || '1', 10);
            var minW  = parseInt(grid.getAttribute('data-prj-min-width') || '500', 10);
            weeks = Number.isFinite(weeks) && weeks > 0 ? weeks : 1;
            minW = Number.isFinite(minW) && minW > 0 ? minW : 500;
            grid.style.setProperty('--prj-weeks', String(weeks));
            grid.style.minWidth = minW + 'px';
        });

        scope.querySelectorAll('.prj-gantt-bar[data-prj-offset][data-prj-duration]').forEach(function (bar) {
            var offset = parseInt(bar.getAttribute('data-prj-offset') || '0', 10);
            var dur = parseInt(bar.getAttribute('data-prj-duration') || '1', 10);
            offset = Number.isFinite(offset) && offset >= 0 ? offset : 0;
            dur = Number.isFinite(dur) && dur > 0 ? dur : 1;
            bar.style.setProperty('--prj-offset', String(offset));
            bar.style.setProperty('--prj-duration', String(dur));
        });
    }

    /* ── Projects index list + modals ────────────────────────── */

    var projectsTable = document.getElementById('prj-table');
    var filterWrap = document.getElementById('prj-filters');
    var searchInput = document.getElementById('prj-filter-q');
    var statusInput = document.getElementById('prj-filter-status');
    var editModalEl = document.getElementById('prjEditModal');
    var editModalContent = document.getElementById('prj-edit-modal-content');
    var deleteModalEl = document.getElementById('prjDeleteModal');
    var deleteForm = document.getElementById('prj-delete-form');
    var deleteNameEl = document.getElementById('prj-delete-project-name');
    var editModal = editModalEl ? bootstrap.Modal.getOrCreateInstance(editModalEl) : null;
    var deleteModal = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;
    var filterTimer = null;
    var currentProjectsUrl = window.location.href;

    function buildProjectsListUrl() {
        if (!filterWrap) return currentProjectsUrl;
        var baseUrl = filterWrap.getAttribute('data-prj-search-url') || window.location.href;
        var params = new URLSearchParams();
        if (searchInput && searchInput.value.trim() !== '') params.set('q', searchInput.value.trim());
        if (statusInput && statusInput.value !== '') params.set('status', statusInput.value);
        return baseUrl + (params.toString() ? ('?' + params.toString()) : '');
    }

    function syncFiltersFromUrl(url) {
        if (!filterWrap) return;
        var parsed = new URL(url, window.location.origin);
        if (searchInput) searchInput.value = parsed.searchParams.get('q') || '';
        if (statusInput) statusInput.value = parsed.searchParams.get('status') || '';
    }

    function refreshProjectsTable(url, pushState) {
        if (!projectsTable) return Promise.resolve();
        projectsTable.classList.add('opacity-50');
        return ajaxHtmlFetch(url).then(function (result) {
            if (!result.ok) throw new Error('load_failed');
            projectsTable.innerHTML = result.html;
            currentProjectsUrl = url;
            applyDynamicStyles(projectsTable);
            reinitTooltips(projectsTable);
            if (pushState !== false) {
                window.history.pushState({ prjTable: true, url: url }, '', url);
            }
        }).catch(function () {
            toast(t('js.progetti.load_projects_error', 'Errore nel caricamento dei progetti.'), 'danger');
        }).finally(function () {
            projectsTable.classList.remove('opacity-50');
        });
    }

    function scheduleProjectsRefresh() {
        clearTimeout(filterTimer);
        filterTimer = window.setTimeout(function () {
            refreshProjectsTable(buildProjectsListUrl(), true);
        }, 350);
    }

    function resetEditModalContent() {
        if (!editModalContent) return;
        editModalContent.innerHTML = '<div class="modal-body py-5 text-center text-muted"><i class="fa-solid fa-spinner fa-spin fa-2x d-block mb-3"></i>Caricamento modulo modifica...</div>';
    }

    if (searchInput) {
        searchInput.addEventListener('input', scheduleProjectsRefresh);
    }

    if (statusInput) {
        statusInput.addEventListener('change', function () {
            refreshProjectsTable(buildProjectsListUrl(), true);
        });
    }

    if (editModalEl) {
        editModalEl.addEventListener('hidden.bs.modal', resetEditModalContent);
    }

    var milestoneEditModalEl = document.getElementById('prjMilestoneEditModal');
    var milestoneEditModal = milestoneEditModalEl ? bootstrap.Modal.getOrCreateInstance(milestoneEditModalEl) : null;
    var milestoneEditForm = document.getElementById('prj-milestone-edit-form');
    var taskEditModalEl = document.getElementById('prjTaskEditModal');
    var taskEditModal = taskEditModalEl ? bootstrap.Modal.getOrCreateInstance(taskEditModalEl) : null;
    var taskEditForm = document.getElementById('prj-task-edit-form');
    var confirmActionModalEl = document.getElementById('prjActionConfirmModal');
    var confirmActionModal = confirmActionModalEl ? bootstrap.Modal.getOrCreateInstance(confirmActionModalEl) : null;
    var confirmActionForm = document.getElementById('prj-confirm-modal-form');
    var confirmActionTitle = document.getElementById('prj-confirm-modal-title');
    var confirmActionMessage = document.getElementById('prj-confirm-modal-message');
    var confirmActionSubmit = document.getElementById('prj-confirm-modal-submit');
    var confirmActionMethod = document.getElementById('prj-confirm-modal-method');
    var confirmActionIcon = document.getElementById('prj-confirm-modal-icon');

    /* helper: legge date bound del progetto e le applica a un array di input */
    var prjPage = document.querySelector('[data-prj-start]');
    var prjDateMin = prjPage ? (prjPage.dataset.prjStart || '') : '';
    var prjDateMax = prjPage ? (prjPage.dataset.prjEnd   || '') : '';

    function applyProjectDateBounds(inputs) {
        inputs.forEach(function (inp) {
            if (!inp) return;
            if (prjDateMin) inp.min = prjDateMin;
            if (prjDateMax) inp.max = prjDateMax;
        });
    }

    window.addEventListener('popstate', function () {
        if (!projectsTable || !filterWrap) return;
        syncFiltersFromUrl(window.location.href);
        refreshProjectsTable(window.location.href, false);
    });

    /* ── Tab navigation ───────────────────────────────────────── */

    var tabLinks = document.querySelectorAll('[data-prj-tab]');
    var tabPanes = document.querySelectorAll('.prj-tab-pane');

    tabLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var tabName = this.dataset.prjTab;
            var tabUrl  = this.dataset.prjTabUrl || '';

            // Activate tab link
            tabLinks.forEach(function (l) { l.classList.remove('active'); });
            this.classList.add('active');

            // Show pane
            tabPanes.forEach(function (p) { p.classList.remove('prj-tab-active'); });
            var pane = document.getElementById('prj-pane-' + tabName);
            if (!pane) return;
            pane.classList.add('prj-tab-active');

            // Load content if needed
            if (tabUrl && pane.dataset.loaded !== tabUrl) {
                pane.innerHTML = '<div class="prj-loading py-5"><i class="fa-solid fa-spinner fa-spin fa-2x"></i></div>';
                htmlFetch(tabUrl).then(function (html) {
                    pane.innerHTML = html;
                    pane.dataset.loaded = tabUrl;
                    afterTabLoad(tabName, pane);
                }).catch(function () {
                    pane.innerHTML = '<div class="alert alert-danger m-3">' + t('js.progetti.load_generic_error', 'Errore nel caricamento.') + '</div>';
                });
            } else {
                afterTabLoad(tabName, pane);
            }
        });
    });

    // Attiva tab da URL param ?tab=xxx
    (function () {
        var params = new URLSearchParams(window.location.search);
        var requestedTab = params.get('tab');
        if (requestedTab) {
            var link = document.querySelector('[data-prj-tab="' + requestedTab + '"]');
            if (link) link.click();
        }
    })();

    function afterTabLoad(tabName, pane) {
        if (tabName === 'kanban') initKanbanSortable();
        if (tabName === 'gantt') { initGanttClickHandlers(pane); setTimeout(function() { drawGanttArrows(pane); }, 100); }
        if (tabName === 'dashboard') initKpiCharts();
        applyDynamicStyles(pane);
        reinitTooltips(pane);
    }

    /* ── KPI Charts (ApexCharts) ───────────────────────────────── */

    var kpiChartsInited = false;

    function readChartJson(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent || el.innerHTML); } catch (_) { return null; }
    }

    function apexTheme() {
        var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        return {
            mode: isDark ? 'dark' : 'light',
            foreColor: isDark ? '#adb5bd' : '#6c757d',
            background: 'transparent'
        };
    }

    function initKpiCharts() {
        if (kpiChartsInited) return;
        if (typeof ApexCharts === 'undefined') return;
        kpiChartsInited = true;

        var theme = apexTheme();

        /* ── Donut: Distribuzione task per stato ── */
        var donutEl = document.getElementById('prj-chart-donut-tasks');
        var donutData = readChartJson('prj-chart-task-status');
        if (donutEl && donutData && donutData.series && donutData.series.some(function (v) { return v > 0; })) {
            new ApexCharts(donutEl, {
                chart: { type: 'donut', height: 220, background: theme.background, toolbar: { show: false }, animations: { speed: 400 } },
                theme: { mode: theme.mode },
                series: donutData.series,
                labels: donutData.labels,
                colors: donutData.colors,
                legend: { position: 'bottom', fontSize: '12px', offsetY: 4 },
                dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600 }, dropShadow: { enabled: false } },
                plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Totale', fontSize: '13px', fontWeight: 700, color: theme.foreColor } } } } },
                tooltip: { y: { formatter: function (v) { return v + ' attivita'; } } },
                stroke: { width: 0 }
            }).render();
        }

        /* ── Bar orizzontale: Ore per membro ── */
        var barEl = document.getElementById('prj-chart-bar-users');
        var barData = readChartJson('prj-chart-user-bar');
        if (barEl && barData && barData.names && barData.names.length > 0) {
            var barHeight = Math.max(200, barData.names.length * 42);
            new ApexCharts(barEl, {
                chart: { type: 'bar', height: barHeight, background: theme.background, toolbar: { show: false }, animations: { speed: 400 } },
                theme: { mode: theme.mode },
                series: [{ name: 'Ore', data: barData.hours }],
                xaxis: { categories: barData.names, labels: { style: { fontSize: '12px' } } },
                yaxis: { labels: { formatter: function (v) { return v + ' h'; }, style: { fontSize: '11px' } } },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'top' } } },
                dataLabels: { enabled: true, formatter: function (v) { return v + ' h'; }, offsetX: 6, style: { fontSize: '11px', fontWeight: 600, colors: [theme.foreColor] } },
                colors: ['#0dcaf0'],
                grid: { xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } }, borderColor: 'rgba(128,128,128,0.15)' },
                tooltip: { x: { show: true } }
            }).render();
        }

        /* ── Area: Trend ore settimanale ── */
        var areaEl = document.getElementById('prj-chart-area-trend');
        var trendData = readChartJson('prj-chart-trend');
        if (areaEl && trendData && trendData.dates && trendData.dates.length > 1) {
            new ApexCharts(areaEl, {
                chart: { type: 'area', height: 180, background: theme.background, toolbar: { show: false }, animations: { speed: 400 }, sparkline: { enabled: false } },
                theme: { mode: theme.mode },
                series: [{ name: 'Ore', data: trendData.hours }],
                xaxis: { categories: trendData.dates, labels: { style: { fontSize: '11px' }, rotate: -30 }, tickAmount: Math.min(trendData.dates.length, 10) },
                yaxis: { labels: { formatter: function (v) { return v + ' h'; }, style: { fontSize: '11px' } }, min: 0 },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
                colors: ['#198754'],
                dataLabels: { enabled: false },
                grid: { borderColor: 'rgba(128,128,128,0.15)' },
                markers: { size: 4, strokeWidth: 0, hover: { size: 6 } },
                tooltip: { y: { formatter: function (v) { return v + ' h'; } } }
            }).render();
        }
    }

    /* ── Gantt bar click → open edit modal ─────────────────────── */

    function initGanttClickHandlers(pane) {
        pane.querySelectorAll('.prj-gantt-bar[data-prj-gantt-id]').forEach(function (bar) {
            bar.addEventListener('click', function () {
                var type = this.dataset.prjGanttType;
                var id   = this.dataset.prjGanttId;
                if (!type || !id || id === '0') return;

                if (type === 'task') {
                    var taskBtn = document.querySelector('[data-prj-task-edit="1"][data-prj-task-id="' + id + '"]');
                    if (taskBtn && taskEditModal && taskEditForm) {
                        // Populate and open task edit modal directly (button may be hidden in another tab)
                        taskEditForm.setAttribute('action', window.location.pathname.replace(/\/$/, '') + '/tasks/' + id);
                        var fields = {
                            'prj-task-edit-title': 'data-prj-task-title',
                            'prj-task-edit-description': 'data-prj-task-description',
                            'prj-task-edit-milestone-id': 'data-prj-task-milestone-id',
                            'prj-task-edit-assigned-user-id': 'data-prj-task-assigned-user-id',
                            'prj-task-edit-priority': 'data-prj-task-priority',
                            'prj-task-edit-status': 'data-prj-task-status',
                            'prj-task-edit-start-date': 'data-prj-task-start-date',
                            'prj-task-edit-due-date': 'data-prj-task-due-date',
                            'prj-task-edit-estimated-hours': 'data-prj-task-estimated-hours'
                        };
                        for (var fId in fields) {
                            var el = document.getElementById(fId);
                            if (el) el.value = taskBtn.getAttribute(fields[fId]) || '';
                        }
                        applyProjectDateBounds([
                            document.getElementById('prj-task-edit-start-date'),
                            document.getElementById('prj-task-edit-due-date')
                        ]);
                        loadTaskDeps(id);
                        taskEditModal.show();
                        return;
                    }
                }
                if (type === 'milestone') {
                    var msBtn = document.querySelector('[data-prj-ms-edit="1"][data-prj-ms-id="' + id + '"]');
                    if (msBtn && milestoneEditModal && milestoneEditForm) {
                        milestoneEditForm.setAttribute('action', window.location.pathname.replace(/\/$/, '') + '/milestones/' + id);
                        var msName = document.getElementById('prj-ms-edit-name');
                        var msDesc = document.getElementById('prj-ms-edit-description');
                        var msDue  = document.getElementById('prj-ms-edit-due-date');
                        var msStat = document.getElementById('prj-ms-edit-status');
                        var msBill = document.getElementById('prj-ms-edit-billable');
                        if (msName) msName.value = msBtn.getAttribute('data-prj-ms-name') || '';
                        if (msDesc) msDesc.value = msBtn.getAttribute('data-prj-ms-description') || '';
                        if (msDue)  msDue.value  = msBtn.getAttribute('data-prj-ms-due-date') || '';
                        if (msStat) msStat.value = msBtn.getAttribute('data-prj-ms-status') || 'pending';
                        if (msBill) msBill.checked = (msBtn.getAttribute('data-prj-ms-billable') || '0') === '1';
                        applyProjectDateBounds([msDue]);
                        milestoneEditModal.show();
                        return;
                    }
                }
            });
        });
    }

    /* ── Gantt dependency arrows ──────────────────────────────── */

    function drawGanttArrows(pane) {
        var svg = pane.querySelector('#prj-gantt-arrows');
        if (!svg) return;
        var grid = pane.querySelector('.prj-gantt-grid');
        if (!grid) return;

        var deps = getDepsData();
        if (!deps || Object.keys(deps).length === 0) { svg.innerHTML = ''; return; }

        // Map task IDs to their bar elements
        var bars = {};
        pane.querySelectorAll('.prj-gantt-bar[data-prj-gantt-type="task"][data-prj-gantt-id]').forEach(function (bar) {
            bars[bar.dataset.prjGanttId] = bar;
        });

        var gridRect = grid.getBoundingClientRect();
        svg.setAttribute('width', grid.scrollWidth);
        svg.setAttribute('height', grid.scrollHeight);
        svg.style.width = grid.scrollWidth + 'px';
        svg.style.height = grid.scrollHeight + 'px';

        var paths = '';
        var markerDef = '<defs><marker id="prj-arrow-head" markerWidth="8" markerHeight="6" refX="8" refY="3" orient="auto">'
            + '<polygon points="0 0, 8 3, 0 6" fill="var(--bs-secondary, #6c757d)" opacity="0.6"/>'
            + '</marker></defs>';

        Object.keys(deps).forEach(function (successorId) {
            var succBar = bars[successorId];
            if (!succBar) return;
            var preds = deps[successorId];
            preds.forEach(function (predId) {
                var predBar = bars[predId];
                if (!predBar) return;

                var predRect = predBar.getBoundingClientRect();
                var succRect = succBar.getBoundingClientRect();

                // Coordinates relative to grid
                var x1 = predRect.right - gridRect.left;
                var y1 = predRect.top + predRect.height / 2 - gridRect.top;
                var x2 = succRect.left - gridRect.left;
                var y2 = succRect.top + succRect.height / 2 - gridRect.top;

                // Curved path
                var dx = Math.abs(x2 - x1);
                var cp = Math.max(20, dx * 0.4);
                paths += '<path d="M' + x1 + ',' + y1 + ' C' + (x1 + cp) + ',' + y1 + ' ' + (x2 - cp) + ',' + y2 + ' ' + x2 + ',' + y2 + '" '
                    + 'fill="none" stroke="var(--bs-secondary, #6c757d)" stroke-width="1.5" stroke-opacity="0.5" '
                    + 'marker-end="url(#prj-arrow-head)" />';
            });
        });

        svg.innerHTML = markerDef + paths;
    }

    /* ── Force-reload a tab ───────────────────────────────────── */

    function reloadTab(tabName) {
        var pane = document.getElementById('prj-pane-' + tabName);
        if (pane) pane.dataset.loaded = '';
        var link = document.querySelector('[data-prj-tab="' + tabName + '"]');
        if (link) link.click();
    }

    /* ── Kanban SortableJS ────────────────────────────────────── */

    function initKanbanSortable() {
        var container = document.querySelector('.prj-kanban-container');
        if (!container || typeof Sortable === 'undefined') return;

        var moveUrl     = container.dataset.prjMoveUrl || '';
        var csrf        = container.dataset.prjCsrf || '';
        var canEdit     = container.dataset.prjCanEdit === '1';
        var currentUser = parseInt(container.dataset.prjCurrentUser || '0', 10);

        container.querySelectorAll('.prj-kanban-items').forEach(function (el) {
            if (el._sortable) el._sortable.destroy();
            el._sortable = Sortable.create(el, {
                group: 'prj-kanban',
                animation: 180,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                draggable: '.prj-kanban-card',
                filter: function (evt, target) {
                    if (canEdit) return false; // editor può trascinare tutto
                    var assignedUser = parseInt(target.dataset.assignedUser || '0', 10);
                    return assignedUser !== currentUser; // blocca card non proprie
                },
                preventOnFilter: false,
                delayOnTouchOnly: true,
                delay: window.matchMedia('(max-width: 576px)').matches ? 140 : 0,
                touchStartThreshold: 4,
                fallbackOnBody: true,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                onEnd: function (evt) {
                    var card = evt.item;
                    var taskId = card.dataset.taskId;
                    var newStatus = evt.to.dataset.status;
                    var newPos = Array.prototype.indexOf.call(evt.to.children, card);

                    updateColumnCount(evt.from);
                    updateColumnCount(evt.to);

                    if (!moveUrl || !taskId) return;
                    var url = moveUrl.replace('__TID__', taskId);
                    var fd = new FormData();
                    fd.append('_token', csrf);
                    fd.append('status', newStatus);
                    fd.append('position', newPos);

                    jsonPost(url, fd).then(function (r) {
                        if (!r.ok) {
                            toast(r.data.error || t('js.progetti.move_error', 'Errore spostamento'), 'danger');
                            reloadTab('kanban');
                        } else {
                            updateHeroProgress();
                        }
                    }).catch(function () {
                        toast(t('js.progetti.network_error', 'Errore di rete'), 'danger');
                        reloadTab('kanban');
                    });
                }
            });
        });
    }

    /* ── Quick-status selects in kanban ────────────────────────── */

    document.addEventListener('change', function (e) {
        var sel = e.target.closest('.prj-quick-status');
        if (!sel) return;

        var url  = sel.dataset.url;
        var csrf = sel.dataset.csrf;
        if (!url || !csrf) return;

        var fd = new FormData();
        fd.append('status', sel.value);
        fd.append('_token', csrf);

        jsonPost(url, fd).then(function (r) {
            if (!r.ok) {
                toast(r.data.error || t('js.progetti.generic_error', 'Errore'), 'danger');
                reloadTab('kanban');
            } else {
                reloadTab('kanban');
            }
        }).catch(function () {
            toast(t('js.progetti.network_error', 'Errore di rete'), 'danger');
        });
    });

    /* ── Timesheet HTMX refresh ───────────────────────────────── */

    document.body.addEventListener('htmx:afterRequest', function (evt) {
        var xhr = evt.detail && evt.detail.xhr;
        if (!xhr) return;
        var trigger = xhr.getResponseHeader('HX-Trigger');
        if (trigger && trigger.indexOf('prjTimesheetRefresh') !== -1) {
            reloadTab('timesheet');
        }
    });

    /* ── Timesheet inline edit toggle ────────────────────────── */

    document.addEventListener('click', function (e) {
        var tsEditBtn = e.target.closest('[data-prj-ts-edit-id]');
        if (tsEditBtn) {
            var rowId = tsEditBtn.getAttribute('data-prj-ts-edit-id');
            var editRow = document.getElementById(rowId);
            if (editRow) editRow.classList.toggle('d-none');
            return;
        }
    });

    /* ── Collapsible add forms ────────────────────────────────── */

    document.addEventListener('click', function (e) {
        var tableLink = e.target.closest('[data-prj-table-link="1"]');
        if (tableLink && projectsTable) {
            e.preventDefault();
            refreshProjectsTable(tableLink.getAttribute('href'), true);
            return;
        }

        var editBtn = e.target.closest('[data-prj-edit-url]');
        if (editBtn && editModal && editModalContent) {
            e.preventDefault();
            resetEditModalContent();
            editModal.show();
            ajaxHtmlFetch(editBtn.getAttribute('data-prj-edit-url')).then(function (result) {
                if (!result.ok) throw new Error('edit_load_failed');
                editModalContent.innerHTML = result.html;
                reinitTooltips(editModalContent);
            }).catch(function () {
                editModalContent.innerHTML = '<div class="modal-body"><div class="alert alert-danger mb-0">' + t('js.progetti.load_edit_form_error', 'Errore nel caricamento del form di modifica.') + '</div></div>';
            });
            return;
        }

        var deleteBtn = e.target.closest('[data-prj-delete-url]');
        if (deleteBtn && deleteModal && deleteForm) {
            e.preventDefault();
            deleteForm.setAttribute('action', deleteBtn.getAttribute('data-prj-delete-url') || '');
            if (deleteNameEl) {
                deleteNameEl.textContent = deleteBtn.getAttribute('data-prj-delete-name') || 'selezionato';
            }
            deleteModal.show();
            return;
        }

        var milestoneEditBtn = e.target.closest('[data-prj-ms-edit="1"]');
        if (milestoneEditBtn && milestoneEditModal && milestoneEditForm) {
            e.preventDefault();
            milestoneEditForm.setAttribute('action', window.location.pathname.replace(/\/$/, '') + '/milestones/' + (milestoneEditBtn.getAttribute('data-prj-ms-id') || '0'));
            var msName = document.getElementById('prj-ms-edit-name');
            var msDescription = document.getElementById('prj-ms-edit-description');
            var msDueDate = document.getElementById('prj-ms-edit-due-date');
            var msStatus = document.getElementById('prj-ms-edit-status');
            var msBillable = document.getElementById('prj-ms-edit-billable');
            if (msName) msName.value = milestoneEditBtn.getAttribute('data-prj-ms-name') || '';
            if (msDescription) msDescription.value = milestoneEditBtn.getAttribute('data-prj-ms-description') || '';
            if (msDueDate) msDueDate.value = milestoneEditBtn.getAttribute('data-prj-ms-due-date') || '';
            if (msStatus) msStatus.value = milestoneEditBtn.getAttribute('data-prj-ms-status') || 'pending';
            if (msBillable) msBillable.checked = (milestoneEditBtn.getAttribute('data-prj-ms-billable') || '0') === '1';
            applyProjectDateBounds([msDueDate]);
            milestoneEditModal.show();
            return;
        }

        var taskEditBtn = e.target.closest('[data-prj-task-edit="1"]');
        if (taskEditBtn && taskEditModal && taskEditForm) {
            e.preventDefault();
            taskEditForm.setAttribute('action', window.location.pathname.replace(/\/$/, '') + '/tasks/' + (taskEditBtn.getAttribute('data-prj-task-id') || '0'));
            var taskTitle = document.getElementById('prj-task-edit-title');
            var taskDescription = document.getElementById('prj-task-edit-description');
            var taskMilestone = document.getElementById('prj-task-edit-milestone-id');
            var taskAssigned = document.getElementById('prj-task-edit-assigned-user-id');
            var taskPriority = document.getElementById('prj-task-edit-priority');
            var taskStatus = document.getElementById('prj-task-edit-status');
            var taskStartDate = document.getElementById('prj-task-edit-start-date');
            var taskDueDate = document.getElementById('prj-task-edit-due-date');
            var taskHours = document.getElementById('prj-task-edit-estimated-hours');
            if (taskTitle) taskTitle.value = taskEditBtn.getAttribute('data-prj-task-title') || '';
            if (taskDescription) taskDescription.value = taskEditBtn.getAttribute('data-prj-task-description') || '';
            if (taskMilestone) taskMilestone.value = taskEditBtn.getAttribute('data-prj-task-milestone-id') || '';
            if (taskAssigned) taskAssigned.value = taskEditBtn.getAttribute('data-prj-task-assigned-user-id') || '';
            if (taskPriority) taskPriority.value = taskEditBtn.getAttribute('data-prj-task-priority') || 'medium';
            if (taskStatus) taskStatus.value = taskEditBtn.getAttribute('data-prj-task-status') || 'todo';
            if (taskStartDate) taskStartDate.value = taskEditBtn.getAttribute('data-prj-task-start-date') || '';
            if (taskDueDate) taskDueDate.value = taskEditBtn.getAttribute('data-prj-task-due-date') || '';
            if (taskHours) taskHours.value = taskEditBtn.getAttribute('data-prj-task-estimated-hours') || '0';
            applyProjectDateBounds([taskStartDate, taskDueDate]);
            loadTaskDeps(taskEditBtn.getAttribute('data-prj-task-id') || '0');
            taskEditModal.show();
            return;
        }

        var confirmActionBtn = e.target.closest('[data-prj-confirm-action="1"]');
        if (confirmActionBtn && confirmActionModal && confirmActionForm) {
            e.preventDefault();
            confirmActionForm.setAttribute('action', confirmActionBtn.getAttribute('data-prj-confirm-action-url') || '');
            if (confirmActionTitle) confirmActionTitle.textContent = confirmActionBtn.getAttribute('data-prj-confirm-title') || 'Conferma azione';
            if (confirmActionMessage) confirmActionMessage.textContent = confirmActionBtn.getAttribute('data-prj-confirm-message') || 'Conferma l\'operazione richiesta.';
            if (confirmActionSubmit) confirmActionSubmit.textContent = confirmActionBtn.getAttribute('data-prj-confirm-submit') || 'Conferma';
            if (confirmActionMethod) confirmActionMethod.value = confirmActionBtn.getAttribute('data-prj-confirm-method') || 'POST';
            if (confirmActionSubmit) {
                confirmActionSubmit.classList.remove('btn-danger', 'btn-warning', 'btn-success', 'btn-primary', 'btn-secondary');
                confirmActionSubmit.classList.add(confirmActionBtn.getAttribute('data-prj-confirm-submit-class') || 'btn-danger');
            }
            if (confirmActionIcon) {
                confirmActionIcon.className = 'fa-solid text-danger me-2 ' + (confirmActionBtn.getAttribute('data-prj-confirm-icon') || 'fa-triangle-exclamation');
            }
            confirmActionModal.show();
            return;
        }

        var btn = e.target.closest('[data-prj-toggle-form]');
        if (!btn) return;
        e.preventDefault();
        var target = document.getElementById(btn.dataset.prjToggleForm);
        if (target) {
            target.classList.toggle('d-none');
            var icon = btn.querySelector('i.fa-chevron-down, i.fa-chevron-up');
            if (icon) icon.classList.toggle('fa-chevron-down'), icon.classList.toggle('fa-chevron-up');
        }
    });

    /* ── Tooltip + afterSwap ──────────────────────────────────── */

    document.body.addEventListener('htmx:afterSwap', function (e) {
        applyDynamicStyles(e.detail.target);
        reinitTooltips(e.detail.target);
    });

    document.addEventListener('submit', function (e) {
        function parsePayload(result) {
            try {
                return JSON.parse(result.html || '{}');
            } catch (_) {
                return {};
            }
        }

        var editForm = e.target.closest('form[data-prj-edit-form="1"]');
        if (editForm) {
            e.preventDefault();
            var submitBtn = editForm.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            ajaxHtmlFetch(editForm.getAttribute('action') || '', {
                method: 'POST',
                body: new FormData(editForm)
            }).then(function (result) {
                var contentType = result.response.headers.get('Content-Type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    var payload = parsePayload(result);
                    if (!result.ok || !payload.success) throw new Error(payload.message || 'update_failed');
                    if (editModal) editModal.hide();
                    toast(payload.message || t('js.progetti.project_updated', 'Progetto aggiornato.'), 'success');
                    return refreshProjectsTable(currentProjectsUrl || buildProjectsListUrl(), false);
                }

                editModalContent.innerHTML = result.html;
                reinitTooltips(editModalContent);
                applyDynamicStyles(editModalContent);
                if (result.ok) {
                    if (editModal) editModal.hide();
                    return refreshProjectsTable(currentProjectsUrl || buildProjectsListUrl(), false);
                }
                return null;
            }).catch(function (err) {
                toast(err && err.message ? err.message : t('js.progetti.project_update_error', "Errore durante l'aggiornamento del progetto."), 'danger');
            }).finally(function () {
                if (submitBtn) submitBtn.disabled = false;
            });
            return;
        }

        var projectDeleteForm = e.target.closest('#prj-delete-form');
        if (projectDeleteForm) {
            e.preventDefault();
            var deleteBtn = projectDeleteForm.querySelector('button[type="submit"]');
            if (deleteBtn) deleteBtn.disabled = true;
            ajaxHtmlFetch(projectDeleteForm.getAttribute('action') || '', {
                method: 'POST',
                body: new FormData(projectDeleteForm)
            }).then(function (result) {
                var payload = parsePayload(result);
                if (!result.ok || !payload.success) throw new Error(payload.message || 'delete_failed');
                if (deleteModal) deleteModal.hide();
                toast(payload.message || t('js.progetti.project_deleted', 'Progetto eliminato.'), 'success');
                return refreshProjectsTable(currentProjectsUrl || buildProjectsListUrl(), false);
            }).catch(function (err) {
                toast(err && err.message ? err.message : t('js.progetti.project_delete_error', "Errore durante l'eliminazione del progetto."), 'danger');
            }).finally(function () {
                if (deleteBtn) deleteBtn.disabled = false;
            });
            return;
        }

        var milestoneModalForm = e.target.closest('#prj-milestone-edit-form');
        if (milestoneModalForm) {
            e.preventDefault();
            var milestoneSubmit = milestoneModalForm.querySelector('button[type="submit"]');
            if (milestoneSubmit) milestoneSubmit.disabled = true;
            ajaxHtmlFetch(milestoneModalForm.getAttribute('action') || '', {
                method: 'POST',
                body: new FormData(milestoneModalForm)
            }).then(function (result) {
                var payload = parsePayload(result);
                if (!result.ok || !payload.success) throw new Error(payload.message || 'update_milestone_failed');
                if (milestoneEditModal) milestoneEditModal.hide();
                toast(payload.message || t('js.progetti.milestone_updated', 'Milestone aggiornata.'), 'success');
                window.location.reload();
            }).catch(function (err) {
                toast(err && err.message ? err.message : t('js.progetti.milestone_update_error', 'Errore durante aggiornamento milestone.'), 'danger');
            }).finally(function () {
                if (milestoneSubmit) milestoneSubmit.disabled = false;
            });
            return;
        }

        var taskModalForm = e.target.closest('#prj-task-edit-form');
        if (taskModalForm) {
            e.preventDefault();
            var taskSubmit = taskModalForm.querySelector('button[type="submit"]');
            if (taskSubmit) taskSubmit.disabled = true;
            ajaxHtmlFetch(taskModalForm.getAttribute('action') || '', {
                method: 'POST',
                body: new FormData(taskModalForm)
            }).then(function (result) {
                var payload = parsePayload(result);
                if (!result.ok || !payload.success) throw new Error(payload.message || 'update_task_failed');
                if (taskEditModal) taskEditModal.hide();
                toast(payload.message || t('js.progetti.task_updated', 'Attivita aggiornata.'), 'success');
                window.location.reload();
            }).catch(function (err) {
                toast(err && err.message ? err.message : t('js.progetti.task_update_error', "Errore durante l'aggiornamento dell'attivita."), 'danger');
            }).finally(function () {
                if (taskSubmit) taskSubmit.disabled = false;
            });
            return;
        }

        var confirmModalForm = e.target.closest('#prj-confirm-modal-form');
        if (confirmModalForm) {
            if (confirmModalForm.hasAttribute('data-prj-no-ajax')) {
                return;
            }
            e.preventDefault();
            var confirmSubmitBtn = confirmModalForm.querySelector('button[type="submit"]');
            if (confirmSubmitBtn) confirmSubmitBtn.disabled = true;
            ajaxHtmlFetch(confirmModalForm.getAttribute('action') || '', {
                method: 'POST',
                body: new FormData(confirmModalForm)
            }).then(function (result) {
                var payload = parsePayload(result);
                if (!result.ok || !payload.success) throw new Error(payload.message || 'action_failed');
                if (confirmActionModal) confirmActionModal.hide();
                toast(payload.message || t('js.progetti.operation_completed', 'Operazione completata.'), 'success');
                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }
                window.location.reload();
            }).catch(function (err) {
                toast(err && err.message ? err.message : t('js.progetti.operation_error', "Errore durante l'operazione."), 'danger');
            }).finally(function () {
                if (confirmSubmitBtn) confirmSubmitBtn.disabled = false;
            });
        }
    });

    /* ── Notify listener ──────────────────────────────────────── */

    document.body.addEventListener('notify', function (e) {
        var d = e.detail || {};
        if (!d.message) return;
        if (typeof window.showToast === 'function') { window.showToast(d.message, d.type || 'success'); return; }
        var el = document.createElement('div');
        el.className = 'alert alert-' + (d.type || 'success') + ' alert-dismissible fade show position-fixed prj-toast-floating';
        el.innerHTML = d.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(function () { if (el.parentNode) { el.classList.remove('show'); setTimeout(function () { el.remove(); }, 150); } }, 3000);
    });

    /* ── Click row to open project ───────────────────────────── */

    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr.prj-click-row[data-href]');
        if (!row) return;

        // Ignore clicks on interactive controls inside the row.
        if (e.target.closest('a, button, input, select, textarea, label, form, [data-no-row-nav]')) {
            return;
        }

        var href = row.getAttribute('data-href');
        if (href) window.location.href = href;
    });

    /* ── Dependencies management ─────────────────────────────── */

    var depListEl = document.getElementById('prj-dep-list');
    var depAddSelect = document.getElementById('prj-dep-add-select');
    var depAddBtn = document.getElementById('prj-dep-add-btn');
    var currentEditTaskId = null;

    function getDepsData() {
        var page = document.querySelector('[data-prj-deps]');
        if (!page) return {};
        try { return JSON.parse(page.dataset.prjDeps || '{}'); } catch(e) { return {}; }
    }

    function getTaskTitles() {
        var page = document.querySelector('[data-prj-task-titles]');
        if (!page) return {};
        try { return JSON.parse(page.dataset.prjTaskTitles || '{}'); } catch(e) { return {}; }
    }

    function loadTaskDeps(taskId) {
        currentEditTaskId = taskId;
        if (!depListEl) return;
        var deps = getDepsData();
        var titles = getTaskTitles();
        var preds = deps[taskId] || [];
        var projectId = (document.querySelector('[data-project-id]') || {}).dataset.projectId || '0';

        if (preds.length === 0) {
            depListEl.innerHTML = '<span class="text-muted small">Nessuna dipendenza</span>';
        } else {
            var html = '';
            preds.forEach(function (predId) {
                var title = titles[predId] || ('Attivita #' + predId);
                html += '<span class="badge bg-light text-dark border me-1 mb-1 prj-dep-badge">'
                    + '<i class="fa-solid fa-link me-1 text-muted"></i>'
                    + title
                    + ' <button type="button" class="btn-close btn-close-sm ms-1" '
                    + 'data-prj-dep-remove="1" data-prj-dep-pred="' + predId + '" '
                    + 'data-prj-dep-task="' + taskId + '" '
                    + 'data-prj-dep-project="' + projectId + '" '
                    + 'aria-label="Rimuovi"></button>'
                    + '</span>';
            });
            depListEl.innerHTML = html;
        }

        // Filter the select to exclude self and existing predecessors
        if (depAddSelect) {
            var opts = depAddSelect.querySelectorAll('option');
            opts.forEach(function (opt) {
                if (!opt.value) return;
                var id = parseInt(opt.value, 10);
                opt.hidden = (id === parseInt(taskId, 10) || preds.indexOf(id) !== -1);
            });
        }
    }

    function updateDepsJson(taskId, newPreds) {
        var page = document.querySelector('[data-prj-deps]');
        if (!page) return;
        var deps = getDepsData();
        deps[taskId] = newPreds;
        page.dataset.prjDeps = JSON.stringify(deps);
    }

    if (depAddBtn) {
        depAddBtn.addEventListener('click', function () {
            if (!depAddSelect || !currentEditTaskId) return;
            var predId = parseInt(depAddSelect.value, 10);
            if (!predId) return;
            var projectId = (document.querySelector('[data-project-id]') || {}).dataset.projectId || '0';
            var csrfInput = document.querySelector('#prj-task-edit-form input[name="_token"]');
            var csrf = csrfInput ? csrfInput.value : '';
            var fd = new FormData();
            fd.append('predecessor_task_id', predId);
            fd.append('_token', csrf);

            depAddBtn.disabled = true;
            jsonPost(window.location.pathname.replace(/\/$/, '') + '/tasks/' + currentEditTaskId + '/dependencies', fd)
                .then(function (res) {
                    if (res.ok && res.data.ok) {
                        var deps = getDepsData();
                        var preds = deps[currentEditTaskId] || [];
                        preds.push(predId);
                        updateDepsJson(currentEditTaskId, preds);
                        loadTaskDeps(currentEditTaskId);
                        toast(res.data.message || t('js.progetti.dependency_added', 'Dipendenza aggiunta.'), 'success');
                    } else {
                        toast(res.data.error || t('js.progetti.generic_error_dot', 'Errore.'), 'danger');
                    }
                })
                .catch(function () { toast(t('js.progetti.network_error_dot', 'Errore di rete.'), 'danger'); })
                .finally(function () { depAddBtn.disabled = false; });
        });
    }

    if (depListEl) {
        depListEl.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('[data-prj-dep-remove]');
            if (!removeBtn) return;
            var predId = removeBtn.dataset.prjDepPred;
            var taskId = removeBtn.dataset.prjDepTask;
            var projectId = removeBtn.dataset.prjDepProject;
            var csrfInput = document.querySelector('#prj-task-edit-form input[name="_token"]');
            var csrf = csrfInput ? csrfInput.value : '';
            var fd = new FormData();
            fd.append('_method', 'DELETE');
            fd.append('_token', csrf);

            fetch(window.location.pathname.replace(/\/$/, '') + '/tasks/' + taskId + '/dependencies/' + predId, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  if (data.ok) {
                      var deps = getDepsData();
                      var preds = (deps[taskId] || []).filter(function (p) { return p !== parseInt(predId, 10); });
                      updateDepsJson(taskId, preds);
                      loadTaskDeps(taskId);
                      toast(data.message || t('js.progetti.dependency_removed', 'Dipendenza rimossa.'), 'success');
                  } else {
                      toast(data.error || t('js.progetti.generic_error_dot', 'Errore.'), 'danger');
                  }
              })
              .catch(function () { toast(t('js.progetti.network_error_dot', 'Errore di rete.'), 'danger'); });
        });
    }

    /* ── Init ─────────────────────────────────────────────────── */

    applyDynamicStyles(document);
    reinitTooltips();

    // Inizializza i grafici KPI se il tab dashboard è già attivo al caricamento
    if (document.getElementById('prj-pane-dashboard') &&
        document.getElementById('prj-pane-dashboard').classList.contains('prj-tab-active')) {
        initKpiCharts();
    }

    /* ── Checklist ────────────────────────────────────────────── */

    var clContainer      = document.getElementById('prj-checklist-container');
    var clBadge          = document.getElementById('prj-checklist-badge');
    var clChecklistTabBtn = document.getElementById('prjTaskChecklistTabBtn');
    var clMarkDoneBtn    = document.getElementById('prj-task-mark-done-btn');
    var clSortable       = null;   // SortableJS instance
    var clLoadedForTask  = null;   // which task is currently loaded

    function clEsc(s) {
        return String(s || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function clGetCsrf() {
        var el = document.querySelector('#prj-task-edit-form input[name="_token"]');
        return el ? el.value : '';
    }

    function clBuildUrl(tpl, taskId) {
        return tpl.replace(/__TID__/g, taskId);
    }

    // ── Aggiorna progress bar + badge ────────────────────────────
    function clUpdateStats(container, total, done) {
        var bar  = container.querySelector('.prj-cl-progress-bar');
        var stat = container.querySelector('.prj-cl-stat-text');
        if (bar)  bar.style.width = (total > 0 ? Math.round(done / total * 100) : 0) + '%';
        if (stat) stat.textContent = done + '/' + total + ' ' + t('js.progetti.completed_word', 'completate');
        if (clBadge) {
            clBadge.textContent = done + '/' + total;
            clBadge.classList.toggle('d-none', total === 0);
            clBadge.className = clBadge.className.replace(/bg-\S+/, '');
            clBadge.classList.add(total > 0 && done === total ? 'bg-success' : 'bg-secondary');
        }
        if (clMarkDoneBtn) {
            clMarkDoneBtn.classList.toggle('d-none', !(total > 0 && done === total));
        }
    }

    // ── Costruisce HTML di un singolo item ───────────────────────
    function clBuildItemHtml(item, canManage) {
        var isDone   = parseInt(item.is_done, 10) === 1;
        var id       = parseInt(item.id, 10);
        var label    = clEsc(item.label);
        var doneName = clEsc(item.done_by_name || '');
        var doneAt   = item.done_at ? item.done_at.substring(0, 10) : '';
        var comment  = clEsc(item.comment || '');
        var canDrag  = canManage && !isDone;

        var html = '<li class="prj-cl-item d-flex align-items-start gap-2 py-2 border-bottom" data-item-id="' + id + '" data-done="' + (isDone ? 1 : 0) + '">';

        // Drag handle
        html += '<span class="prj-cl-drag-handle text-muted mt-1 flex-shrink-0' + (canDrag ? '' : ' invisible') + '" style="cursor:grab"><i class="fa-solid fa-grip-vertical"></i></span>';

        // Checkbox
        html += '<div class="form-check mt-1 flex-shrink-0">';
        html += '<input type="checkbox" class="form-check-input prj-cl-check" id="prj-cl-check-' + id + '"';
        if (isDone) html += ' checked disabled';
        html += '>';
        html += '</div>';

        // Corpo voce
        html += '<div class="flex-grow-1" style="min-width:0">';
        html += '<label class="form-check-label' + (isDone ? ' text-decoration-line-through text-muted' : '') + '" for="prj-cl-check-' + id + '">' + label + '</label>';

        // Info se completata
        if (isDone) {
            html += '<div class="small text-muted mt-1">';
            if (doneName) html += '<i class="fa-solid fa-user me-1"></i>' + doneName;
            if (doneAt)   html += (doneName ? ' &middot; ' : '') + '<i class="fa-regular fa-calendar me-1"></i>' + doneAt;
            if (comment)  html += '<div class="fst-italic mt-1">&ldquo;' + comment + '&rdquo;</div>';
            html += '</div>';
        }

        // Area commento (visibile al clic se non done)
        if (!isDone) {
            html += '<div class="prj-cl-comment-area mt-1 d-none">';
            html += '<textarea class="form-control form-control-sm prj-cl-comment-input" rows="2" placeholder="Commento..."></textarea>';
            html += '<div class="d-flex gap-1 mt-1">';
            html += '<button type="button" class="btn btn-success btn-sm prj-cl-confirm-check"><i class="fa-solid fa-check me-1"></i>Conferma</button>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm prj-cl-cancel-check">Annulla</button>';
            html += '</div></div>';
        }

        html += '</div>';

        // Bottone elimina (solo se canManage e non done)
        if (canManage && !isDone) {
            html += '<button type="button" class="btn btn-outline-danger btn-sm border-0 prj-cl-delete flex-shrink-0" data-item-id="' + id + '" data-bs-toggle="tooltip" title="Elimina voce"><i class="fa-solid fa-trash"></i></button>';
        } else {
            html += '<span class="flex-shrink-0" style="width:28px"></span>';
        }

        html += '</li>';
        return html;
    }

    // ── Renderizza la checklist completa ─────────────────────────
    function clRender(container, data) {
        var items      = data.items || [];
        var canManage  = !!data.canManage;
        var stats      = data.stats || { total: 0, done: 0 };
        var templates  = data.templates || [];

        var html = '<div class="prj-cl-panel p-3">';

        // Progress bar
        var pct = stats.total > 0 ? Math.round(stats.done / stats.total * 100) : 0;
        html += '<div class="d-flex align-items-center gap-2 mb-3">';
        html += '<div class="progress flex-grow-1" style="height:8px"><div class="progress-bar bg-success prj-cl-progress-bar" style="width:' + pct + '%"></div></div>';
        html += '<small class="text-muted text-nowrap prj-cl-stat-text">' + stats.done + '/' + stats.total + ' ' + t('js.progetti.completed_word', 'completate') + '</small>';
        html += '</div>';

        // Lista voci
        if (items.length === 0) {
            html += '<p class="text-muted small mb-3">Nessuna voce nella checklist.</p>';
        } else {
            html += '<ul id="prj-cl-list" class="list-unstyled mb-3">';
            items.forEach(function (item) { html += clBuildItemHtml(item, canManage); });
            html += '</ul>';
        }

        // Aggiungi voce + template (solo canManage)
        if (canManage) {
            html += '<div class="prj-cl-add-area">';
            html += '<div class="input-group input-group-sm mb-2">';
            html += '<input type="text" class="form-control" id="prj-cl-new-label" placeholder="Nuova voce checklist..." maxlength="500">';
            html += '<button type="button" class="btn btn-primary" id="prj-cl-add-btn" data-bs-toggle="tooltip" title="Aggiungi voce"><i class="fa-solid fa-plus"></i></button>';
            html += '</div>';

            if (templates.length > 0) {
                html += '<div class="input-group input-group-sm">';
                html += '<select class="form-select form-select-sm" id="prj-cl-tpl-select"><option value="">Applica un modello...</option>';
                templates.forEach(function (t) {
                    html += '<option value="' + parseInt(t.id, 10) + '">' + clEsc(t.name) + '</option>';
                });
                html += '</select>';
                html += '<button type="button" class="btn btn-outline-secondary" id="prj-cl-apply-tpl-btn" data-bs-toggle="tooltip" title="Applica modello"><i class="fa-solid fa-wand-magic-sparkles"></i></button>';
                html += '</div>';
            }
            html += '</div>';
        }

        html += '</div>';
        container.innerHTML = html;

        // Init Sortable (solo se canManage e ci sono items)
        if (clSortable) { try { clSortable.destroy(); } catch(e) {} clSortable = null; }
        var listEl = container.querySelector('#prj-cl-list');
        if (listEl && canManage && typeof Sortable !== 'undefined') {
            clSortable = Sortable.create(listEl, {
                animation: 150,
                handle: '.prj-cl-drag-handle',
                draggable: '.prj-cl-item[data-done="0"]',
                onEnd: function () {
                    var ids = Array.from(listEl.querySelectorAll('.prj-cl-item')).map(function (el) {
                        return parseInt(el.dataset.itemId, 10);
                    });
                    var urlTpl  = clContainer ? clContainer.dataset.checklistUrl : '';
                    var url     = clBuildUrl(urlTpl, currentEditTaskId) + '/reorder';
                    var csrf    = clGetCsrf();
                    var fd      = new FormData();
                    fd.append('_token', csrf);
                    ids.forEach(function (id) { fd.append('order[]', id); });
                    jsonPost(url, fd).then(function (r) {
                        if (!r.ok) toast(r.data.error || t('js.progetti.reorder_error', 'Errore nel riordinamento.'), 'danger');
                    });
                }
            });
        }

        reinitTooltips(container);
        clUpdateStats(container, stats.total, stats.done);
    }

    // ── Carica checklist dal server ──────────────────────────────
    function clLoad(taskId) {
        if (!clContainer) return;
        if (clLoadedForTask === taskId) return;
        clLoadedForTask = taskId;
        clContainer.innerHTML = '<div class="text-center text-muted py-5"><i class="fa-solid fa-spinner fa-spin fa-lg"></i></div>';
        if (clMarkDoneBtn) { clMarkDoneBtn.classList.add('d-none'); clMarkDoneBtn.dataset.taskId = taskId; }

        var urlTpl = clContainer.dataset.checklistUrl || '';
        var url    = clBuildUrl(urlTpl, taskId);

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) throw new Error(data.error || t('js.progetti.checklist_load_error', 'Errore caricamento checklist.'));
                clRender(clContainer, data);
                if (clMarkDoneBtn) clMarkDoneBtn.dataset.taskId = taskId;
            })
            .catch(function (err) {
                clContainer.innerHTML = '<div class="alert alert-danger m-3">' + clEsc(err.message) + '</div>';
            });
    }

    // ── Quando si apre il modal task: reset stato checklist ──────
    if (taskEditModalEl) {
        taskEditModalEl.addEventListener('show.bs.modal', function () {
            clLoadedForTask = null;
            if (clBadge) { clBadge.textContent = '0/0'; clBadge.classList.add('d-none'); }
            if (clMarkDoneBtn) clMarkDoneBtn.classList.add('d-none');
            if (clContainer) clContainer.innerHTML = '<div class="text-center text-muted py-5"><i class="fa-solid fa-spinner fa-spin fa-lg"></i></div>';
        });
        // Ripristina tab Dettagli dopo che il modal è completamente visibile
        // (chiamare bsTab.show() in show.bs.modal, su un elemento ancora nascosto, non funziona)
        taskEditModalEl.addEventListener('shown.bs.modal', function () {
            var detailsTabBtn = document.getElementById('prjTaskTabDetailsBtn');
            if (detailsTabBtn) {
                var bsTab = bootstrap.Tab.getOrCreateInstance(detailsTabBtn);
                bsTab.show();
            }
        });
    }

    // ── Quando si clicca la tab Checklist: carica ────────────────
    // Usa 'click' invece di 'shown.bs.tab' per evitare problemi con le transizioni Bootstrap
    if (clChecklistTabBtn) {
        clChecklistTabBtn.addEventListener('click', function () {
            if (currentEditTaskId) {
                // Piccolo delay per lasciare che Bootstrap attivi il pannello
                setTimeout(function () { clLoad(currentEditTaskId); }, 50);
            }
        });
    }

    // ── Delegated events sulla checklist container ───────────────
    document.addEventListener('click', function (e) {
        if (!clContainer) return;

        // Aggiungi voce
        if (e.target.closest('#prj-cl-add-btn')) {
            var input = clContainer.querySelector('#prj-cl-new-label');
            var label = input ? input.value.trim() : '';
            if (!label) { if (input) input.focus(); return; }
            var btn = e.target.closest('#prj-cl-add-btn');
            btn.disabled = true;
            var urlTpl = clContainer.dataset.checklistUrl || '';
            var url    = clBuildUrl(urlTpl, currentEditTaskId);
            var fd     = new FormData();
            fd.append('_token', clGetCsrf());
            fd.append('label', label);
            jsonPost(url, fd)
                .then(function (r) {
                    if (!r.ok) throw new Error(r.data.error || t('js.progetti.checklist_add_error', 'Errore aggiunta voce.'));
                    var item    = r.data.item;
                    var listEl  = clContainer.querySelector('#prj-cl-list');
                    var canM    = !!clContainer.querySelector('.prj-cl-add-area');
                    if (!listEl) {
                        // Primo item: ricrea la lista
                        var emptyP = clContainer.querySelector('p.text-muted');
                        if (emptyP) emptyP.remove();
                        var panel = clContainer.querySelector('.prj-cl-panel');
                        if (panel) {
                            var ul = document.createElement('ul');
                            ul.id = 'prj-cl-list';
                            ul.className = 'list-unstyled mb-3';
                            panel.insertBefore(ul, panel.querySelector('.prj-cl-add-area'));
                            listEl = ul;
                        }
                    }
                    if (listEl) {
                        var li = document.createElement('li');
                        li.outerHTML; // placeholder
                        listEl.insertAdjacentHTML('beforeend', clBuildItemHtml(item, canM));
                    }
                    // Aggiorna stats modal + card kanban
                    var total = clContainer.querySelectorAll('.prj-cl-item').length;
                    var done  = clContainer.querySelectorAll('.prj-cl-item[data-done="1"]').length;
                    clUpdateStats(clContainer, total, done);
                    clUpdateKanbanCard(currentEditTaskId, total, done);
                    if (input) { input.value = ''; input.focus(); }
                    reinitTooltips(clContainer);
                })
                .catch(function (err) { toast(err.message, 'danger'); })
                .finally(function () { btn.disabled = false; });
            return;
        }

        // Applica template
        if (e.target.closest('#prj-cl-apply-tpl-btn')) {
            var sel = clContainer.querySelector('#prj-cl-tpl-select');
            var tplId = sel ? parseInt(sel.value, 10) : 0;
            if (!tplId) { toast(t('js.progetti.select_template', 'Seleziona un modello.'), 'danger'); return; }
            var applyBtn = e.target.closest('#prj-cl-apply-tpl-btn');
            applyBtn.disabled = true;
            var urlTplBase = clContainer.dataset.checklistUrl || '';
            var applyUrl   = clBuildUrl(urlTplBase, currentEditTaskId) + '/from-template';
            var fd         = new FormData();
            fd.append('_token', clGetCsrf());
            fd.append('template_id', tplId);
            jsonPost(applyUrl, fd)
                .then(function (r) {
                    if (!r.ok) throw new Error(r.data.error || t('js.progetti.apply_template_error', "Errore nell'applicazione del modello."));
                    toast(t('js.progetti.template_applied', 'Modello applicato.'), 'success');
                    clLoadedForTask = null;
                    clLoad(currentEditTaskId);
                })
                .catch(function (err) { toast(err.message, 'danger'); })
                .finally(function () { applyBtn.disabled = false; });
            return;
        }

        // Click checkbox — mostra area commento
        var checkEl = e.target.closest('.prj-cl-check');
        if (checkEl && !checkEl.disabled && checkEl.checked) {
            // Il browser ha già impostato checked=true; lo annulliamo finché l'utente non conferma
            checkEl.checked = false;
            e.preventDefault();
            var li = checkEl.closest('.prj-cl-item');
            if (!li) return;
            var commentArea = li.querySelector('.prj-cl-comment-area');
            if (!commentArea) return;
            // Conta quanti item NON ancora done (escluso questo)
            var pendingItems = Array.from(clContainer.querySelectorAll('.prj-cl-item[data-done="0"]'));
            var isLast = pendingItems.length === 1;
            var textarea = commentArea.querySelector('.prj-cl-comment-input');
            if (textarea) {
                textarea.placeholder = isLast
                    ? t('js.progetti.comment_required_placeholder', "Commento obbligatorio per l'ultima voce...")
                    : t('js.progetti.comment_optional_placeholder', 'Commento (facoltativo)...');
            }
            commentArea.classList.remove('d-none');
            if (textarea) textarea.focus();
            return;
        }

        // Annulla check
        if (e.target.closest('.prj-cl-cancel-check')) {
            var li = e.target.closest('.prj-cl-item');
            if (li) {
                var area = li.querySelector('.prj-cl-comment-area');
                if (area) area.classList.add('d-none');
                var textarea = li.querySelector('.prj-cl-comment-input');
                if (textarea) textarea.value = '';
            }
            return;
        }

        // Conferma check
        if (e.target.closest('.prj-cl-confirm-check')) {
            var li       = e.target.closest('.prj-cl-item');
            if (!li) return;
            var itemId   = parseInt(li.dataset.itemId, 10);
            var textarea = li.querySelector('.prj-cl-comment-input');
            var comment  = textarea ? textarea.value.trim() : '';

            // Verifica se è l'ultima voce
            var pendingItems = Array.from(clContainer.querySelectorAll('.prj-cl-item[data-done="0"]'));
            var isLast = pendingItems.length === 1;
            if (isLast && !comment) {
                if (textarea) { textarea.classList.add('is-invalid'); textarea.focus(); }
                toast(t('js.progetti.comment_required_toast', "Il commento è obbligatorio per l'ultima voce."), 'danger');
                return;
            }
            if (textarea) textarea.classList.remove('is-invalid');

            var confirmBtn = e.target.closest('.prj-cl-confirm-check');
            confirmBtn.disabled = true;
            var urlTpl  = clContainer.dataset.checklistUrl || '';
            var doneUrl = clBuildUrl(urlTpl, currentEditTaskId) + '/' + itemId + '/done';
            var fd      = new FormData();
            fd.append('_token', clGetCsrf());
            fd.append('comment', comment);
            jsonPost(doneUrl, fd)
                .then(function (r) {
                    if (!r.ok) throw new Error(r.data.error || t('js.progetti.confirm_error', 'Errore conferma.'));
                    // Aggiorna UI dell'item
                    li.dataset.done = '1';
                    var cb = li.querySelector('.prj-cl-check');
                    if (cb) { cb.checked = true; cb.disabled = true; }
                    var labelEl = li.querySelector('.form-check-label');
                    if (labelEl) { labelEl.classList.add('text-decoration-line-through', 'text-muted'); }
                    var area = li.querySelector('.prj-cl-comment-area');
                    if (area) area.remove();
                    var deleteBtn = li.querySelector('.prj-cl-delete');
                    if (deleteBtn) deleteBtn.remove();
                    var dragHandle = li.querySelector('.prj-cl-drag-handle');
                    if (dragHandle) dragHandle.classList.add('invisible');
                    // Mostra commento salvato
                    if (comment) {
                        var bodyDiv = li.querySelector('.flex-grow-1');
                        if (bodyDiv) {
                            bodyDiv.insertAdjacentHTML('beforeend',
                                '<div class="small text-muted mt-1 fst-italic">&ldquo;' + clEsc(comment) + '&rdquo;</div>'
                            );
                        }
                    }
                    // Aggiorna stats modal + card kanban
                    var total = clContainer.querySelectorAll('.prj-cl-item').length;
                    var done  = clContainer.querySelectorAll('.prj-cl-item[data-done="1"]').length;
                    clUpdateStats(clContainer, total, done);
                    clUpdateKanbanCard(currentEditTaskId, total, done);
                    // Se allDone
                    if (r.data.allDone && clMarkDoneBtn) {
                        clMarkDoneBtn.classList.remove('d-none');
                        clMarkDoneBtn.dataset.taskId = String(currentEditTaskId);
                    }
                })
                .catch(function (err) { toast(err.message, 'danger'); })
                .finally(function () { confirmBtn.disabled = false; });
            return;
        }

        // Elimina item
        var delBtn = e.target.closest('.prj-cl-delete');
        if (delBtn && clContainer.contains(delBtn)) {
            var itemId  = parseInt(delBtn.dataset.itemId, 10);
            var li      = delBtn.closest('.prj-cl-item');
            // Conferma inline: primo click → evidenzia; secondo click → elimina
            if (!delBtn.dataset.confirming) {
                delBtn.dataset.confirming = '1';
                delBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Conferma';
                delBtn.classList.add('btn-danger');
                delBtn.classList.remove('btn-outline-danger');
                setTimeout(function () {
                    delBtn.dataset.confirming = '';
                    delBtn.innerHTML = '<i class="fa-solid fa-trash"></i>';
                    delBtn.classList.remove('btn-danger');
                    delBtn.classList.add('btn-outline-danger');
                }, 3000);
                return;
            }
            delBtn.disabled = true;
            var urlTpl  = clContainer.dataset.checklistUrl || '';
            var delUrl  = clBuildUrl(urlTpl, currentEditTaskId) + '/' + itemId;
            var fd      = new FormData();
            fd.append('_token', clGetCsrf());
            fd.append('_method', 'DELETE');
            jsonPost(delUrl, fd)
                .then(function (r) {
                    if (!r.ok) throw new Error(r.data.error || t('js.progetti.delete_error', 'Errore eliminazione.'));
                    if (li) li.remove();
                    var total = clContainer.querySelectorAll('.prj-cl-item').length;
                    var done  = clContainer.querySelectorAll('.prj-cl-item[data-done="1"]').length;
                    clUpdateStats(clContainer, total, done);
                    clUpdateKanbanCard(currentEditTaskId, total, done);
                })
                .catch(function (err) { toast(err.message, 'danger'); delBtn.disabled = false; });
            return;
        }
    });

    // ── "Segna come completato" nel modal ────────────────────────
    if (clMarkDoneBtn) {
        clMarkDoneBtn.addEventListener('click', function () {
            var taskId  = parseInt(clMarkDoneBtn.dataset.taskId, 10);
            var urlTpl  = clContainer ? clContainer.dataset.quickStatusUrl : '';
            var url     = clBuildUrl(urlTpl, taskId);
            var fd      = new FormData();
            fd.append('_token', clGetCsrf());
            fd.append('status', 'done');
            clMarkDoneBtn.disabled = true;
            jsonPost(url, fd)
                .then(function (r) {
                    if (!r.ok) throw new Error(r.data.error || t('js.progetti.status_change_error', 'Errore cambio stato.'));
                    toast(t('js.progetti.task_marked_done', 'Attivita segnata come completata!'), 'success');
                    if (taskEditModal) taskEditModal.hide();
                    setTimeout(function () { window.location.reload(); }, 600);
                })
                .catch(function (err) { toast(err.message, 'danger'); clMarkDoneBtn.disabled = false; });
        });
    }

    /* ── Fine Checklist ───────────────────────────────────────── */

    // ── Setter per my_tasks page (consente di impostare currentEditTaskId
    //    e resettare clLoadedForTask dall'inline script esterno) ────────────
    window._prjSetCurrentTask = function (taskId) {
        currentEditTaskId = parseInt(taskId, 10) || null;
        clLoadedForTask   = null;
    };

})();