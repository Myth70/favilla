(function () {
    'use strict';

    // =====================================================================
    // Utility helpers
    // =====================================================================
    function parseJsonScript(id, fallback) {
        var el = document.getElementById(id);
        if (!el || !el.textContent) return fallback;
        try { return JSON.parse(el.textContent); } catch (e) { return fallback; }
    }

    function qs(sel, scope) { return (scope || document).querySelector(sel); }
    function qsa(sel, scope) { return (scope || document).querySelectorAll(sel); }

    // =====================================================================
    // Catalogs (populated once from <script type="application/json">)
    // =====================================================================
    var iconCatalog  = parseJsonScript('ntas-icon-catalog', []);
    var colorCatalog = parseJsonScript('ntas-color-catalog', []);
    var iconMap  = {};
    iconCatalog.forEach(function (i) { iconMap[i.value] = i.label; });
    var colorMap = {};
    colorCatalog.forEach(function (c) { colorMap[c.value] = c.label; });

    // =====================================================================
    // State
    // =====================================================================
    var currentIconTargetInputId  = '';
    var currentColorTargetInputId = '';
    var currentPreviewScope       = '';
    var iconInputByScope  = {};
    var colorInputByScope = {};
    var lastTemplateField = null;
    var previewDebounceTimer = null;

    // DOM references (static pickers that live in admin_settings.php)
    var iconModalEl       = document.getElementById('ntasIconPickerModal');
    var colorModalEl      = document.getElementById('ntasColorPickerModal');
    var iconSearch        = document.getElementById('ntasIconSearch');
    var iconGrid          = document.getElementById('ntasIconGrid');
    var colorGrid         = document.getElementById('ntasColorGrid');
    var iconCustomInput   = document.getElementById('ntasIconCustomClass');
    var iconCustomApplyBtn = document.getElementById('ntasApplyCustomIcon');
    var eventModalEl      = document.getElementById('ntas-event-modal');

    function getModalInstance(el) {
        if (!el || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) return null;
        return window.bootstrap.Modal.getOrCreateInstance(el);
    }
    function getInputById(id) { return id ? document.getElementById(id) : null; }
    function getCurrentIconValue()  { var i = getInputById(currentIconTargetInputId);  return i ? i.value : 'fa-solid fa-bell'; }
    function getCurrentColorValue() { var c = getInputById(currentColorTargetInputId); return c ? c.value : ''; }

    // =====================================================================
    // Icon / Color preview synchronization
    // =====================================================================
    function syncPreview(scope) {
        if (!scope) return;
        var iconInput  = getInputById(iconInputByScope[scope] || '');
        var colorInput = getInputById(colorInputByScope[scope] || '');
        var iconValue  = iconInput ? iconInput.value : null;
        var colorValue = colorInput ? colorInput.value : null;

        if (iconValue !== null) {
            qsa('.js-ntas-icon-preview[data-preview-scope="' + scope + '"]').forEach(function (el) {
                el.className = 'js-ntas-icon-preview ' + iconValue;
            });
            qsa('.js-ntas-icon-label[data-preview-scope="' + scope + '"]').forEach(function (el) {
                el.textContent = iconMap[iconValue] || iconValue;
            });
        }
        if (colorValue !== null) {
            qsa('.js-ntas-color-preview[data-preview-scope="' + scope + '"]').forEach(function (el) {
                if (colorValue !== '') { el.style.backgroundColor = colorValue; el.classList.remove('is-default'); }
                else { el.style.backgroundColor = ''; el.classList.add('is-default'); }
            });
            qsa('.js-ntas-color-label[data-preview-scope="' + scope + '"]').forEach(function (el) {
                el.textContent = colorMap[colorValue] || (colorValue !== '' ? colorValue : 'Default sistema');
            });
        }

        // Update live preview if editing inside modal
        if (scope === 'ntas-modal') schedulePreviewUpdate();
    }

    // =====================================================================
    // Modal stacking fix — keep parent modal usable when sub-modal closes
    // =====================================================================
    function fixModalStacking() {
        if (!eventModalEl) return;
        [iconModalEl, colorModalEl].forEach(function (pickerEl) {
            if (!pickerEl) return;
            pickerEl.addEventListener('show.bs.modal', function () {
                // Raise picker z-index above event modal
                pickerEl.style.zIndex = '1070';
                // Bootstrap 5 appends the backdrop as a sibling after the modal — raise it too
                setTimeout(function () {
                    var backdrops = document.querySelectorAll('.modal-backdrop');
                    if (backdrops.length > 1) {
                        backdrops[backdrops.length - 1].style.zIndex = '1065';
                    }
                }, 10);
            });
            pickerEl.addEventListener('hidden.bs.modal', function () {
                // Re-add modal-open if event modal still visible
                if (eventModalEl.classList.contains('show')) {
                    document.body.classList.add('modal-open');
                    document.body.style.overflow = 'hidden';
                    document.body.style.paddingRight = '';
                }
            });
        });

        // Prevent event modal from stealing focus when picker modal is open
        eventModalEl.addEventListener('focusin', function (e) {
            var pickerVisible = (iconModalEl && iconModalEl.classList.contains('show')) ||
                                (colorModalEl && colorModalEl.classList.contains('show'));
            if (pickerVisible) e.stopPropagation();
        });
    }
    fixModalStacking();

    // =====================================================================
    // Icon picker grid
    // =====================================================================
    function renderIconGrid(filterText) {
        if (!iconGrid) return;
        var currentValue = getCurrentIconValue();
        var q = (filterText || '').trim().toLowerCase();
        var html = '';
        iconCatalog.forEach(function (item) {
            var haystack = (item.label + ' ' + item.value).toLowerCase();
            if (q !== '' && haystack.indexOf(q) === -1) return;
            var active = item.value === currentValue ? ' is-active' : '';
            html += '<button type="button" class="ntas-icon-option' + active + '" data-icon-value="' + item.value.replace(/"/g, '&quot;') + '" title="' + item.value.replace(/"/g, '&quot;') + '">' +
                '<i class="' + item.value + '"></i>' +
                '<span class="ntas-icon-option-label">' + item.label + '</span>' +
                '</button>';
        });
        iconGrid.innerHTML = html || '<div class="text-muted small">Nessuna icona trovata.</div>';
    }

    // =====================================================================
    // Color picker grid
    // =====================================================================
    function renderColorGrid() {
        if (!colorGrid) return;
        var currentValue = getCurrentColorValue();
        var html = '';
        colorCatalog.forEach(function (item) {
            var isDefault = item.value === '';
            var active = item.value === currentValue ? ' is-active' : '';
            var dotClass = 'ntas-color-option-dot' + (isDefault ? ' is-default' : '');
            var dotStyle = !isDefault ? ' style="background-color:' + item.value + '"' : '';
            html += '<button type="button" class="ntas-color-option' + active + '" data-color-value="' + item.value.replace(/"/g, '&quot;') + '" title="' + (item.value || 'Default') + '">' +
                '<span class="' + dotClass + '"' + dotStyle + '></span>' +
                '<span>' + item.label + '</span>' +
                '</button>';
        });
        colorGrid.innerHTML = html;
    }

    // =====================================================================
    // Bind icon / color openers
    // =====================================================================
    function bindModalOpeners(scope) {
        var root = scope || document;
        root.querySelectorAll('.js-ntas-open-icon-modal').forEach(function (btn) {
            var s = btn.getAttribute('data-preview-scope') || '';
            var inputId = btn.getAttribute('data-target-input') || '';
            if (s && inputId) iconInputByScope[s] = inputId;
            btn.addEventListener('click', function () {
                currentIconTargetInputId = inputId;
                currentPreviewScope = s;
                renderIconGrid(iconSearch ? iconSearch.value : '');
                if (iconCustomInput) iconCustomInput.value = getCurrentIconValue();
                var m = getModalInstance(iconModalEl);
                if (m) m.show();
                if (iconSearch) setTimeout(function () { iconSearch.focus(); }, 120);
            });
        });
        root.querySelectorAll('.js-ntas-open-color-modal').forEach(function (btn) {
            var s = btn.getAttribute('data-preview-scope') || '';
            var inputId = btn.getAttribute('data-target-input') || '';
            if (s && inputId) colorInputByScope[s] = inputId;
            btn.addEventListener('click', function () {
                currentColorTargetInputId = inputId;
                currentPreviewScope = s;
                renderColorGrid();
                var m = getModalInstance(colorModalEl);
                if (m) m.show();
            });
        });

        // Sync previews
        var scopes = {};
        Object.keys(iconInputByScope).forEach(function (k) { scopes[k] = true; });
        Object.keys(colorInputByScope).forEach(function (k) { scopes[k] = true; });
        Object.keys(scopes).forEach(function (k) { syncPreview(k); });
    }

    // =====================================================================
    // Token insertion into template fields
    // =====================================================================
    function insertTokenIntoField(field, token) {
        if (!field) return;
        var start = field.selectionStart || 0;
        var end   = field.selectionEnd || 0;
        var value = field.value || '';
        field.value = value.slice(0, start) + token + value.slice(end);
        var cursor = start + token.length;
        field.setSelectionRange(cursor, cursor);
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // =====================================================================
    // Live preview engine
    // =====================================================================
    function renderTemplateStr(template, vars) {
        if (!template) return '';
        var result = template;
        Object.keys(vars).forEach(function (key) {
            var val = vars[key];
            if (typeof val === 'string' || typeof val === 'number') {
                result = result.split('{{' + key + '}}').join(String(val));
            }
        });
        return result;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function nl2br(text) {
        return escapeHtml(text).replace(/\n/g, '<br>');
    }

    function updatePreview() {
        var modalContent = qs('#ntas-event-modal-content');
        if (!modalContent) return;

        var sampleData = parseJsonScript('ntas-modal-sample-data', {});

        // Get active channel tab
        var activeTab = qs('#ntas-channel-tabs .nav-link.active', modalContent);
        var activeChannel = activeTab ? (activeTab.getAttribute('data-channel') || 'in_app') : 'in_app';

        // Get icon / color
        var iconInput = qs('#ntas-modal-icon', modalContent);
        var colorInput = qs('#ntas-modal-color', modalContent);
        var icon  = iconInput ? iconInput.value : 'fa-solid fa-bell';
        var color = colorInput ? colorInput.value : '';

        // Get templates for active channel
        var pane = qs('#ntas-ch-pane-' + activeChannel, modalContent);
        var subjectInput = pane ? pane.querySelector('[data-field="subject"]') : null;
        var bodyInput    = pane ? pane.querySelector('[data-field="body"]') : null;
        var subjectTpl = subjectInput ? subjectInput.value : '{{title}}';
        var bodyTpl    = bodyInput ? bodyInput.value : '{{body}}';

        // Merge sample data with channel_slug
        var vars = Object.assign({}, sampleData, { channel_slug: activeChannel });

        var renderedSubject = renderTemplateStr(subjectTpl, vars) || vars.title || '';
        var renderedBody    = renderTemplateStr(bodyTpl, vars) || vars.body || '';

        // Show/hide preview panes
        var previewInApp   = qs('#ntas-preview-inapp', modalContent);
        var previewEmail   = qs('#ntas-preview-email', modalContent);
        var previewTelegram = qs('#ntas-preview-telegram', modalContent);
        if (previewInApp)   previewInApp.classList.toggle('d-none', activeChannel !== 'in_app');
        if (previewEmail)   previewEmail.classList.toggle('d-none', activeChannel !== 'email');
        if (previewTelegram) previewTelegram.classList.toggle('d-none', activeChannel !== 'telegram');

        // Update header icon
        var headerIcon = qs('#ntas-modal-icon-header', modalContent);
        if (headerIcon) {
            headerIcon.className = icon + ' js-ntas-modal-icon-preview';
            headerIcon.style.color = color || '';
        }

        // In-App preview — uses real .nt-item structure
        if (activeChannel === 'in_app' && previewInApp) {
            var inappIndicator = qs('#ntas-prev-inapp-indicator', previewInApp);
            if (inappIndicator) {
                inappIndicator.className = 'nt-indicator nt-' + escapeHtml(sampleData.type || 'info');
                if (color) { inappIndicator.style.backgroundColor = color; }
                else { inappIndicator.style.backgroundColor = ''; }
            }
            var inappIcon = qs('#ntas-prev-inapp-icon', previewInApp);
            if (inappIcon) {
                inappIcon.className = 'nt-type-icon nt-' + escapeHtml(sampleData.type || 'info');
                if (color) { inappIcon.style.color = color; }
                else { inappIcon.style.color = ''; }
                inappIcon.innerHTML = '<i class="' + escapeHtml(icon) + '"></i>';
            }
            var inappSubject = qs('#ntas-prev-inapp-subject', previewInApp);
            if (inappSubject) inappSubject.textContent = renderedSubject;
            var inappBody = qs('#ntas-prev-inapp-body', previewInApp);
            if (inappBody) inappBody.innerHTML = nl2br(renderedBody);
        }

        // Email preview
        if (activeChannel === 'email' && previewEmail) {
            var emailSubject = qs('#ntas-prev-email-subject', previewEmail);
            if (emailSubject) emailSubject.textContent = renderedSubject;
            var emailBody = qs('#ntas-prev-email-body', previewEmail);
            if (emailBody) emailBody.innerHTML = nl2br(renderedBody);
            var emailLink = qs('#ntas-prev-email-link', previewEmail);
            if (emailLink) {
                var linkVal = renderTemplateStr('{{link}}', vars);
                emailLink.style.display = linkVal && linkVal !== '{{link}}' ? '' : 'none';
            }
        }

        // Telegram preview
        if (activeChannel === 'telegram' && previewTelegram) {
            var tgBody = qs('#ntas-prev-tg-body', previewTelegram);
            if (tgBody) tgBody.innerHTML = nl2br(renderedBody);
        }
    }

    function schedulePreviewUpdate() {
        clearTimeout(previewDebounceTimer);
        previewDebounceTimer = setTimeout(updatePreview, 200);
    }

    // =====================================================================
    // Modal initialization after HTMX loads content
    // =====================================================================
    function initModalContent() {
        var modalContent = qs('#ntas-event-modal-content');
        if (!modalContent) return;

        // Bind icon / color pickers
        bindModalOpeners(modalContent);

        // Channel toggle indicator sync
        modalContent.querySelectorAll('.ntas-channel-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var ch = this.getAttribute('data-channel');
                var indicator = qs('#ntas-ch-indicator-' + ch, modalContent);
                if (indicator) {
                    indicator.classList.toggle('is-on', this.checked);
                    indicator.classList.toggle('is-off', !this.checked);
                }
            });
        });

        // Template input → live preview
        modalContent.querySelectorAll('.ntas-template-input').forEach(function (input) {
            input.addEventListener('input', schedulePreviewUpdate);
        });

        // Channel tab change → update preview
        modalContent.querySelectorAll('#ntas-channel-tabs .nav-link').forEach(function (tab) {
            tab.addEventListener('shown.bs.tab', function () {
                updatePreview();
            });
        });

        // Context chip click → insert token
        modalContent.querySelectorAll('.ntas-context-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var token = this.getAttribute('data-template-token') || '';
                if (token && lastTemplateField) {
                    insertTokenIntoField(lastTemplateField, token);
                }
            });
        });

        // Icon / color change → update preview (change event dispatched by picker handlers)
        var iconInput = qs('#ntas-modal-icon', modalContent);
        var colorInput = qs('#ntas-modal-color', modalContent);
        if (iconInput) iconInput.addEventListener('change', schedulePreviewUpdate);
        if (colorInput) colorInput.addEventListener('change', schedulePreviewUpdate);

        // Initial preview render
        updatePreview();
    }

    // =====================================================================
    // Table filter (client-side)
    // =====================================================================
    function initTableFilter() {
        var filterInput = document.getElementById('ntas-events-filter');
        if (!filterInput) return;

        filterInput.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var rows = qsa('.ntas-event-row');
            var moduleHeaders = qsa('.ntas-module-header-row');
            var moduleVisible = {};

            rows.forEach(function (row) {
                var name = row.getAttribute('data-event-name') || '';
                var slug = row.getAttribute('data-event-slug') || '';
                var mod  = row.getAttribute('data-module') || '';
                var match = q === '' || name.indexOf(q) !== -1 || slug.toLowerCase().indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) moduleVisible[mod] = true;
            });

            moduleHeaders.forEach(function (row) {
                var mod = row.getAttribute('data-module') || '';
                row.style.display = (q === '' || moduleVisible[mod]) ? '' : 'none';
            });
        });
    }

    // =====================================================================
    // Global event listeners
    // =====================================================================
    // Track last focused template field
    document.addEventListener('focusin', function (e) {
        if ((e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) &&
            e.target.classList.contains('ntas-template-input')) {
            lastTemplateField = e.target;
        }
    });

    // Icon search
    if (iconSearch) {
        iconSearch.addEventListener('input', function () { renderIconGrid(iconSearch.value || ''); });
    }

    // Icon grid click
    if (iconGrid) {
        iconGrid.addEventListener('click', function (e) {
            var btn = e.target instanceof HTMLElement ? e.target.closest('.ntas-icon-option') : null;
            if (!btn) return;
            var value = btn.getAttribute('data-icon-value') || 'fa-solid fa-bell';
            var input = getInputById(currentIconTargetInputId);
            if (input) { input.value = value; input.dispatchEvent(new Event('change', { bubbles: true })); }
            syncPreview(currentPreviewScope);
            var m = getModalInstance(iconModalEl);
            if (m) m.hide();
        });
    }

    // Custom icon apply
    if (iconCustomApplyBtn) {
        iconCustomApplyBtn.addEventListener('click', function () {
            var value = (iconCustomInput ? iconCustomInput.value : '').trim().replace(/\s+/g, ' ');
            if (value === '') return;
            var input = getInputById(currentIconTargetInputId);
            if (input) { input.value = value; input.dispatchEvent(new Event('change', { bubbles: true })); }
            if (!iconMap[value]) iconMap[value] = value;
            syncPreview(currentPreviewScope);
            var m = getModalInstance(iconModalEl);
            if (m) m.hide();
        });
    }

    // Color grid click
    if (colorGrid) {
        colorGrid.addEventListener('click', function (e) {
            var btn = e.target instanceof HTMLElement ? e.target.closest('.ntas-color-option') : null;
            if (!btn) return;
            var value = btn.getAttribute('data-color-value') || '';
            var input = getInputById(currentColorTargetInputId);
            if (input) { input.value = value; input.dispatchEvent(new Event('change', { bubbles: true })); }
            syncPreview(currentPreviewScope);
            var m = getModalInstance(colorModalEl);
            if (m) m.hide();
        });
    }

    // Reset icon picker on close
    if (iconModalEl) {
        iconModalEl.addEventListener('hidden.bs.modal', function () {
            if (iconSearch) iconSearch.value = '';
            if (iconCustomInput) iconCustomInput.value = '';
        });
    }

    // =====================================================================
    // HTMX integration — modal content loaded
    // =====================================================================
    document.body.addEventListener('htmx:afterSettle', function (e) {
        var target = e.detail ? e.detail.target : null;
        if (target && target.id === 'ntas-event-modal-content') {
            // Ensure HTMX processes hx-* attributes on the new form
            if (typeof htmx !== 'undefined' && htmx.process) {
                htmx.process(target);
            }
            initModalContent();
        }
    });

    // =====================================================================
    // HX-Trigger custom events
    // =====================================================================
    document.body.addEventListener('ntasCloseModal', function () {
        if (eventModalEl) {
            var m = getModalInstance(eventModalEl);
            if (m) m.hide();
        }
    });

    document.body.addEventListener('ntasRefreshEventsTable', function () {
        // Full page reload to refresh the events table (simple and reliable)
        window.location.reload();
    });

    // =====================================================================
    // Bootstrap init on page load
    // =====================================================================
    bindModalOpeners();
    initTableFilter();
})();

