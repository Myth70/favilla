/**
 * profile.js — Pagina profilo utente
 * Show/hide password, strength meter, confirm match indicator.
 * Wrappato in IIFE per evitare conflitti con app.js.
 */
(function () {
    'use strict';

    // ========================================================================
    // Show / Hide password
    // Ogni bottone .pf-pw-eye con data-pf-target="<input-id>" toggles il campo.
    // ========================================================================
    function initPasswordToggles() {
        document.querySelectorAll('.pf-pw-eye').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-pf-target');
                var input = document.getElementById(targetId);
                if (!input) return;

                var isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';

                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = isText
                        ? 'fa-solid fa-eye fa-sm'
                        : 'fa-solid fa-eye-slash fa-sm';
                }
            });
        });
    }

    // ========================================================================
    // Password strength meter
    // Aggiornato sull'input del campo #password (nuova password).
    // ========================================================================
    function scorePassword(val) {
        if (!val || val.length === 0) return 0;
        if (val.length < 8) return 1; // debole

        var criteria = 0;
        if (/[a-z]/.test(val)) criteria++;
        if (/[A-Z]/.test(val)) criteria++;
        if (/[0-9]/.test(val)) criteria++;
        if (/[^a-zA-Z0-9]/.test(val)) criteria++;

        if (criteria >= 3) return 3; // forte
        if (criteria >= 2) return 2; // discreta
        return 1; // debole
    }

    function initStrengthMeter() {
        var input  = document.getElementById('password');
        var wrap   = document.getElementById('pf-pw-strength');
        var fill   = document.getElementById('pf-pw-fill');
        var label  = document.getElementById('pf-pw-label');
        if (!input || !wrap || !fill || !label) return;

        var levels = {
            0: { cls: '',          text: '' },
            1: { cls: 'pf-weak',   text: t('js.profile.password_strength.weak', 'Debole') },
            2: { cls: 'pf-medium', text: t('js.profile.password_strength.fair', 'Discreta') },
            3: { cls: 'pf-strong', text: t('js.profile.password_strength.good', 'Forte') }
        };

        input.addEventListener('input', function () {
            var val   = input.value;
            var score = scorePassword(val);

            if (!val) {
                wrap.classList.add('d-none');
                fill.className     = 'pf-pw-fill';
                label.className    = 'pf-pw-label';
                label.textContent  = '';
                return;
            }

            wrap.classList.remove('d-none');

            var level = levels[score];
            fill.className    = 'pf-pw-fill ' + level.cls;
            label.className   = 'pf-pw-label ' + level.cls;
            label.textContent = level.text;
        });
    }

    // ========================================================================
    // Confirm password match indicator
    // Appare sotto #password_confirmation mentre si digita.
    // ========================================================================
    function initConfirmMatch() {
        var newPw   = document.getElementById('password');
        var confirm = document.getElementById('password_confirmation');
        var hint    = document.getElementById('pf-pw-match');
        if (!newPw || !confirm || !hint) return;

        confirm.addEventListener('input', updateMatch);
        newPw.addEventListener('input', function () {
            if (confirm.value) updateMatch();
        });

        function updateMatch() {
            var val = confirm.value;
            if (!val) {
                hint.className = 'pf-pw-match d-none';
                return;
            }
            var icon = hint.querySelector('i');
            var text = hint.querySelector('span');
            if (val === newPw.value) {
                hint.className = 'pf-pw-match pf-ok';
                if (icon) icon.className = 'fa-solid fa-circle-check';
                if (text) text.textContent = t('js.profile.passwords_match', 'Le password corrispondono');
            } else {
                hint.className = 'pf-pw-match pf-fail';
                if (icon) icon.className = 'fa-solid fa-circle-xmark';
                if (text) text.textContent = t('js.profile.passwords_mismatch', 'Le password non corrispondono');
            }
        }
    }

    // ========================================================================
    // Accent swatches — nascondi tooltip subito dopo il click
    // Nell'header il dropdown si chiude automaticamente (Bootstrap lo fa).
    // Nella profile page i swatches sono nel DOM diretto: il tooltip resta
    // visibile bloccando anche l'outline del cerchio attivo.
    // ========================================================================
    function initSwatchTooltipDismiss() {
        document.querySelectorAll('.accent-swatch').forEach(function (swatch) {
            swatch.addEventListener('click', function () {
                if (typeof bootstrap === 'undefined') return;
                var tip = bootstrap.Tooltip.getInstance(swatch);
                if (!tip) return;
                tip.hide();
                // Il mouse è ancora in hover: disabilitiamo brevemente il tooltip
                // per evitare che si riapra e copra l'outline dell'active swatch.
                tip.disable();
                setTimeout(function () { tip.enable(); }, 600);
            });
        });
    }

    // ========================================================================
    // Avatar upload — drag & drop → Cropper.js modal
    // ========================================================================
    function initAvatarUpload() {
        var zone    = document.getElementById('pf-drop-zone');
        var input   = document.getElementById('pf-avatar-input');
        var browse  = document.getElementById('pf-drop-browse');
        var config  = document.getElementById('pf-cropper-config');
        var picker  = document.getElementById('pf-avatar-filepicker');

        if (!zone || !input || !config) return;
        if (typeof AvatarCropper === 'undefined') return;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');

        function showFeedback(message, type, options) {
            if (typeof window.notify === 'function') {
                window.notify(Object.assign({
                    message: message,
                    type: type || 'info',
                    source: 'profile-avatar'
                }, options || {}));
                return;
            }

            console.warn('[profile-avatar]', message);
        }

        // Initialize AvatarCropper
        AvatarCropper.init({
            context:   config.dataset.context,
            contextId: parseInt(config.dataset.contextId, 10),
            cropUrl:   config.dataset.cropUrl,
            csrfToken: csrfMeta ? csrfMeta.content : '',
            onSuccess: function (data) {
                // Update avatar preview
                var preview = document.getElementById('pf-avatar-preview');
                if (preview) {
                    if (preview.tagName === 'IMG') {
                        preview.src = data.url;
                        preview.setAttribute('data-original-src', data.url);
                    } else {
                        var img = document.createElement('img');
                        img.src = data.url;
                        img.alt = t('js.profile.avatar_alt', 'Foto profilo');
                        img.id = 'pf-avatar-preview';
                        img.className = 'pf-upload-preview-img';
                        img.setAttribute('data-original-src', data.url);
                        preview.parentNode.replaceChild(img, preview);
                    }
                }
                // Update header avatar
                var headerAvatar = document.querySelector('.app-header-avatar-sm');
                if (headerAvatar) headerAvatar.src = data.url;
                var dropdownAvatar = document.querySelector('.app-header-avatar-md');
                if (dropdownAvatar) dropdownAvatar.src = data.url;

                // Show remove button if not present
                var removeForm = document.getElementById('pf-avatar-remove-form');
                if (!removeForm) {
                    location.reload();
                    return;
                }

                showFeedback(t('js.profile.avatar_updated', 'Foto profilo aggiornata con successo.'), 'success');
            },
            onError: function (msg) {
                showFeedback(msg, 'danger', {
                    title: t('js.profile.avatar_upload_failed_title', 'Upload avatar non riuscito'),
                    channel: 'banner',
                    persistent: true
                });
            }
        });

        // Click sulla drop zone attiva il file picker
        zone.addEventListener('click', function (e) {
            if (e.target === browse || (e.target.closest && e.target.closest('#pf-drop-browse'))) return;
            input.click();
        });

        if (browse) {
            browse.addEventListener('click', function (e) {
                e.stopPropagation();
                input.click();
            });
        }

        // Drag & drop — stati visivi
        ['dragenter', 'dragover'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                zone.classList.add('pf-drag-over');
            });
        });
        ['dragleave', 'dragend'].forEach(function (evt) {
            zone.addEventListener(evt, function (e) {
                e.preventDefault();
                zone.classList.remove('pf-drag-over');
            });
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('pf-drag-over');
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                handleFile(e.dataTransfer.files[0]);
            }
        });

        // File selezionato via input
        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) {
                handleFile(input.files[0]);
                input.value = '';
            }
        });

        // FilePicker integration — watch hidden field for changes
        var avatarUrlField = document.getElementById('pf-avatar-url');
        if (avatarUrlField) {
            var pickerBtn = document.querySelector('[data-pf-open-picker="1"]');
            if (pickerBtn && typeof FilePicker !== 'undefined') {
                pickerBtn.addEventListener('click', function () {
                    var inputId = pickerBtn.getAttribute('data-picker-input') || 'pf-avatar-url';
                    var type = pickerBtn.getAttribute('data-picker-type') || 'image';
                    FilePicker.open(inputId, null, type);
                });
            }

            // MutationObserver can't watch value changes; use polling after FilePicker modal closes
            var filePickerModal = document.getElementById('filePicker');
            if (filePickerModal) {
                filePickerModal.addEventListener('hidden.bs.modal', function () {
                    var val = avatarUrlField.value;
                    if (val) {
                        avatarUrlField.value = '';
                        var uploadsBase = config.dataset.uploadsBase || '/uploads';
                        AvatarCropper.openWithSrc(uploadsBase + '/' + val);
                    }
                });
            }
        }

        function handleFile(file) {
            var maxSize = 2 * 1024 * 1024;
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (file.size > maxSize) {
                showFeedback(t('js.profile.file_too_large', 'Il file supera il limite di 2 MB.'), 'warning', {
                    title: t('js.profile.file_too_large_title', 'File troppo grande'),
                    channel: 'banner',
                    duration: 9000
                });
                return;
            }
            if (allowedTypes.indexOf(file.type) === -1) {
                showFeedback(t('js.profile.unsupported_format', 'Formato non supportato. Usa JPG, PNG, GIF o WebP.'), 'warning', {
                    title: t('js.profile.unsupported_format_title', 'Formato non supportato'),
                    channel: 'banner',
                    duration: 9000
                });
                return;
            }

            AvatarCropper.open(file);
        }
    }

    // ========================================================================
    // Tema visivo (skin) — click su una tile cambia lo skin istantaneamente
    // (l'HTML ha gia' tutti i preset CSS caricati, scoped via attributo) e
    // persiste la scelta sul server.
    // ========================================================================
    function initSkinPicker() {
        var container = document.getElementById('pf-skin-settings');
        if (!container) return;
        var tiles = container.querySelectorAll('.pf-skin-tile');
        if (!tiles.length) return;

        tiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                var skin = tile.getAttribute('data-skin');
                if (!skin) return;

                if (!window.FavillaTheme) return;

                window.FavillaTheme.applySkin(skin);
                window.FavillaTheme.persistSkin(skin);
            });
        });
    }

    // ========================================================================
    // Reinit Bootstrap tooltips after HTMX swaps (sessions, login history)
    // ========================================================================
    function initHtmxSwapHooks() {
        document.body.addEventListener('htmx:afterSwap', function (evt) {
            var target = evt.detail.target;
            if (!target) return;
            var tooltipEls = target.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipEls.forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        });
    }

    // ========================================================================
    // Hash deep-linking per le tab del profilo.
    // Supporta sia link alla sezione sicurezza (#security) sia anchor interne
    // come #cambia-password dopo la segmentazione in tab.
    // ========================================================================
    function initTabDeepLinks() {
        if (typeof bootstrap === 'undefined') return;

        function focusPasswordField(target, panel) {
            var passwordField = null;

            if (target && (target.id === 'security' || target.id === 'cambia-password')) {
                passwordField = document.getElementById('current_password');
            }

            if (!passwordField && panel && panel.id === 'pf-ws-panel-security') {
                passwordField = panel.querySelector('input, select, textarea, button');
            }

            if (!passwordField) return;

            try {
                passwordField.focus({ preventScroll: true });
            } catch (error) {
                passwordField.focus();
            }
        }

        function openHashTarget() {
            var hash = window.location.hash;
            if (!hash || hash.length <= 1) return;

            var target = document.getElementById(decodeURIComponent(hash.slice(1)));
            if (!target) return;

            var panel = target.closest('.pf-ws-panel');
            if (!panel) return;

            var tabId = panel.getAttribute('aria-labelledby');
            var tabTrigger = tabId ? document.getElementById(tabId) : null;
            if (!tabTrigger) return;

            var scrollToTarget = function () {
                target.scrollIntoView({ block: 'start' });
                focusPasswordField(target, panel);
            };

            if (panel.classList.contains('show') && panel.classList.contains('active')) {
                scrollToTarget();
                return;
            }

            tabTrigger.addEventListener('shown.bs.tab', function () {
                scrollToTarget();
            }, { once: true });

            bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
        }

        window.addEventListener('hashchange', openHashTarget);
        openHashTarget();
    }

    // ========================================================================
    // Font picker — cambia font-family-base e heading in tempo reale e
    // persiste la scelta sul server.
    // ========================================================================
    function initFontPicker() {
        var container = document.getElementById('pf-font-settings');
        if (!container) return;
        var tiles = container.querySelectorAll('.pf-font-tile');
        if (!tiles.length) return;

        tiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                var font = tile.getAttribute('data-font');
                if (!font) return;

                if (!window.FavillaTheme) return;

                window.FavillaTheme.applyFont(font);
                window.FavillaTheme.persistFont(font);
            });
        });
    }

    // ========================================================================
    // Sidebar tile picker — i due tile "Espansa" / "Compressa" pilotano la
    // checkbox nascosta #profile-sidebar-toggle, preservando la logica
    // esistente in ProfilePreferences.init (app.js) che ascolta il 'change'.
    // ========================================================================
    function initSidebarTiles() {
        var wrap = document.getElementById('pf-sidebar-settings');
        if (!wrap) return;
        var toggle = document.getElementById('profile-sidebar-toggle');
        if (!toggle) return;
        var tiles = wrap.querySelectorAll('.pf-sidebar-tile');

        tiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                var shouldCollapse = tile.getAttribute('data-sidebar') === '1';
                if (toggle.checked === shouldCollapse) return;

                toggle.checked = shouldCollapse;
                tiles.forEach(function (t) {
                    t.classList.toggle('active', t.getAttribute('data-sidebar') === (shouldCollapse ? '1' : '0'));
                });
                // Innesca il change handler registrato in app.js (ProfilePreferences.init)
                toggle.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    // ========================================================================
    // Sidebar style picker — 3 tile (default/chiaro/accent) che cambiano
    // l'attributo data-sidebar-style su <html> (scoped CSS fa il resto) e
    // persistono la scelta. In modalita' 'accent' viene applicata sulla
    // sidebar la stessa classe .pf-pattern-* della hero utente cosi' che i
    // pseudo-elementi decorativi (estesi in app.css a .app-sidebar) si
    // attivino senza ricaricare.
    // ========================================================================
    function initSidebarStylePicker() {
        var container = document.getElementById('pf-sidebar-style-settings');
        if (!container) return;
        var tiles = container.querySelectorAll('.pf-sidebar-style-tile');
        if (!tiles.length) return;

        tiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                var style = tile.getAttribute('data-sidebar-style');
                if (!style) return;

                if (!window.FavillaTheme) return;

                window.FavillaTheme.applySidebarStyle(style);
                window.FavillaTheme.persistSidebarStyle(style);
            });
        });
    }

    // ========================================================================
    // Init
    // ========================================================================
    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggles();
        initStrengthMeter();
        initConfirmMatch();
        initSwatchTooltipDismiss();
        initAvatarUpload();
        initSkinPicker();
        initFontPicker();
        initSidebarTiles();
        initSidebarStylePicker();
        initHtmxSwapHooks();
        initTabDeepLinks();
    });

})();
