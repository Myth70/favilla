(function() {
    'use strict';

    var sortableInstance = null;

    /**
     * Initialize SortableJS on the widget settings list.
     */
    function initSortable() {
        var list = document.getElementById('widget-settings-list');
        if (!list || sortableInstance) return;

        sortableInstance = new Sortable(list, {
            handle: '.hm-drag-handle',
            animation: 150,
            ghostClass: 'hm-sortable-ghost',
            chosenClass: 'hm-sortable-chosen'
        });
    }

    /**
     * Destroy sortable instance (when offcanvas closes).
     */
    function destroySortable() {
        if (sortableInstance) {
            sortableInstance.destroy();
            sortableInstance = null;
        }
    }

    /**
     * Collect widget order + visibility from the settings list.
     */
    function collectWidgetLayout() {
        var items = document.querySelectorAll('#widget-settings-list .hm-widget-item');
        var widgets = [];
        items.forEach(function(item) {
            widgets.push({
                id: item.dataset.widgetId,
                visible: item.querySelector('.hm-widget-toggle').checked
            });
        });
        return widgets;
    }

    /**
     * Save widget layout via POST.
     */
    function saveWidgetLayout() {
        var widgets = collectWidgetLayout();
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.content : '';

        var saveBtn = document.getElementById('hm-widget-save');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Salvataggio...';
        }

        fetch(window.hmWidgetRoutes.save, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ widgets: widgets })
        }).then(function(response) {
            if (response.ok) {
                // Close offcanvas
                var offcanvasEl = document.getElementById('widgetSettingsOffcanvas');
                if (offcanvasEl) {
                    var offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                    if (offcanvas) offcanvas.hide();
                }
                // Refresh widgets via HTMX
                var target = document.getElementById('dashboard-widgets');
                if (target) {
                    htmx.trigger(target, 'refresh');
                }
            }
        }).catch(function() {
            // Silent fail — toast from server side
        }).finally(function() {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Salva';
            }
        });
    }

    /**
     * Reset widget layout to defaults.
     */
    function resetWidgetLayout() {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.content : '';

        fetch(window.hmWidgetRoutes.reset, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        }).then(function(response) {
            if (response.ok) {
                // Close offcanvas
                var offcanvasEl = document.getElementById('widgetSettingsOffcanvas');
                if (offcanvasEl) {
                    var offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                    if (offcanvas) offcanvas.hide();
                }
                // Refresh widgets
                var target = document.getElementById('dashboard-widgets');
                if (target) {
                    htmx.trigger(target, 'refresh');
                }
            }
        });
    }

    /**
     * Lazily ensure the ApexCharts script is loaded; resolves once `window.ApexCharts`
     * is available. Multiple concurrent calls share the same in-flight promise.
     * Path matches the asset shipped in /public/assets/js/apexcharts.min.js.
     */
    var apexLoaderPromise = null;
    function ensureApexCharts() {
        if (typeof window.ApexCharts !== 'undefined') {
            return Promise.resolve();
        }
        if (apexLoaderPromise) {
            return apexLoaderPromise;
        }
        apexLoaderPromise = new Promise(function(resolve, reject) {
            var existing = document.querySelector('script[data-apex-loader]');
            if (existing) {
                existing.addEventListener('load', function() { resolve(); });
                existing.addEventListener('error', function() { reject(new Error('apexcharts load failed')); });
                return;
            }
            // Derive the asset base URL from this script's own src, so the loader works
            // regardless of where Favilla is mounted (subdir or domain root).
            var self = document.currentScript
                || document.querySelector('script[src*="dashboard-widgets"]');
            var apexUrl = '/assets/js/apexcharts.min.js';
            if (self && self.src) {
                apexUrl = self.src.replace(/dashboard-widgets\.js(\?.*)?$/, 'apexcharts.min.js');
            }
            var s = document.createElement('script');
            s.src = apexUrl;
            s.async = true;
            s.dataset.apexLoader = '1';
            s.addEventListener('load', function() { resolve(); });
            s.addEventListener('error', function() {
                apexLoaderPromise = null;
                reject(new Error('apexcharts load failed'));
            });
            document.head.appendChild(s);
        });
        return apexLoaderPromise;
    }

    /**
     * Initialize ApexCharts from data attributes. Loads the ApexCharts library
     * on demand if at least one chart placeholder is found in the container.
     */
    function initApexCharts(container) {
        var root = container || document;
        var charts = root.querySelectorAll('[data-apex-chart]:not([data-apex-rendered])');
        if (!charts.length) return;
        ensureApexCharts().then(function() {
            charts.forEach(function(el) {
                if (el.dataset.apexRendered) return;
                try {
                    var opts = JSON.parse(el.dataset.apexChart);
                    opts.chart = opts.chart || {};
                    opts.chart.fontFamily = 'inherit';
                    el._apexChart = new ApexCharts(el, opts);
                    el._apexChart.render();
                    el.dataset.apexRendered = '1';
                } catch (e) {
                    // Skip invalid chart config
                }
            });
        }).catch(function() {
            // ApexCharts failed to load — leave placeholders untouched.
        });
    }

    function destroyApexCharts(container) {
        var root = container || document;
        var charts = root.querySelectorAll('[data-apex-chart]');

        charts.forEach(function(el) {
            if (el._apexChart && typeof el._apexChart.destroy === 'function') {
                el._apexChart.destroy();
                el._apexChart = null;
            }
            delete el.dataset.apexRendered;
        });
    }

    function refreshApexCharts(container) {
        destroyApexCharts(container);
        initApexCharts(container);
    }

    /**
     * Re-init Bootstrap tooltips.
     */
    function initTooltips(container) {
        var root = container || document;
        var tooltips = [].slice.call(root.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltips.forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    }

    // --- Event Listeners ---

    // Delegate click for save/reset buttons (content loaded via HTMX)
    document.addEventListener('click', function(e) {
        if (e.target.closest('#hm-widget-save')) {
            saveWidgetLayout();
        }
        if (e.target.closest('#hm-widget-reset')) {
            resetWidgetLayout();
        }
    });

    // React to HTMX swaps: settings panel, single-widget bodies, skeleton reload.
    document.body.addEventListener('htmx:afterSwap', function(e) {
        var target = e.detail.target;
        if (!target) return;

        // Settings offcanvas content loaded → (re)init drag-and-drop.
        if (target.id === 'widgetSettingsOffcanvasBody') {
            destroySortable();
            initSortable();
            return;
        }

        // A single widget body was loaded in parallel (target = column wrapper).
        if (target.matches && target.matches('[data-widget-id]')) {
            // Empty body = widget hidden / nothing to show → drop the placeholder.
            if (target.children.length === 0 || target.textContent.trim() === '') {
                target.remove();
                return;
            }
            initApexCharts(target);
            initTooltips(target);
            return;
        }

        // Skeleton structure reloaded after save/reset (container innerHTML swap).
        if (target.id === 'dashboard-widgets') {
            initApexCharts(target);
            initTooltips(target);
        }
    });

    // Cleanup sortable when offcanvas hides
    document.addEventListener('hidden.bs.offcanvas', function(e) {
        if (e.target.id === 'widgetSettingsOffcanvas') {
            destroySortable();
        }
    });

    // Init charts on page load
    document.addEventListener('DOMContentLoaded', function() {
        initApexCharts();
    });

    document.addEventListener('favilla:theme-state-changed', function() {
        var widgetGrid = document.getElementById('dashboard-widgets');
        if (!widgetGrid) return;

        refreshApexCharts(widgetGrid);
    });

})();
