(function () {
    var STORAGE_KEY = 'favilla.home.today.filterMode';
    var VIEW_KEY    = 'favilla.home.today.viewMode';

    function initTooltips(root) {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }

        var scope = root || document;
        var nodes = scope.querySelectorAll('[data-bs-toggle="tooltip"]');

        nodes.forEach(function (el) {
            try {
                window.bootstrap.Tooltip.getOrCreateInstance(el);
            } catch (e) {
                // keep runtime resilient for detached/replaced nodes
            }
        });
    }

    function isTodayContextTarget(target) {
        if (!target) {
            return false;
        }

        return target.id === 'oggi-feed' || !!target.closest('#oggi-feed');
    }

    function getStoredFilterMode() {
        try {
            var raw = window.sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return 'all';
            }
            if (raw === 'urgent') {
                return 'urgent';
            }
            if (raw.indexOf('source:') === 0) {
                return raw;
            }
            return 'all';
        } catch (e) {
            return 'all';
        }
    }

    function setStoredFilterMode(mode) {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, mode);
        } catch (e) {
            // no-op
        }
    }

    function getStoredViewMode() {
        try {
            var raw = window.sessionStorage.getItem(VIEW_KEY);
            if (raw === 'timeline' || raw === 'urgenza') {
                return raw;
            }
            return 'timeline';
        } catch (e) {
            return 'timeline';
        }
    }

    function setStoredViewMode(mode) {
        try {
            window.sessionStorage.setItem(VIEW_KEY, mode);
        } catch (e) {
            // no-op
        }
    }

    function scrollNowLineIntoView(root) {
        if (!root) {
            return;
        }
        var tl = root.querySelector('.hm-today-tl');
        if (!tl || tl.getAttribute('data-hm-tl-scrolled') === '1') {
            return;
        }
        var nowEl = tl.querySelector('.hm-today-tl-now');
        if (!nowEl) {
            return;
        }
        // Only scroll if the timeline is taller than the viewport (otherwise it just jolts)
        var overflows = tl.getBoundingClientRect().height > (window.innerHeight * 0.9);
        if (!overflows) {
            tl.setAttribute('data-hm-tl-scrolled', '1');
            return;
        }
        try {
            nowEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
        } catch (e) {
            nowEl.scrollIntoView();
        }
        tl.setAttribute('data-hm-tl-scrolled', '1');
    }

    function applyViewMode(root, mode) {
        if (!root) {
            return;
        }
        if (mode !== 'timeline' && mode !== 'urgenza') {
            mode = 'timeline';
        }

        var modes = root.querySelectorAll('.hm-today-mode');
        modes.forEach(function (m) {
            var thisMode = m.getAttribute('data-hm-today-mode');
            m.classList.toggle('d-none', thisMode !== mode);
        });

        var buttons = root.querySelectorAll('[data-hm-today-viewmode]');
        buttons.forEach(function (btn) {
            var active = btn.getAttribute('data-hm-today-viewmode') === mode;
            btn.classList.toggle('active', active);
            if (btn.hasAttribute('aria-pressed')) {
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
        });

        if (mode === 'timeline') {
            scrollNowLineIntoView(root);
        }
    }

    function applyFilter(root, mode) {
        if (!root) {
            return;
        }

        var items = root.querySelectorAll('.hm-today-item');
        var visibleCount = 0;

        items.forEach(function (item) {
            var visible;
            if (mode === 'all') {
                visible = true;
            } else if (mode === 'urgent') {
                visible = item.getAttribute('data-hm-item-urgent') === '1';
            } else if (mode.indexOf('source:') === 0) {
                visible = item.getAttribute('data-hm-item-source') === mode.slice(7);
            } else {
                visible = true;
            }
            item.classList.toggle('d-none', !visible);
            if (visible) {
                visibleCount += 1;
            }
        });

        // Hide group headers whose items are all hidden
        var headers = root.querySelectorAll('.hm-today-group-header');
        headers.forEach(function (header) {
            var groupId = header.getAttribute('data-hm-group-id');
            var groupItems = root.querySelectorAll('.hm-today-item[data-hm-group="' + groupId + '"]');
            var anyVisible = false;
            groupItems.forEach(function (gi) {
                if (!gi.classList.contains('d-none')) {
                    anyVisible = true;
                }
            });
            header.classList.toggle('d-none', !anyVisible);
        });

        var countNode = root.querySelector('[data-hm-visible-count]');
        if (countNode) {
            countNode.textContent = String(visibleCount);
        }

        var emptyFiltered = root.querySelector('[data-hm-empty-filter]');
        if (emptyFiltered) {
            emptyFiltered.classList.toggle('d-none', visibleCount > 0);
        }

        var buttons = root.querySelectorAll('[data-hm-today-filter]');
        buttons.forEach(function (btn) {
            var active = btn.getAttribute('data-hm-today-filter') === mode;
            btn.classList.toggle('active', active);
            if (btn.hasAttribute('aria-pressed')) {
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            }
        });
    }

    function initTodayFilters(root) {
        if (!root) {
            return;
        }

        var hasFilterBar = root.querySelector('[data-hm-today-filter]');
        if (!hasFilterBar) {
            return;
        }

        applyFilter(root, getStoredFilterMode());
    }

    function initTodayViewMode(root) {
        if (!root) {
            return;
        }
        var hasViewToggle = root.querySelector('[data-hm-today-viewmode]');
        if (!hasViewToggle) {
            return;
        }
        applyViewMode(root, getStoredViewMode());
    }

    document.body.addEventListener('click', function (event) {
        var viewButton = event.target.closest('[data-hm-today-viewmode]');
        if (viewButton) {
            var rootV = document.getElementById('oggi-feed');
            var viewMode = viewButton.getAttribute('data-hm-today-viewmode') || 'timeline';
            if (viewMode !== 'timeline' && viewMode !== 'urgenza') {
                viewMode = 'timeline';
            }
            setStoredViewMode(viewMode);
            applyViewMode(rootV, viewMode);
            return;
        }

        var filterButton = event.target.closest('[data-hm-today-filter]');
        if (filterButton) {
            var root = document.getElementById('oggi-feed');
            var mode = filterButton.getAttribute('data-hm-today-filter') || 'all';
            setStoredFilterMode(mode);
            applyFilter(root, mode);
            return;
        }

        var resetButton = event.target.closest('[data-hm-reset-filter]');
        if (resetButton) {
            var resetRoot = document.getElementById('oggi-feed');
            setStoredFilterMode('all');
            applyFilter(resetRoot, 'all');
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips(document);
        initTodayViewMode(document.getElementById('oggi-feed'));
        initTodayFilters(document.getElementById('oggi-feed'));
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        if (!event || !isTodayContextTarget(event.target)) {
            return;
        }

        initTooltips(event.target);
        initTodayViewMode(document.getElementById('oggi-feed'));
        initTodayFilters(document.getElementById('oggi-feed'));
    });

    document.body.addEventListener('htmx:beforeRequest', function (event) {
        var elt = event.detail && event.detail.elt;
        if (!elt || !elt.hasAttribute('data-hm-quick-action')) {
            return;
        }
        var item = elt.closest('.hm-today-item');
        if (item) {
            item.classList.add('hm-today-item--removing');
        }
    });

    document.body.addEventListener('htmx:afterRequest', function (event) {
        var elt = event.detail && event.detail.elt;
        if (!elt || !elt.hasAttribute('data-hm-quick-action')) {
            return;
        }
        // On success, refresh the feed. This replaces the per-button
        // hx-on::after-request handlers, which a strict CSP (no 'unsafe-eval')
        // blocks because htmx evaluates them via new Function().
        if (event.detail && event.detail.successful) {
            if (window.htmx) {
                window.htmx.trigger('#oggi-feed', 'refreshTodayFeed');
            }
            return;
        }
        // If the request failed, restore the item so it stays visible
        var item = elt.closest('.hm-today-item');
        if (item) {
            item.classList.remove('hm-today-item--removing');
        }
    });
})();
