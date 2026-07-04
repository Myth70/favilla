/**
 * Intranet — app.js
 * Global initializations: HTMX CSRF, dark/light mode, sidebar, toast notifications.
 * Loaded LAST in the layout, after Bootstrap and HTMX.
 */
(function () {
    'use strict';

    // ========================================================================
    // HTMX — CSRF token on every request
    // ========================================================================
    document.addEventListener('htmx:configRequest', function (e) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            e.detail.headers['X-CSRF-Token'] = meta.content;
        }
    });

    // ========================================================================
    // XHR POST helper with error handling
    // ========================================================================
    function xhrPost(url, body, csrfToken) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-CSRF-Token', csrfToken);
        xhr.onerror = function () {
            console.warn('[Favilla] Richiesta fallita:', url);
        };
        xhr.onload = function () {
            if (xhr.status >= 400) {
                console.warn('[Favilla] Risposta ' + xhr.status + ':', url);
            }
        };
        xhr.send(body);
    }

    // ========================================================================
    // Safe localStorage wrapper (disabled in some private browsing modes)
    // ========================================================================
    var storage = {
        get: function (key) {
            try { return localStorage.getItem(key); } catch (e) { return null; }
        },
        set: function (key, value) {
            try { localStorage.setItem(key, value); } catch (e) { /* silent */ }
        }
    };

    // ========================================================================
    // Theme system — single client-side source of truth for UI preferences
    // ========================================================================
    var ThemeSystem = {
        EVENTS: {
            stateChanged: 'favilla:theme-state-changed',
            theme: 'themeChanged',
            accent: 'accentChanged',
            skin: 'skinChanged',
            font: 'fontChanged',
            sidebarStyle: 'sidebarStyleChanged',
            pattern: 'patternChanged'
        },

        STORAGE_KEYS: {
            theme: 'intranet_theme'
        },

        DEFAULT_ACCENT: '#3b82f6',

        DEFAULT_PATTERNS: ['circles', 'triangles', 'hexagons', 'diamonds', 'pentagons', 'lines', 'curves', 'grid', 'chevrons', 'mesh'],

        FONT_STACKS: {
            'system':    'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            'inter':     '"Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            'plex':      '"IBM Plex Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
            'lora':      '"Lora", Georgia, Cambria, "Times New Roman", serif',
            'jetbrains': '"JetBrains Mono", ui-monospace, Menlo, Consolas, "Courier New", monospace'
        },

        getRoot: function () {
            return document.documentElement;
        },

        readCssVar: function (name, fallback) {
            var value = getComputedStyle(ThemeSystem.getRoot()).getPropertyValue(name).trim();
            return value || fallback || '';
        },

        getAllowedPatterns: function () {
            var wrap = document.getElementById('pf-pattern-settings');
            var allowed = [];

            try {
                allowed = JSON.parse(wrap && wrap.dataset.allowedPatterns || '[]');
            } catch (e) {
                allowed = [];
            }

            return allowed.length ? allowed : ThemeSystem.DEFAULT_PATTERNS.slice();
        },

        getCurrentPattern: function () {
            var root = ThemeSystem.getRoot();
            var attrPattern = root.getAttribute('data-theme-pattern');
            if (attrPattern) {
                return attrPattern;
            }

            var source = document.querySelector('.pf-hero-header, .app-header, #app-sidebar');
            if (!source) {
                return 'circles';
            }

            var match = source.className.match(/\bpf-pattern-([a-z]+)\b/);
            return match ? match[1] : 'circles';
        },

        getState: function () {
            var root = ThemeSystem.getRoot();
            return {
                theme: root.getAttribute('data-bs-theme') || 'light',
                skin: root.getAttribute('data-theme-skin') || 'default',
                font: root.getAttribute('data-theme-font') || 'system',
                sidebarStyle: root.getAttribute('data-sidebar-style') || 'default',
                accent: ThemeSystem.readCssVar('--accent-color', ThemeSystem.DEFAULT_ACCENT) || ThemeSystem.DEFAULT_ACCENT,
                pattern: ThemeSystem.getCurrentPattern()
            };
        },

        syncTileSelection: function (selector, attributeName, value) {
            document.querySelectorAll(selector).forEach(function (el) {
                el.classList.toggle('active', el.getAttribute(attributeName) === value);
            });
        },

        syncThemeIcon: function (theme) {
            var icon = document.getElementById('theme-icon');
            if (!icon) return;

            icon.className = theme === 'dark'
                ? 'fa-solid fa-sun'
                : 'fa-solid fa-moon';
        },

        syncThemeButtons: function (theme) {
            var lightBtn = document.getElementById('pf-theme-light');
            var darkBtn = document.getElementById('pf-theme-dark');

            if (lightBtn) {
                lightBtn.classList.toggle('active', theme === 'light');
            }

            if (darkBtn) {
                darkBtn.classList.toggle('active', theme === 'dark');
            }
        },

        syncAccentControls: function (color) {
            document.querySelectorAll('.accent-swatch').forEach(function (swatch) {
                swatch.classList.toggle('active', swatch.dataset.color === color);
            });
        },

        syncPatternButtons: function (pattern) {
            document.querySelectorAll('.pf-pattern-btn').forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.pattern === pattern);
            });
        },

        clearPatternClasses: function (el) {
            if (!el) return;

            el.className = el.className
                .replace(/\bpf-pattern-[a-z]+\b/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        },

        syncSidebarPattern: function (style) {
            var sidebar = document.getElementById('app-sidebar');
            if (!sidebar) return;

            ThemeSystem.clearPatternClasses(sidebar);

            if (style === 'accent') {
                sidebar.classList.add('pf-pattern-' + ThemeSystem.getCurrentPattern());
            }
        },

        syncState: function () {
            var state = ThemeSystem.getState();

            ThemeSystem.syncThemeIcon(state.theme);
            ThemeSystem.syncThemeButtons(state.theme);
            ThemeSystem.syncAccentControls(state.accent);
            ThemeSystem.syncTileSelection('.pf-skin-tile', 'data-skin', state.skin);
            ThemeSystem.syncTileSelection('.pf-font-tile', 'data-font', state.font);
            ThemeSystem.syncTileSelection('.pf-sidebar-style-tile', 'data-sidebar-style', state.sidebarStyle);
            ThemeSystem.syncPatternButtons(state.pattern);
            ThemeSystem.syncSidebarPattern(state.sidebarStyle);
        },

        emit: function (eventName, detail, changedKeys) {
            if (eventName) {
                document.dispatchEvent(new CustomEvent(eventName, {
                    detail: detail || {}
                }));
            }

            document.dispatchEvent(new CustomEvent(ThemeSystem.EVENTS.stateChanged, {
                detail: {
                    state: ThemeSystem.getState(),
                    changedKeys: changedKeys || []
                }
            }));
        },

        persist: function (url, body) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta || !url) return;

            xhrPost(url, body, meta.content);
        },

        applyTheme: function (theme) {
            ThemeSystem.getRoot().setAttribute('data-bs-theme', theme);
            ThemeSystem.syncThemeIcon(theme);
            ThemeSystem.syncThemeButtons(theme);
            ThemeSystem.emit(ThemeSystem.EVENTS.theme, { theme: theme }, ['theme']);
        },

        persistTheme: function (theme) {
            storage.set(ThemeSystem.STORAGE_KEYS.theme, theme);
            ThemeSystem.persist(document.body.dataset.themeUrl || '/preferences/theme',
                'theme=' + encodeURIComponent(theme));
        },

        toggleTheme: function () {
            var nextTheme = ThemeSystem.getState().theme === 'dark' ? 'light' : 'dark';
            ThemeSystem.applyTheme(nextTheme);
            ThemeSystem.persistTheme(nextTheme);
            return nextTheme;
        },

        applyAccent: function (color) {
            ThemeSystem.getRoot().style.setProperty('--accent', color);
            ThemeSystem.syncAccentControls(color);
            ThemeSystem.emit(ThemeSystem.EVENTS.accent, { color: color }, ['accent']);
        },

        persistAccent: function (color) {
            ThemeSystem.persist(document.body.dataset.colorUrl || '/preferences/color',
                'color=' + encodeURIComponent(color));
        },

        applySkin: function (skin) {
            ThemeSystem.getRoot().setAttribute('data-theme-skin', skin);
            ThemeSystem.syncTileSelection('.pf-skin-tile', 'data-skin', skin);
            ThemeSystem.emit(ThemeSystem.EVENTS.skin, { skin: skin }, ['skin']);
        },

        persistSkin: function (skin) {
            ThemeSystem.persist(document.body.dataset.skinUrl || '/preferences/skin',
                'skin=' + encodeURIComponent(skin));
        },

        applyFont: function (font) {
            var stack = ThemeSystem.FONT_STACKS[font] || ThemeSystem.FONT_STACKS.system;
            var root = ThemeSystem.getRoot();

            root.style.setProperty('--font-family-base', stack);
            root.style.setProperty('--font-family-heading', stack);
            root.setAttribute('data-theme-font', font);

            ThemeSystem.syncTileSelection('.pf-font-tile', 'data-font', font);
            ThemeSystem.emit(ThemeSystem.EVENTS.font, { font: font, stack: stack }, ['font']);
        },

        persistFont: function (font) {
            ThemeSystem.persist(document.body.dataset.fontUrl || '/preferences/font',
                'font=' + encodeURIComponent(font));
        },

        applySidebarStyle: function (style) {
            ThemeSystem.getRoot().setAttribute('data-sidebar-style', style);
            ThemeSystem.syncTileSelection('.pf-sidebar-style-tile', 'data-sidebar-style', style);
            ThemeSystem.syncSidebarPattern(style);
            ThemeSystem.emit(ThemeSystem.EVENTS.sidebarStyle, { style: style }, ['sidebarStyle']);
        },

        persistSidebarStyle: function (style) {
            ThemeSystem.persist(document.body.dataset.sidebarStyleUrl || '/preferences/sidebar-style',
                'style=' + encodeURIComponent(style));
        },

        applyPattern: function (pattern) {
            var allowed = ThemeSystem.getAllowedPatterns();
            var normalizedPattern = allowed.indexOf(pattern) !== -1 ? pattern : allowed[0] || 'circles';
            var classList = allowed.map(function (item) {
                return 'pf-pattern-' + item;
            });

            document.querySelectorAll('.pf-hero-header, .app-header').forEach(function (el) {
                el.classList.remove.apply(el.classList, classList);
                el.classList.add('pf-pattern-' + normalizedPattern);
            });

            ThemeSystem.getRoot().setAttribute('data-theme-pattern', normalizedPattern);
            ThemeSystem.syncPatternButtons(normalizedPattern);
            ThemeSystem.syncSidebarPattern(ThemeSystem.getState().sidebarStyle);
            ThemeSystem.emit(ThemeSystem.EVENTS.pattern, { pattern: normalizedPattern }, ['pattern']);
        },

        persistPattern: function (pattern, url) {
            var wrap = document.getElementById('pf-pattern-settings');
            ThemeSystem.persist(url || (wrap && wrap.dataset.patternUrl) || '/preferences/pattern',
                'pattern=' + encodeURIComponent(pattern));
        },

        init: function () {
            ThemeSystem.syncState();
        }
    };

    window.FavillaTheme = ThemeSystem;

    // ========================================================================
    // Dark / Light mode toggle
    // ========================================================================
    var ThemeManager = {
        STORAGE_KEY: ThemeSystem.STORAGE_KEYS.theme,

        init: function () {
            var btn = document.getElementById('theme-toggle-btn');
            if (!btn) return;

            btn.addEventListener('click', function () {
                ThemeSystem.toggleTheme();
            });

            // Apply saved theme (already done server-side via data-bs-theme, but sync icon)
            ThemeSystem.syncThemeIcon(ThemeSystem.getState().theme);
        },

        getCurrent: function () {
            return ThemeSystem.getState().theme;
        },

        apply: function (theme) {
            ThemeSystem.applyTheme(theme);
        },

        updateIcon: function (theme) {
            ThemeSystem.syncThemeIcon(theme);
        },

        save: function (theme) {
            ThemeSystem.persistTheme(theme);
        }
    };

    // ========================================================================
    // Sidebar toggle
    // ========================================================================
    var SidebarManager = {
        STORAGE_KEY: 'intranet_sidebar_collapsed',

        init: function () {
            var body = document.body;

            // Collapsed state is rendered server-side on <body class="sidebar-collapsed">.
            // No client-side restore needed — avoids flash on page load.

            // Desktop collapse toggle
            var collapseBtn = document.getElementById('sidebar-collapse-btn');
            if (collapseBtn) {
                collapseBtn.addEventListener('click', function () {
                    body.classList.toggle('sidebar-collapsed');
                    var collapsed = body.classList.contains('sidebar-collapsed') ? '1' : '0';
                    storage.set(SidebarManager.STORAGE_KEY, collapsed);
                    // Persist to server
                    SidebarManager.saveState(collapsed);
                    // Update tooltip label to reflect new state
                    var tip = bootstrap.Tooltip.getInstance(collapseBtn);
                    if (tip) {
                        tip.setContent({ '.tooltip-inner': collapsed === '1' ? t('js.sidebar.expand', 'Espandi sidebar') : t('js.sidebar.collapse', 'Comprimi sidebar') });
                    }
                    // Show/hide menu icon tooltips
                    SidebarManager.updateMenuTooltips(collapsed === '1');
                });
            }

            // Mobile open
            var toggleBtn = document.getElementById('sidebar-toggle-btn');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    body.classList.add('sidebar-open');
                });
            }

            // Mobile close
            var closeBtn = document.getElementById('sidebar-close-btn');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    body.classList.remove('sidebar-open');
                });
            }

            // Close on overlay click
            var overlay = document.getElementById('sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', function () {
                    body.classList.remove('sidebar-open');
                });
            }

            // In collapsed (icon-only) mode, accordion toggle links navigate
            // to the parent route instead of trying to open the hidden submenu.
            document.querySelectorAll('[data-sidebar-route]').forEach(function (link) {
                link.addEventListener('click', function () {
                    if (body.classList.contains('sidebar-collapsed')) {
                        window.location.href = link.dataset.sidebarRoute;
                    }
                });
            });

            // If page loaded with sidebar already collapsed, init menu tooltips
            // and fix the collapse button label (title in HTML is always default)
            if (body.classList.contains('sidebar-collapsed')) {
                SidebarManager.updateMenuTooltips(true);
                var tip = bootstrap.Tooltip.getInstance(collapseBtn);
                if (tip) {
                    tip.setContent({ '.tooltip-inner': t('js.sidebar.expand', 'Espandi sidebar') });
                }
            }
        },

        updateMenuTooltips: function (collapsed) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            document.querySelectorAll('.sidebar-menu-link[data-sidebar-label], .sidebar-admin-toggle[data-sidebar-label], .sidebar-admin-btn[data-sidebar-label], .sidebar-admin-submenu .dropdown-item[data-sidebar-label]').forEach(function (link) {
                var existing = bootstrap.Tooltip.getInstance(link);
                if (collapsed) {
                    if (!existing) {
                        new bootstrap.Tooltip(link, {
                            placement: 'right',
                            title: link.getAttribute('data-sidebar-label'),
                            trigger: 'hover',
                            container: 'body',
                            boundary: 'viewport'
                        });
                    }
                } else {
                    if (existing) {
                        existing.hide();
                        existing.dispose();
                    }
                }
            });
        },

        saveState: function (collapsed) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) return;
            xhrPost(document.body.dataset.sidebarUrl || '/preferences/sidebar',
                    'sidebar_collapsed=' + encodeURIComponent(collapsed), meta.content);
        }
    };

    // ========================================================================
    // Accent color manager — palette swatches
    // ========================================================================
    var ColorManager = {
        apply: function (color) {
            ThemeSystem.applyAccent(color);
        },

        save: function (color) {
            ThemeSystem.persistAccent(color);
        },

        hideTooltip: function (element) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip || !element) return;

            var tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) {
                tooltip.hide();
            }
        },

        closeDropdown: function (swatch) {
            if (!swatch) return;

            var dropdown = swatch.closest('.dropdown');
            if (!dropdown) return;

            var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
            if (!toggle) return;

            ColorManager.hideTooltip(swatch);
            if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;

            ColorManager.hideTooltip(toggle);
            bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
        },

        init: function () {
            // Direct binding on each swatch avoids Bootstrap dropdown interference.
            // Chiude esplicitamente il dropdown per evitare dipendenze dall'autoclose implicito.
            document.querySelectorAll('.accent-swatch').forEach(function (swatch) {
                swatch.addEventListener('click', function () {
                    var color = swatch.dataset.color;
                    if (!color) return;
                    ColorManager.apply(color);
                    ColorManager.save(color);
                    ColorManager.closeDropdown(swatch);
                });
            });
        }
    };

    // ========================================================================
    // Profile preferences page — synced controls
    // ========================================================================
    var ProfilePreferences = {
        init: function () {
            // Theme buttons on profile page (light / dark)
            var lightBtn = document.getElementById('pf-theme-light');
            var darkBtn  = document.getElementById('pf-theme-dark');
            if (lightBtn) {
                lightBtn.addEventListener('click', function () {
                    ThemeSystem.applyTheme('light');
                    ThemeSystem.persistTheme('light');
                });
            }
            if (darkBtn) {
                darkBtn.addEventListener('click', function () {
                    ThemeSystem.applyTheme('dark');
                    ThemeSystem.persistTheme('dark');
                });
            }

            // Sidebar toggle on profile page
            var sidebarToggle = document.getElementById('profile-sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('change', function () {
                    var collapsed = sidebarToggle.checked ? '1' : '0';
                    document.body.classList.toggle('sidebar-collapsed', sidebarToggle.checked);
                    storage.set(SidebarManager.STORAGE_KEY, collapsed);
                    SidebarManager.saveState(collapsed);
                    var label = document.getElementById('profile-sidebar-label');
                    if (label) label.textContent = sidebarToggle.checked ? t('js.sidebar.state_collapsed', 'Compressa') : t('js.sidebar.state_expanded', 'Espansa');
                    if (window.notify) window.notify(t('js.sidebar.pref_saved', 'Preferenza sidebar salvata'), 'success');
                });
            }

            ProfilePreferences.initPatternPicker();
        },

        initPatternPicker: function () {
            var wrap = document.getElementById('pf-pattern-settings');
            if (!wrap) return;

            wrap.querySelectorAll('.pf-pattern-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var pattern = btn.dataset.pattern;
                    if (!pattern) return;

                    ProfilePreferences.applyPattern(pattern);
                    ProfilePreferences.savePattern(pattern, wrap.dataset.patternUrl || '/preferences/pattern');
                });
            });
        },

        applyPattern: function (pattern) {
            ThemeSystem.applyPattern(pattern);
        },

        savePattern: function (pattern, url) {
            ThemeSystem.persistPattern(pattern, url);
        },

        syncThemeLabel: function (theme) {
            ThemeSystem.syncThemeButtons(theme);
        }
    };

    // ========================================================================
    // Feedback system
    // window.notify('Messaggio', 'success')
    // window.notify({ message: '...', type: 'warning', channel: 'banner' })
    // ========================================================================
    var NOTIFY_ICONS = {
        success: 'fa-solid fa-circle-check',
        danger:  'fa-solid fa-circle-xmark',
        warning: 'fa-solid fa-triangle-exclamation',
        info:    'fa-solid fa-circle-info'
    };

    var FEEDBACK_DEFAULT_DURATION = 4000;
    var FEEDBACK_LONG_DURATION = 8000;

    function normalizeFeedbackType(type) {
        var normalized = String(type || 'info').toLowerCase();

        if (normalized === 'error') {
            return 'danger';
        }

        if (['success', 'danger', 'warning', 'info'].indexOf(normalized) === -1) {
            return 'info';
        }

        return normalized;
    }

    function parseFeedbackNumber(value, fallback) {
        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? fallback : parsed;
    }

    function parseFeedbackJson(text) {
        if (!text || typeof text !== 'string') return null;

        try {
            return JSON.parse(text);
        } catch (e) {
            return null;
        }
    }

    var FeedbackManager = {
        normalize: function (input, legacyType, legacyOptions) {
            var payload = {};
            var options = legacyOptions;

            if (input && typeof input === 'object' && !Array.isArray(input)) {
                payload = Object.assign({}, input);
                if (legacyType && typeof legacyType === 'object' && !Array.isArray(legacyType)) {
                    options = legacyType;
                }
            } else {
                payload = {
                    message: input,
                    type: legacyType
                };
            }

            if (options && typeof options === 'object' && !Array.isArray(options)) {
                payload = Object.assign(payload, options);
            }

            var type = normalizeFeedbackType(payload.level || payload.type || 'info');
            var channel = String(payload.channel || payload.surface || 'toast').toLowerCase();
            var retryAfter = Math.max(0, parseFeedbackNumber(payload.retryAfter || payload.retry_after, 0));
            var persistent = !!payload.persistent || payload.duration === 0 || payload.autohide === false;
            var duration = parseFeedbackNumber(payload.duration, type === 'danger' ? FEEDBACK_LONG_DURATION : FEEDBACK_DEFAULT_DURATION);

            if (channel !== 'banner') {
                channel = 'toast';
            }

            if (channel === 'banner' && retryAfter > 0) {
                persistent = true;
            }

            if (persistent) {
                duration = 0;
            }

            return {
                title: payload.title ? String(payload.title) : '',
                message: payload.message != null ? String(payload.message) : '',
                type: type,
                channel: channel,
                duration: duration,
                persistent: persistent,
                retryAfter: retryAfter,
                dismissible: payload.dismissible !== false,
                actions: Array.isArray(payload.actions) ? payload.actions.filter(function (action) {
                    return action && action.label;
                }) : [],
                source: payload.source ? String(payload.source) : '',
                key: payload.key ? String(payload.key) : ''
            };
        },

        ensureContainer: function (id, className, live) {
            var container = document.getElementById(id);
            if (container) return container;

            container = document.createElement('div');
            container.id = id;
            container.className = className;
            container.setAttribute('aria-live', live || 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
            return container;
        },

        getToastContainer: function () {
            return FeedbackManager.ensureContainer(
                'toast-container',
                'toast-container position-fixed bottom-0 end-0 p-3',
                'polite'
            );
        },

        getBannerContainer: function () {
            return FeedbackManager.ensureContainer(
                'feedback-banner-container',
                'position-fixed top-0 start-50 translate-middle-x p-3 d-flex flex-column gap-2 w-100',
                'assertive'
            );
        },

        appendText: function (parent, tagName, className, text) {
            if (!text) return null;

            var el = document.createElement(tagName);
            if (className) el.className = className;
            el.appendChild(document.createTextNode(String(text)));
            parent.appendChild(el);
            return el;
        },

        attachRetryAfter: function (parent, payload, variant) {
            if (!payload.retryAfter) return null;

            var meta = document.createElement('div');
            meta.className = variant === 'banner'
                ? 'small opacity-75 mt-2'
                : 'small mt-2 ' + ((payload.type === 'success' || payload.type === 'danger') ? 'text-white-50' : 'opacity-75');

            var remaining = payload.retryAfter;
            function render() {
                if (remaining <= 0) {
                    meta.textContent = t('js.feedback.retry.now', 'Puoi riprovare adesso.');
                    return;
                }

                meta.textContent = (remaining === 1
                    ? t('js.feedback.retry.countdown_one', 'Riprova tra :n secondo.')
                    : t('js.feedback.retry.countdown_other', 'Riprova tra :n secondi.')
                ).replace(':n', remaining);
            }

            render();
            parent.appendChild(meta);

            var timer = window.setInterval(function () {
                remaining -= 1;
                render();
                if (remaining <= 0) {
                    window.clearInterval(timer);
                }
            }, 1000);

            return timer;
        },

        appendActions: function (parent, payload, dismiss) {
            if (!payload.actions.length) return;

            var actionsWrap = document.createElement('div');
            actionsWrap.className = 'd-flex flex-wrap gap-2 mt-3';

            payload.actions.forEach(function (action) {
                var href = action.href || '';
                var control = document.createElement(href ? 'a' : 'button');

                if (href) {
                    control.href = href;
                } else {
                    control.type = 'button';
                }

                control.className = action.className || (payload.channel === 'banner'
                    ? 'btn btn-sm btn-outline-secondary'
                    : 'btn btn-sm btn-light');
                control.appendChild(document.createTextNode(String(action.label)));

                if (action.dismiss !== false) {
                    control.addEventListener('click', function () {
                        dismiss();
                    });
                }

                actionsWrap.appendChild(control);
            });

            parent.appendChild(actionsWrap);
        },

        showToast: function (payload) {
            var container = FeedbackManager.getToastContainer();
            var toast = document.createElement('div');
            var type = payload.type;

            toast.className = 'toast border-0 shadow-sm text-bg-' + type;
            toast.setAttribute('role', (type === 'danger' || type === 'warning') ? 'alert' : 'status');
            toast.setAttribute('aria-live', type === 'danger' ? 'assertive' : 'polite');
            toast.setAttribute('aria-atomic', 'true');
            if (payload.key) toast.dataset.feedbackKey = payload.key;
            if (payload.source) toast.dataset.feedbackSource = payload.source;

            var layout = document.createElement('div');
            layout.className = 'd-flex';

            var body = document.createElement('div');
            body.className = 'toast-body';

            var content = document.createElement('div');
            content.className = 'd-flex align-items-start gap-3';

            var icon = document.createElement('i');
            icon.className = (NOTIFY_ICONS[type] || NOTIFY_ICONS.info) + ' mt-1 flex-shrink-0';
            content.appendChild(icon);

            var copy = document.createElement('div');
            copy.className = 'flex-grow-1';
            FeedbackManager.appendText(copy, 'div', payload.title ? 'fw-semibold mb-1' : 'fw-semibold', payload.title || payload.message);
            if (payload.title && payload.message) {
                FeedbackManager.appendText(copy, 'div', 'small', payload.message);
            }

            content.appendChild(copy);
            body.appendChild(content);
            layout.appendChild(body);

            var retryTimer = FeedbackManager.attachRetryAfter(copy, payload, 'toast');
            function dismiss() {
                var instance = bootstrap.Toast.getInstance(toast);
                if (instance) {
                    instance.hide();
                } else {
                    toast.remove();
                }
            }

            FeedbackManager.appendActions(copy, payload, dismiss);

            if (payload.dismissible) {
                var closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn-close me-2 m-auto';
                closeBtn.setAttribute('data-bs-dismiss', 'toast');
                closeBtn.setAttribute('aria-label', t('js.common.close', 'Chiudi'));

                if (type === 'success' || type === 'danger') {
                    closeBtn.classList.add('btn-close-white');
                }

                layout.appendChild(closeBtn);
            }

            toast.appendChild(layout);
            container.appendChild(toast);

            var bsToast = new bootstrap.Toast(toast, {
                autohide: !payload.persistent && payload.duration > 0,
                delay: payload.duration > 0 ? payload.duration : FEEDBACK_LONG_DURATION
            });

            toast.addEventListener('hidden.bs.toast', function () {
                if (retryTimer) window.clearInterval(retryTimer);
                toast.remove();
            });

            bsToast.show();
            return toast;
        },

        showBanner: function (payload) {
            var container = FeedbackManager.getBannerContainer();
            var banner = document.createElement('div');
            var variant = payload.type === 'danger' ? 'danger' : (payload.type === 'success' ? 'success' : (payload.type === 'warning' ? 'warning' : 'info'));

            if (payload.source) {
                container.querySelectorAll('[data-feedback-source="' + payload.source.replace(/"/g, '') + '"]').forEach(function (existing) {
                    existing.remove();
                });
            }

            banner.className = 'alert alert-' + variant + ' border shadow-sm mb-0 mx-auto d-flex align-items-start gap-3';
            banner.setAttribute('role', 'alert');
            banner.style.maxWidth = '680px';
            banner.style.width = 'min(680px, calc(100vw - 2rem))';
            if (payload.key) banner.dataset.feedbackKey = payload.key;
            if (payload.source) banner.dataset.feedbackSource = payload.source;

            var icon = document.createElement('i');
            icon.className = (NOTIFY_ICONS[payload.type] || NOTIFY_ICONS.info) + ' mt-1 flex-shrink-0';
            banner.appendChild(icon);

            var copy = document.createElement('div');
            copy.className = 'flex-grow-1';
            FeedbackManager.appendText(copy, 'div', payload.title ? 'fw-semibold mb-1' : 'fw-semibold', payload.title || payload.message);
            if (payload.title && payload.message) {
                FeedbackManager.appendText(copy, 'div', '', payload.message);
            }

            var retryTimer = FeedbackManager.attachRetryAfter(copy, payload, 'banner');
            function dismiss() {
                if (retryTimer) window.clearInterval(retryTimer);
                banner.remove();
            }

            FeedbackManager.appendActions(copy, payload, dismiss);
            banner.appendChild(copy);

            if (payload.dismissible) {
                var closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn-close';
                closeBtn.setAttribute('aria-label', t('js.common.close', 'Chiudi'));
                closeBtn.addEventListener('click', dismiss);
                banner.appendChild(closeBtn);
            }

            container.appendChild(banner);

            if (!payload.persistent && payload.duration > 0) {
                window.setTimeout(dismiss, payload.duration);
            }

            return banner;
        },

        fromXhr: function (xhr, fallback) {
            if (!xhr) return null;

            var response = parseFeedbackJson(xhr.responseText || '');
            var message = '';

            if (response && typeof response === 'object') {
                if (response.feedback && typeof response.feedback === 'object') {
                    return FeedbackManager.normalize(response.feedback);
                }

                if (typeof response.message === 'string' && response.message !== '') {
                    message = response.message;
                } else if (typeof response.error === 'string' && response.error !== '') {
                    message = response.error;
                }
            }

            if (xhr.status === 429) {
                return FeedbackManager.normalize({
                    title: t('js.feedback.title.rate_limit', 'Attendi un momento'),
                    message: message || fallback || t('js.feedback.message.rate_limit', 'Troppe richieste. Riprova tra poco.'),
                    type: 'warning',
                    channel: 'banner',
                    retryAfter: response && response.retry_after,
                    persistent: true,
                    source: 'rate-limit'
                });
            }

            if (message) {
                return FeedbackManager.normalize({
                    title: xhr.status >= 500 ? t('js.feedback.title.server_error', 'Operazione non completata') : '',
                    message: message,
                    type: xhr.status >= 500 ? 'danger' : 'warning',
                    channel: xhr.status >= 500 ? 'toast' : 'banner',
                    duration: xhr.status >= 500 ? FEEDBACK_LONG_DURATION : 0,
                    persistent: xhr.status < 500,
                    source: xhr.status >= 500 ? 'server-error' : 'request-warning'
                });
            }

            return fallback ? FeedbackManager.normalize(fallback) : null;
        },

        show: function (input, legacyType, legacyOptions) {
            var payload = FeedbackManager.normalize(input, legacyType, legacyOptions);
            if (!payload.title && !payload.message) return null;

            return payload.channel === 'banner'
                ? FeedbackManager.showBanner(payload)
                : FeedbackManager.showToast(payload);
        }
    };

    window.FavillaFeedback = FeedbackManager;
    window.notify = function (input, legacyType, legacyOptions) {
        return FeedbackManager.show(input, legacyType, legacyOptions);
    };
    window.showToast = function (input, legacyType, legacyOptions) {
        var payload = FeedbackManager.normalize(input, legacyType, legacyOptions);
        payload.channel = 'toast';
        return FeedbackManager.show(payload);
    };

    // ========================================================================
    // Select filter helper (for long dropdowns)
    // ========================================================================
    document.addEventListener('click', function (e) {
        var clearBtn = e.target.closest('[data-app-clear-target]');
        if (!clearBtn) return;

        var targetId = clearBtn.getAttribute('data-app-clear-target');
        var target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;

        target.innerHTML = '';
    });

    document.addEventListener('input', function (e) {
        var input = e.target.closest('[data-app-filter-select]');
        if (!input) return;

        var selectId = input.getAttribute('data-app-filter-select');
        var select = document.getElementById(selectId);
        if (!select) return;

        var query = (input.value || '').toLowerCase().trim();
        var visibleCount = 0;

        Array.prototype.forEach.call(select.options, function (opt, idx) {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }

            var match = query === '' || (opt.textContent || '').toLowerCase().indexOf(query) !== -1;
            opt.hidden = !match;
            if (match) visibleCount++;
        });

        if (visibleCount === 0) {
            select.selectedIndex = 0;
        }
    });

    // ========================================================================
    // SoD modal client-side guard: role1 must differ from role2
    // ========================================================================
    function initSodRoleGuard() {
        var roleA = document.querySelector('[data-sod-role="a"]');
        var roleB = document.querySelector('[data-sod-role="b"]');
        var warning = document.getElementById('adm-sod-role-warning');
        var submitBtn = document.getElementById('adm-sod-submit');
        if (!roleA || !roleB || !warning || !submitBtn) return;

        function syncSodState() {
            var same = roleA.value !== '' && roleA.value === roleB.value;
            warning.classList.toggle('d-none', !same);
            submitBtn.disabled = same;
            roleB.setCustomValidity(same ? 'I due ruoli devono essere diversi.' : '');
        }

        roleA.addEventListener('change', syncSodState);
        roleB.addEventListener('change', syncSodState);
        syncSodState();
    }

    initSodRoleGuard();
    document.body.addEventListener('htmx:afterSwap', initSodRoleGuard);

    // ========================================================================
    // HTMX — show toast on server errors
    // ========================================================================
    document.addEventListener('htmx:responseError', function (e) {
        var xhr = e.detail && e.detail.xhr;
        var status = xhr && xhr.status;
        var url = e.detail && e.detail.requestConfig && e.detail.requestConfig.path;
        if (url && url.indexOf('/search/quick') !== -1) return;

        if (xhr && typeof xhr.getResponseHeader === 'function') {
            var triggerHeader = xhr.getResponseHeader('HX-Trigger') || '';
            if (triggerHeader.indexOf('notify') !== -1) {
                return;
            }
        }

        var payload = FeedbackManager.fromXhr(xhr, {
            title: status >= 500 ? t('js.feedback.title.server_error', 'Operazione non completata') : '',
            message: t('js.feedback.message.server_error', 'Errore di comunicazione con il server.'),
            type: status === 429 ? 'warning' : 'danger',
            channel: status === 429 ? 'banner' : 'toast',
            duration: status === 429 ? 0 : FEEDBACK_LONG_DURATION,
            persistent: status === 429,
            source: status === 429 ? 'rate-limit' : 'server-error'
        });

        if (payload) {
            window.notify(payload);
        }
    });

    document.addEventListener('htmx:sendError', function () {
        window.notify({
            title: t('js.feedback.title.network', 'Connessione non disponibile'),
            message: t('js.feedback.message.network', 'La richiesta non è partita correttamente. Verifica la rete e riprova.'),
            type: 'warning',
            channel: 'banner',
            persistent: true,
            source: 'network-error'
        });
    });

    document.addEventListener('htmx:timeout', function () {
        window.notify({
            title: t('js.feedback.title.timeout', 'Risposta troppo lenta'),
            message: t('js.feedback.message.timeout', 'Il server ha impiegato troppo tempo a rispondere. Riprova tra poco.'),
            type: 'warning',
            channel: 'banner',
            duration: 10000,
            source: 'request-timeout'
        });
    });

    // ========================================================================
    // HTMX — HX-Trigger: notify event
    // Fired by controllers via: header('HX-Trigger: {"notify":{"message":"...","type":"success"}}')
    // ========================================================================
    document.body.addEventListener('notify', function (e) {
        window.notify(e.detail || {});
    });

    // ========================================================================
    // Bootstrap Tooltips — global init + HTMX reinit
    // ========================================================================
    var TOOLTIP_SELECTOR = '[data-bs-toggle="tooltip"], [data-app-tooltip="true"]';

    var TooltipManager = {
        init: function () {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            document.querySelectorAll(TOOLTIP_SELECTOR).forEach(function (el) {
                bootstrap.Tooltip.getOrCreateInstance(el, {
                    container: 'body',
                    boundary: 'viewport'
                });
            });
        },
        // Rimuove i div .tooltip orfani: quelli il cui trigger non esiste più nel DOM
        purgeOrphans: function () {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
            document.querySelectorAll('body > .tooltip').forEach(function (tipEl) {
                var id = tipEl.id;
                if (id && !document.querySelector('[aria-describedby="' + id + '"]')) {
                    tipEl.remove();
                }
            });
        }
    };

    document.body.addEventListener('htmx:beforeSwap', function (evt) {
        var xhr = evt.detail && evt.detail.xhr;
        if (xhr && xhr.status === 422) {
            var contentType = '';
            if (typeof xhr.getResponseHeader === 'function') {
                contentType = (xhr.getResponseHeader('Content-Type') || '').toLowerCase();
            }

            if (contentType.indexOf('text/html') !== -1) {
                evt.detail.shouldSwap = true;
                evt.detail.isError = false;
            }
        }

        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        var target = evt.detail && evt.detail.target;
        if (!target) return;
        // Nascondi e disponi solo i tooltip degli elementi nel target che verrà sostituito
        target.querySelectorAll(TOOLTIP_SELECTOR).forEach(function (el) {
            var tip = bootstrap.Tooltip.getInstance(el);
            if (tip) { tip.hide(); tip.dispose(); }
        });
    });

    document.body.addEventListener('htmx:afterSwap', function () {
        TooltipManager.purgeOrphans();
        TooltipManager.init();
        FileInputManager.init();
    });

    // ========================================================================
    // File inputs — localized trigger label + selected filename sync
    // ========================================================================
    var FileInputManager = {
        init: function () {
            document.querySelectorAll('input[type="file"][data-app-file-target]').forEach(function (input) {
                if (input.dataset.appFileBound === '1') {
                    return;
                }

                var target = document.getElementById(input.dataset.appFileTarget || '');
                if (!target) {
                    return;
                }

                var placeholder = input.dataset.appFilePlaceholder || t('js.file_input.none_selected', 'Nessun file selezionato');
                var syncValue = function () {
                    var value = placeholder;

                    if (input.files && input.files.length) {
                        value = Array.prototype.map.call(input.files, function (file) {
                            return file.name;
                        }).join(', ');
                    }

                    if ('value' in target) {
                        target.value = value;
                    } else {
                        target.textContent = value;
                    }

                    target.setAttribute('title', value);
                };

                input.addEventListener('change', syncValue);

                if (input.form && !input.form.dataset.appFileResetBound) {
                    input.form.dataset.appFileResetBound = '1';
                    input.form.addEventListener('reset', function () {
                        window.setTimeout(function () {
                            FileInputManager.init();
                            document.querySelectorAll('input[type="file"][data-app-file-target]').forEach(function (fileInput) {
                                var fileTarget = document.getElementById(fileInput.dataset.appFileTarget || '');
                                if (!fileTarget) {
                                    return;
                                }

                                var resetPlaceholder = fileInput.dataset.appFilePlaceholder || t('js.file_input.none_selected', 'Nessun file selezionato');
                                if ('value' in fileTarget) {
                                    fileTarget.value = resetPlaceholder;
                                } else {
                                    fileTarget.textContent = resetPlaceholder;
                                }
                                fileTarget.setAttribute('title', resetPlaceholder);
                            });
                        }, 0);
                    });
                }

                input.dataset.appFileBound = '1';
                syncValue();
            });
        }
    };

    // ========================================================================
    // Global search — keyboard navigation (arrows + Enter) in dropdown
    // ========================================================================
    var SearchKeyboard = {
        init: function () {
            var input = document.querySelector('#global-search-wrapper input[name="q"]');
            if (!input) return;

            input.addEventListener('keydown', function (e) {
                var container = document.getElementById('global-search-results');
                if (!container) return;

                var items = container.querySelectorAll('.dropdown-item');
                if (items.length === 0) return;

                var active = container.querySelector('.dropdown-item.active');
                var idx = active ? Array.prototype.indexOf.call(items, active) : -1;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (active) active.classList.remove('active');
                    idx = (idx + 1) % items.length;
                    items[idx].classList.add('active');
                    items[idx].scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (active) active.classList.remove('active');
                    idx = idx <= 0 ? items.length - 1 : idx - 1;
                    items[idx].classList.add('active');
                    items[idx].scrollIntoView({ block: 'nearest' });
                } else if (e.key === 'Enter' && active) {
                    e.preventDefault();
                    active.click();
                }
            });
        }
    };

    // ========================================================================
    // Footer "Torna su" button — scroll app content to top
    // ========================================================================
    var ScrollTopManager = {
        init: function () {
            document.addEventListener('click', function (e) {
                var trigger = e.target.closest('[data-scroll-top]');
                if (!trigger) return;

                e.preventDefault();

                var content = document.querySelector('.app-content');
                if (content && typeof content.scrollTo === 'function') {
                    content.scrollTo({ top: 0, behavior: 'smooth' });
                }

                var appMain = document.getElementById('app-main');
                if (appMain && appMain !== content && typeof appMain.scrollTo === 'function') {
                    appMain.scrollTo({ top: 0, behavior: 'smooth' });
                }

                window.scrollTo({ top: 0, behavior: 'smooth' });

                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    var tip = bootstrap.Tooltip.getInstance(trigger);
                    if (tip) tip.hide();
                }
            });
        }
    };

    // ========================================================================
    // Ctrl+K / Cmd+K — focus global search input
    // ========================================================================
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            var input = document.querySelector('#global-search-wrapper input[name="q"]');
            if (input) {
                input.focus();
                input.select();
            }
        }
    });

    // ========================================================================
    // Global confirm modal — replaces native confirm() with Bootstrap modal
    // Usage: appConfirm('Sei sicuro?').then(ok => { if (ok) ... })
    //        appConfirm({ title: 'Attenzione', body: 'Messaggio', confirmLabel: 'Elimina', confirmClass: 'btn-danger' })
    // ========================================================================
    window.appConfirm = function (opts) {
        if (typeof opts === 'string') {
            opts = { body: opts };
        }
        var title        = opts.title        || t('js.common.confirm', 'Conferma');
        var body         = opts.body         || t('js.confirm.default_body', 'Sei sicuro?');
        var confirmLabel = opts.confirmLabel || t('js.common.confirm', 'Conferma');
        var cancelLabel  = opts.cancelLabel  || t('js.common.cancel', 'Annulla');
        var confirmClass = opts.confirmClass || 'btn-primary';

        // Reuse or create the modal element
        var modal = document.getElementById('app-confirm-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'app-confirm-modal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.setAttribute('aria-hidden', 'true');
            modal.innerHTML =
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h5 class="modal-title" id="app-confirm-title"></h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' + t('js.common.close', 'Chiudi') + '"></button>' +
                        '</div>' +
                        '<div class="modal-body" id="app-confirm-body"></div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="app-confirm-cancel"></button>' +
                            '<button type="button" class="btn" id="app-confirm-ok"></button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(modal);
        }

        // Populate
        document.getElementById('app-confirm-title').textContent = title;
        var bodyEl = document.getElementById('app-confirm-body');
        // Support multiline: replace \n with <br>
        bodyEl.innerHTML = '';
        body.split('\n').forEach(function (line, i) {
            if (i > 0) bodyEl.appendChild(document.createElement('br'));
            bodyEl.appendChild(document.createTextNode(line));
        });
        var cancelBtn  = document.getElementById('app-confirm-cancel');
        var okBtn      = document.getElementById('app-confirm-ok');
        cancelBtn.textContent = cancelLabel;
        okBtn.textContent = confirmLabel;
        okBtn.className = 'btn ' + confirmClass;

        var bsModal = bootstrap.Modal.getOrCreateInstance(modal);

        return new Promise(function (resolve) {
            function cleanup() {
                okBtn.removeEventListener('click', onOk);
                modal.removeEventListener('hidden.bs.modal', onHidden);
            }
            var resolved = false;
            function onOk() {
                resolved = true;
                cleanup();
                bsModal.hide();
                resolve(true);
            }
            function onHidden() {
                cleanup();
                if (!resolved) resolve(false);
            }
            okBtn.addEventListener('click', onOk);
            modal.addEventListener('hidden.bs.modal', onHidden);
            bsModal.show();
        });
    };

    // ========================================================================
    // Global [data-confirm] — intercept form submit with Bootstrap modal
    // Place a data-confirm="Messaggio?" attribute on any <button type="submit">
    // Optional: data-confirm-label="Elimina"  data-confirm-class="btn-danger"
    // ========================================================================
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form._appConfirmed) {
            form._appConfirmed = false;
            return;
        }
        var trigger = e.submitter && e.submitter.matches('[data-app-confirm]')
            ? e.submitter
            : form.querySelector('[data-app-confirm]');
        if (!trigger) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        window.appConfirm({
            title: t('js.common.confirm', 'Conferma'),
            body: trigger.dataset.appConfirm,
            confirmLabel: trigger.dataset.appConfirmLabel || t('js.common.confirm', 'Conferma'),
            confirmClass: trigger.dataset.appConfirmClass || 'btn-danger'
        }).then(function (ok) {
            if (ok) {
                form._appConfirmed = true;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(trigger);
                    return;
                }

                form.submit();
            }
        });
    }, true);

    // ========================================================================
    // Global [data-app-confirm] on HTMX triggers — intercept click before
    // hx-* fires, show modal, re-emit click on confirm.
    // Use on <button>/<a> with hx-get/hx-post/hx-put/hx-delete/hx-patch.
    // ========================================================================
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-app-confirm]');
        if (!btn || btn._appConfirmed) return;
        var isHtmxTrigger = btn.hasAttribute('hx-get')
            || btn.hasAttribute('hx-post')
            || btn.hasAttribute('hx-put')
            || btn.hasAttribute('hx-delete')
            || btn.hasAttribute('hx-patch');
        if (!isHtmxTrigger) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        window.appConfirm({
            body:         btn.dataset.appConfirm,
            confirmLabel: btn.dataset.appConfirmLabel || t('js.common.confirm', 'Conferma'),
            confirmClass: btn.dataset.appConfirmClass || 'btn-danger'
        }).then(function (ok) {
            if (!ok) return;
            btn._appConfirmed = true;
            btn.click();
            setTimeout(function () { btn._appConfirmed = false; }, 100);
        });
    }, true);

    // ========================================================================
    // Debug mode indicator — reads cookie set by SettingsController
    // ========================================================================
    var DebugIndicator = {
        init: function () {
            if (document.cookie.indexOf('favilla_debug=1') === -1) return;
            var badge = document.createElement('div');
            badge.className = 'app-debug-badge';
            badge.innerHTML = '<i class="fa-solid fa-bug me-1"></i>DEBUG';
            badge.title = t('js.debug.badge_title', 'Modalità debug attiva — disattivala dalle impostazioni di sistema');
            document.body.appendChild(badge);
        }
    };

    // ========================================================================
    // Impersonation banner — reads cookie set by ImpersonationService
    // ========================================================================
    var ImpersonationBanner = {
        init: function () {
            var match = document.cookie.match(/favilla_impersonating=([^;]+)/);
            if (!match) return;

            var data;
            try {
                data = JSON.parse(decodeURIComponent(match[1]));
            } catch (e) {
                return;
            }

            // Auto-expiry: se scaduto, auto-revert
            if (data.expiresAt && (Date.now() / 1000) > data.expiresAt) {
                ImpersonationBanner.autoRevert(data.revertUrl);
                return;
            }

            var banner = document.createElement('div');
            banner.className = 'app-impersonation-banner';

            var icon = document.createElement('i');
            icon.className = 'fa-solid fa-user-secret me-2';
            banner.appendChild(icon);

            var text = document.createTextNode(t('js.impersonation.banner_prefix', 'Stai impersonando '));
            banner.appendChild(text);

            var strong = document.createElement('strong');
            strong.textContent = data.name;
            banner.appendChild(strong);

            var sep = document.createTextNode(' \u2014 ');
            banner.appendChild(sep);

            var link = document.createElement('a');
            link.href = '#';
            link.className = 'app-impersonation-revert';
            link.textContent = t('js.impersonation.back_link', 'Torna al tuo account');
            link.addEventListener('click', function (e) {
                e.preventDefault();
                ImpersonationBanner.autoRevert(data.revertUrl);
            });
            banner.appendChild(link);

            // Timer auto-expiry
            if (data.expiresAt) {
                var remaining = Math.max(0, data.expiresAt - Math.floor(Date.now() / 1000));
                var timerSpan = document.createElement('span');
                timerSpan.className = 'app-impersonation-timer ms-3';
                timerSpan.textContent = ImpersonationBanner.formatTime(remaining);
                banner.appendChild(timerSpan);

                setInterval(function () {
                    remaining = Math.max(0, data.expiresAt - Math.floor(Date.now() / 1000));
                    timerSpan.textContent = ImpersonationBanner.formatTime(remaining);
                    if (remaining <= 0) {
                        ImpersonationBanner.autoRevert(data.revertUrl);
                    }
                }, 1000);
            }

            document.body.appendChild(banner);
        },

        formatTime: function (seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        },

        autoRevert: function (url) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            form.style.display = 'none';

            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = '_token';
                input.value = meta.content;
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        }
    };

    // ========================================================================
    // Initialize on DOM ready
    // ========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        ThemeSystem.init();
        ThemeManager.init();
        TooltipManager.init();   // prima di SidebarManager: serve il tooltip del collapse-btn già attivo
        FileInputManager.init();
        SidebarManager.init();
        ColorManager.init();
        ProfilePreferences.init();
        SearchKeyboard.init();
        ScrollTopManager.init();
        DebugIndicator.init();
        ImpersonationBanner.init();
    });

})();

// ============================================================================
// PollingManager — global utility for HTMX-driven polling.
//
// Usage:
//   PollingManager.start('notifications', document.getElementById('badge'), 60000);
//   PollingManager.stop('notifications');
//
// The element must already have hx-get / hx-trigger attributes set.
// start() sets hx-trigger="every Ns" and re-processes the element;
// stop()  removes hx-trigger so HTMX stops polling.
//
// pauseOnHidden() is called automatically on load: polling pauses while the
// browser tab is hidden and resumes when the user switches back — saving
// network requests for background tabs.
// ============================================================================
window.PollingManager = (function () {
    'use strict';

    var _timers  = {};   // name → current interval (ms)
    var _paused  = false;

    function start(name, element, intervalMs) {
        if (!element) return;
        _timers[name] = intervalMs;
        if (_paused) return;
        _setTrigger(element, intervalMs);
    }

    function stop(name, element) {
        delete _timers[name];
        if (!element) return;
        element.removeAttribute('hx-trigger');
        if (typeof htmx !== 'undefined') htmx.process(element);
    }

    function pauseAll() {
        _paused = true;
        document.querySelectorAll('[data-poll-name]').forEach(function (el) {
            el.removeAttribute('hx-trigger');
            if (typeof htmx !== 'undefined') htmx.process(el);
        });
    }

    function resumeAll() {
        _paused = false;
        document.querySelectorAll('[data-poll-name]').forEach(function (el) {
            var name = el.dataset.pollName;
            if (_timers[name]) {
                _setTrigger(el, _timers[name]);
            }
        });
    }

    function _setTrigger(element, intervalMs) {
        var secs = Math.max(1, Math.round(intervalMs / 1000));
        element.setAttribute('hx-trigger', 'every ' + secs + 's');
        if (typeof htmx !== 'undefined') htmx.process(element);
    }

    // Auto-pause when tab is hidden (visibility API)
    function pauseOnHidden() {
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                pauseAll();
            } else {
                resumeAll();
            }
        });
    }

    // Initialise visibility-aware pause automatically
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pauseOnHidden);
    } else {
        pauseOnHidden();
    }

    return { start: start, stop: stop };
})();
