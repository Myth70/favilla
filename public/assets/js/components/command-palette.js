/**
 * command-palette.js — Favilla Command Palette (Ctrl+K) v3
 *
 * Miglioramenti v3:
 *  - Mouse hover seleziona sempre (rimossa dipendenza da inputMode)
 *  - Ctrl/Cmd+click e click con tasto centrale → apri in nuova scheda
 *  - Ctrl/Cmd+Invio → apri elemento selezionato in nuova scheda
 *  - Pulsante "Cancella cronologia" nel tab Recenti
 *  - Animazione di pressione al click
 *  - Rimosso tracking mousemove superfluo
 */
(function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────────────
    var STORAGE_KEY  = 'favilla_palette_recent';
    var MAX_RECENT   = 8;
    var MAX_RESULTS  = 40;
    var DEBOUNCE_MS  = 80;
    var PRESS_MS     = 90;

    // ── State ────────────────────────────────────────────────────────
    var catalog       = null;
    var isOpen        = false;
    var selectedIndex = -1;
    var currentItems  = [];
    var fetching      = false;
    var activeTab     = 'all';   // 'all' | 'recent'
    var inputMode     = 'keyboard';
    var debounceTimer = null;

    // ── DOM refs ─────────────────────────────────────────────────────
    var root, dialog, inputEl, clearBtn, results, countEl;
    var tabAll, tabRecent, countAll, countRecent;
    var indexLink;

    // ════════════════════════════════════════════════════════════════
    // Init
    // ════════════════════════════════════════════════════════════════
    function init() {
        root = document.getElementById('command-palette-root');
        if (!root) return;

        dialog      = root.querySelector('.cp-dialog');
        inputEl     = root.querySelector('.cp-input');
        clearBtn    = root.querySelector('.cp-clear-btn');
        results     = root.querySelector('#cp-results-list');
        countEl     = root.querySelector('#cp-result-count');
        tabAll      = root.querySelector('[data-cp-tab="all"]');
        tabRecent   = root.querySelector('[data-cp-tab="recent"]');
        countAll    = root.querySelector('#cp-count-all');
        countRecent = root.querySelector('#cp-count-recent');
        indexLink   = root.querySelector('#cp-index-link');

        // Global shortcut: Ctrl+K / Cmd+K
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                e.stopPropagation();
                isOpen ? close() : open();
            }
        });

        // Sidebar trigger button
        var triggerBtn = document.getElementById('palette-trigger-btn');
        if (triggerBtn) {
            triggerBtn.addEventListener('click', function (e) {
                e.preventDefault();
                open();
            });
        }

        // Close on backdrop click
        root.addEventListener('mousedown', function (e) {
            if (e.target === root) close();
        });

        // Input events
        inputEl.addEventListener('input', onInput);
        inputEl.addEventListener('keydown', onKeyDown);

        // Clear button
        clearBtn && clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            inputEl.value = '';
            updateClearBtn();
            inputEl.focus();
            triggerRender();
        });

        // Tab buttons
        tabAll    && tabAll.addEventListener('click',    function () { switchTab('all'); });
        tabRecent && tabRecent.addEventListener('click', function () { switchTab('recent'); });

        // Event delegation in results: gestisce il pulsante "Cancella cronologia"
        results.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-cp-action="clear-recent"]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                clearRecentStorage();
                updateRecentCount();
                triggerRender();
                inputEl.focus();
            }
        });

        // Keep "Indice completo" footer link accessible
        if (indexLink) {
            indexLink.addEventListener('click', function () { close(); });
        }

        // Update recent count badge on init
        updateRecentCount();
    }

    // ════════════════════════════════════════════════════════════════
    // Open / Close
    // ════════════════════════════════════════════════════════════════
    function open() {
        if (isOpen) return;
        isOpen    = true;
        inputMode = 'keyboard';
        activeTab = 'all';
        root.classList.remove('d-none');
        inputEl.value = '';
        selectedIndex  = -1;
        updateClearBtn();
        setActiveTab('all');

        if (!catalog && !fetching) {
            fetchCatalog();
        } else {
            triggerRender();
        }

        requestAnimationFrame(function () { inputEl.focus(); });
        document.body.style.overflow = 'hidden';
    }

    function close() {
        if (!isOpen) return;
        isOpen = false;
        root.classList.add('d-none');
        inputEl.value = '';
        results.innerHTML = '';
        selectedIndex = -1;
        currentItems  = [];
        updateClearBtn();
        updateCount('');
        document.body.style.overflow = '';
    }

    // ════════════════════════════════════════════════════════════════
    // Tabs
    // ════════════════════════════════════════════════════════════════
    function switchTab(tab) {
        if (tab === activeTab) return;
        activeTab = tab;
        selectedIndex = -1;
        setActiveTab(tab);
        triggerRender();
    }

    function setActiveTab(tab) {
        [tabAll, tabRecent].forEach(function (btn) {
            if (!btn) return;
            var isActive = btn.getAttribute('data-cp-tab') === tab;
            btn.classList.toggle('cp-tab-active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    // ════════════════════════════════════════════════════════════════
    // Fetch catalog
    // ════════════════════════════════════════════════════════════════
    function fetchCatalog() {
        fetching = true;
        renderState('loading', 'fa-solid fa-spinner fa-spin', 'Caricamento…', 'Recupero del catalogo funzioni in corso.');

        var url = root.getAttribute('data-palette-url');
        if (!url) { fetching = false; return; }

        fetch(url, {
            headers:     { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (data) {
            catalog  = Array.isArray(data) ? data : [];
            fetching = false;
            updateAllCount();
            if (isOpen) triggerRender();
        })
        .catch(function () {
            fetching = false;
            renderState('error', 'fa-solid fa-circle-exclamation', 'Errore di caricamento', 'Impossibile recuperare il catalogo. Riprova più tardi.');
        });
    }

    // ════════════════════════════════════════════════════════════════
    // Input handling
    // ════════════════════════════════════════════════════════════════
    function onInput() {
        updateClearBtn();
        selectedIndex = -1;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerRender, DEBOUNCE_MS);
    }

    function triggerRender() {
        if (!catalog && !fetching) { fetchCatalog(); return; }
        if (!catalog) return;

        var q = (inputEl.value || '').trim();

        if (activeTab === 'recent') {
            renderRecentTab(q);
            return;
        }

        // 'all' tab
        if (q) {
            renderFiltered(q);
        } else {
            renderRecent();
        }
    }

    function updateClearBtn() {
        if (!clearBtn) return;
        clearBtn.classList.toggle('cp-visible', (inputEl.value || '').length > 0);
    }

    function updateCount(text) {
        if (!countEl) return;
        countEl.textContent = text;
        countEl.style.display = text ? '' : 'none';
    }

    function updateAllCount() {
        if (!countAll || !catalog) return;
        countAll.textContent = catalog.length > 999 ? '999+' : String(catalog.length);
    }

    function updateRecentCount() {
        if (!countRecent) return;
        var n = getRecent().length;
        countRecent.textContent = String(n);
    }

    // ════════════════════════════════════════════════════════════════
    // Search & Scoring
    // ════════════════════════════════════════════════════════════════
    function filterItems(query) {
        var q = query.toLowerCase();
        var scored = [];

        for (var i = 0; i < catalog.length; i++) {
            var item  = catalog[i];
            var score = scoreMatch(q, item);
            if (score > 0) scored.push({ item: item, score: score });
        }

        scored.sort(function (a, b) { return b.score - a.score; });

        var out = [];
        var limit = Math.min(scored.length, MAX_RESULTS);
        for (var j = 0; j < limit; j++) out.push(scored[j].item);
        return out;
    }

    function scoreMatch(q, item) {
        var label   = (item.label || '').toLowerCase();
        var desc    = (item.description || '').toLowerCase();
        var search  = item.search_text || '';
        var group   = (item.group || '').toLowerCase();
        var section = (item.section || '').toLowerCase();

        if (label === q)                 return 200;
        if (label.startsWith(q))         return 150;
        if (label.includes(q))           return 100;
        if (desc.includes(q))            return 70;
        if (search.includes(q))          return 55;
        if (group.includes(q))           return 40;
        if (section.includes(q))         return 30;
        if (fuzzyMatch(q, label))        return 15;
        if (fuzzyMatch(q, search))       return 8;
        return 0;
    }

    function fuzzyMatch(query, text) {
        var qi = 0;
        for (var ti = 0; ti < text.length && qi < query.length; ti++) {
            if (text[ti] === query[qi]) qi++;
        }
        return qi === query.length;
    }

    // ════════════════════════════════════════════════════════════════
    // Highlight matched text in label
    // ════════════════════════════════════════════════════════════════
    function highlightLabel(label, query) {
        if (!query) return escapeHtml(label);
        var lower = label.toLowerCase();
        var q     = query.toLowerCase();
        var idx   = lower.indexOf(q);

        if (idx !== -1) {
            return escapeHtml(label.substring(0, idx))
                + '<span class="cp-match">' + escapeHtml(label.substring(idx, idx + q.length)) + '</span>'
                + escapeHtml(label.substring(idx + q.length));
        }

        // Fuzzy highlight
        var qi = 0;
        var out = '';
        for (var i = 0; i < label.length; i++) {
            if (qi < q.length && label[i].toLowerCase() === q[qi]) {
                out += '<span class="cp-match">' + escapeHtml(label[i]) + '</span>';
                qi++;
            } else {
                out += escapeHtml(label[i]);
            }
        }
        return out;
    }

    // ════════════════════════════════════════════════════════════════
    // Build recent header HTML
    // ════════════════════════════════════════════════════════════════
    function buildRecentHeader(showClear) {
        var html = '<div class="cp-recent-header">'
            + '<span class="cp-recent-header-label">'
            + '<i class="fa-solid fa-clock-rotate-left fa-xs" aria-hidden="true"></i>'
            + ' Visitati di recente'
            + '</span>';
        if (showClear) {
            html += '<button type="button" class="cp-clear-recent-btn" data-cp-action="clear-recent" aria-label="Cancella cronologia">'
                + '<i class="fa-solid fa-trash-can fa-xs" aria-hidden="true"></i>'
                + ' Cancella'
                + '</button>';
        }
        html += '</div>';
        return html;
    }

    // ════════════════════════════════════════════════════════════════
    // Render
    // ════════════════════════════════════════════════════════════════
    function renderFiltered(query) {
        var items = filterItems(query);
        currentItems = items;

        if (items.length === 0) {
            updateCount('');
            renderState(
                'empty',
                'fa-solid fa-magnifying-glass',
                'Nessun risultato',
                'Nessuna funzione corrisponde a "' + escapeHtml(query) + '".'
            );
            return;
        }

        var noun = items.length === 1 ? 'risultato' : 'risultati';
        updateCount(items.length + ' ' + noun);

        var html = '';
        var lastSection = '';
        var lastGroup   = '';

        for (var i = 0; i < items.length; i++) {
            var item     = items[i];
            var secKey   = item.section || '';
            var groupKey = secKey + '>' + (item.group || '');

            if (secKey !== lastSection) {
                html += '<div class="cp-section-header" aria-hidden="true">'
                      + escapeHtml(secKey || 'Altro')
                      + '</div>';
                lastSection = secKey;
                lastGroup   = '';
            }

            if (groupKey !== lastGroup) {
                html += '<div class="cp-group-header" aria-hidden="true">'
                      + escapeHtml(item.group || '')
                      + '</div>';
                lastGroup = groupKey;
            }

            html += buildItem(item, i, query);
        }

        results.innerHTML = html;
        bindItemEvents();
    }

    function renderRecent() {
        var recent = getRecent();
        currentItems = recent;
        updateCount('');

        if (recent.length === 0) {
            renderState(
                'hint',
                'fa-solid fa-magnifying-glass',
                'Cerca tra le funzioni',
                'Digita per cercare tra tutte le funzioni amministrative. Gli elementi visitati appariranno qui.'
            );
            return;
        }

        var html = buildRecentHeader(true);
        for (var i = 0; i < recent.length; i++) {
            html += buildItem(recent[i], i, '');
        }

        results.innerHTML = html;
        bindItemEvents();
    }

    function renderRecentTab(query) {
        var allRecent = getRecent();
        var recent    = allRecent;

        if (query) {
            var q = query.toLowerCase();
            recent = allRecent.filter(function (r) {
                return (r.label || '').toLowerCase().includes(q)
                    || (r.description || '').toLowerCase().includes(q)
                    || (r.search_text || '').includes(q);
            });
        }

        currentItems = recent;
        updateCount(recent.length > 0 ? recent.length + (recent.length === 1 ? ' elemento' : ' elementi') : '');

        if (recent.length === 0) {
            renderState(
                'hint',
                'fa-solid fa-clock-rotate-left',
                'Nessuna visita recente',
                query ? 'Nessun elemento recente corrisponde a "' + escapeHtml(query) + '".' : 'Naviga nelle funzioni admin per costruire la cronologia.'
            );
            return;
        }

        var html = buildRecentHeader(allRecent.length > 0);
        for (var i = 0; i < recent.length; i++) {
            html += buildItem(recent[i], i, query);
        }

        results.innerHTML = html;
        bindItemEvents();
    }

    function renderState(type, iconClass, title, desc) {
        currentItems = [];
        updateCount('');
        results.innerHTML =
            '<div class="cp-state" role="status">' +
                '<div class="cp-state-icon"><i class="' + iconClass + '" aria-hidden="true"></i></div>' +
                '<div class="cp-state-title">' + escapeHtml(title) + '</div>' +
                '<div class="cp-state-desc">' + escapeHtml(desc) + '</div>' +
            '</div>';
    }

    function buildItem(item, index, query) {
        var sel = (index === selectedIndex);
        return '<div class="cp-item' + (sel ? ' cp-item-selected' : '') + '"'
            + ' data-cp-index="' + index + '"'
            + ' data-cp-url="'   + escapeHtml(item.url) + '"'
            + ' role="option"'
            + ' aria-selected="' + (sel ? 'true' : 'false') + '">'
            + '<div class="cp-item-icon" aria-hidden="true"><i class="' + escapeHtml(item.icon) + '"></i></div>'
            + '<div class="cp-item-content">'
            +   '<div class="cp-item-label">' + highlightLabel(item.label, query) + '</div>'
            +   '<div class="cp-item-desc">'  + escapeHtml(item.description) + '</div>'
            + '</div>'
            + '<div class="cp-item-meta">'
            +   '<span class="cp-item-badge">'   + escapeHtml(item.group || '') + '</span>'
            +   '<span class="cp-item-section">' + escapeHtml(item.section || '') + '</span>'
            + '</div>'
            + '<i class="fa-solid fa-chevron-right cp-item-arrow" aria-hidden="true"></i>'
            + '</div>';
    }

    // ════════════════════════════════════════════════════════════════
    // Item interaction
    // ════════════════════════════════════════════════════════════════
    function bindItemEvents() {
        var els = results.querySelectorAll('.cp-item');
        for (var i = 0; i < els.length; i++) {
            (function (el, idx) {
                // Left click: Ctrl/Cmd → nuova scheda, altrimenti naviga con animazione
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (e.ctrlKey || e.metaKey) {
                        openInNewTab(idx);
                    } else {
                        pressAndNavigate(el, idx);
                    }
                });

                // Tasto centrale (middle-click) → nuova scheda
                el.addEventListener('mousedown', function (e) {
                    if (e.button === 1) {
                        e.preventDefault();
                        openInNewTab(idx);
                    }
                });

                // Hover → seleziona sempre (indipendente da inputMode)
                el.addEventListener('mouseenter', function () {
                    inputMode = 'mouse';
                    setSelected(idx, false);
                });

                el.addEventListener('mouseleave', function () {
                    if (inputMode === 'mouse' && selectedIndex === idx) {
                        clearSelection();
                    }
                });
            })(els[i], i);
        }
    }

    function pressAndNavigate(el, index) {
        if (index < 0 || index >= currentItems.length) return;
        var item = currentItems[index]; // cattura prima del close()
        el.classList.add('cp-item-pressing');
        setTimeout(function () {
            addRecent(item);
            updateRecentCount();
            close();
            window.location.href = item.url;
        }, PRESS_MS);
    }

    function navigateTo(index) {
        if (index < 0 || index >= currentItems.length) return;
        var item = currentItems[index];
        addRecent(item);
        updateRecentCount();
        close();
        window.location.href = item.url;
    }

    function openInNewTab(index) {
        if (index < 0 || index >= currentItems.length) return;
        var item = currentItems[index];
        addRecent(item);
        updateRecentCount();
        close();
        window.open(item.url, '_blank', 'noopener,noreferrer');
    }

    function setSelected(index, scroll) {
        if (index === selectedIndex) return;
        var els = results.querySelectorAll('.cp-item');

        if (selectedIndex >= 0 && els[selectedIndex]) {
            els[selectedIndex].classList.remove('cp-item-selected');
            els[selectedIndex].setAttribute('aria-selected', 'false');
        }

        selectedIndex = index;

        if (index >= 0 && els[index]) {
            els[index].classList.add('cp-item-selected');
            els[index].setAttribute('aria-selected', 'true');
            if (scroll !== false) scrollIntoViewIfNeeded(els[index]);
        }
    }

    function clearSelection() {
        var prev = results.querySelector('.cp-item-selected');
        if (prev) {
            prev.classList.remove('cp-item-selected');
            prev.setAttribute('aria-selected', 'false');
        }
        selectedIndex = -1;
    }

    function scrollIntoViewIfNeeded(el) {
        var cTop    = results.scrollTop;
        var cBottom = cTop + results.clientHeight;
        var eTop    = el.offsetTop - results.offsetTop;
        var eBottom = eTop + el.offsetHeight;

        if (eTop < cTop) {
            results.scrollTop = eTop - 4;
        } else if (eBottom > cBottom) {
            results.scrollTop = eBottom - results.clientHeight + 4;
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Keyboard navigation
    // ════════════════════════════════════════════════════════════════
    function onKeyDown(e) {
        var count = currentItems.length;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                inputMode = 'keyboard';
                if (count > 0) setSelected(selectedIndex < count - 1 ? selectedIndex + 1 : 0, true);
                break;

            case 'ArrowUp':
                e.preventDefault();
                inputMode = 'keyboard';
                if (count > 0) setSelected(selectedIndex > 0 ? selectedIndex - 1 : count - 1, true);
                break;

            case 'Enter':
                e.preventDefault();
                if (e.ctrlKey || e.metaKey) {
                    // Apri in nuova scheda
                    var newTabIdx = selectedIndex >= 0 ? selectedIndex : (count > 0 ? 0 : -1);
                    if (newTabIdx >= 0) openInNewTab(newTabIdx);
                } else {
                    if (selectedIndex >= 0) {
                        navigateTo(selectedIndex);
                    } else if (count > 0) {
                        navigateTo(0);
                    }
                }
                break;

            case 'Escape':
                e.preventDefault();
                close();
                break;

            case 'Tab':
                // Cicla tra i tab con Tab / Shift+Tab quando l'input è vuoto
                if ((inputEl.value || '').trim() === '') {
                    e.preventDefault();
                    switchTab(activeTab === 'all' ? 'recent' : 'all');
                }
                break;
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Recent items (localStorage)
    // ════════════════════════════════════════════════════════════════
    function getRecent() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function addRecent(item) {
        try {
            var list = getRecent();
            list = list.filter(function (r) { return r.url !== item.url; });
            list.unshift({
                label:       item.label,
                description: item.description,
                icon:        item.icon,
                url:         item.url,
                group:       item.group   || '',
                section:     item.section || '',
                search_text: item.search_text || ''
            });
            if (list.length > MAX_RECENT) list = list.slice(0, MAX_RECENT);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (e) { /* silent */ }
    }

    function clearRecentStorage() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* silent */ }
    }

    // ════════════════════════════════════════════════════════════════
    // Utilities
    // ════════════════════════════════════════════════════════════════
    function escapeHtml(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    // ════════════════════════════════════════════════════════════════
    // Boot
    // ════════════════════════════════════════════════════════════════
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
