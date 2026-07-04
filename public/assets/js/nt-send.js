(function () {
    'use strict';

    // Types metadata from JSON
    var typesMeta = {};
    var typesEl = document.getElementById('nt-send-types-meta');
    if (typesEl && typesEl.textContent) {
        try { typesMeta = JSON.parse(typesEl.textContent); } catch (e) { /* ignore */ }
    }

    // DOM references
    var radios    = document.querySelectorAll('input[name="send_mode"]');
    var userGroup = document.getElementById('nt-send-user-group');
    var roleGroup = document.getElementById('nt-send-role-group');
    var titleInput = document.getElementById('title');
    var bodyInput  = document.getElementById('body');
    var linkInput  = document.getElementById('link');
    var iconInput  = document.getElementById('icon');
    var typeRadios = document.querySelectorAll('.nt-send-type-radio');

    var previewIcon      = document.getElementById('nt-send-preview-icon');
    var previewIndicator = document.getElementById('nt-send-preview-indicator');
    var previewTitle     = document.getElementById('nt-send-preview-title');
    var previewBody      = document.getElementById('nt-send-preview-body');
    var previewLink      = document.getElementById('nt-send-preview-link');
    var previewCard      = document.getElementById('nt-send-preview-card');
    var iconPreviewBox   = document.getElementById('nt-send-icon-preview');

    // ---------------------------------------------------------------
    // Toggle user / role
    // ---------------------------------------------------------------
    if (radios.length && userGroup && roleGroup) {
        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                userGroup.classList.toggle('d-none', this.value === 'role');
                roleGroup.classList.toggle('d-none', this.value !== 'role');
            });
        });
    }

    // ---------------------------------------------------------------
    // Type pills toggle
    // ---------------------------------------------------------------
    typeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.nt-send-type-label').forEach(function (lbl) {
                lbl.classList.toggle('is-active', lbl.querySelector('input:checked') !== null);
            });
            updatePreview();
        });
    });

    // ---------------------------------------------------------------
    // Live preview
    // ---------------------------------------------------------------
    function getSelectedType() {
        var checked = document.querySelector('.nt-send-type-radio:checked');
        return checked ? checked.value : 'info';
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function updatePreview() {
        var type  = getSelectedType();
        var meta  = typesMeta[type] || { color: '#2563EB', icon: 'fa-solid fa-circle-info' };
        var title = (titleInput ? titleInput.value : '').trim() || 'Oggetto della notifica';
        var body  = (bodyInput ? bodyInput.value : '').trim() || 'Testo descrittivo';
        var link  = (linkInput ? linkInput.value : '').trim();
        var icon  = (iconInput ? iconInput.value : '').trim();

        // Determine which icon to show
        var displayIcon = icon || meta.icon;
        var displayColor = meta.color;

        // Update indicator bar
        if (previewIndicator) {
            previewIndicator.className = 'nt-indicator nt-' + type;
            previewIndicator.style.backgroundColor = '';
        }
        // Update type icon
        if (previewIcon) {
            previewIcon.className = 'nt-type-icon nt-' + type;
            previewIcon.style.color = '';
            previewIcon.innerHTML = '<i class="' + escapeHtml(displayIcon) + '"></i>';
        }
        if (previewTitle) previewTitle.textContent = title;
        if (previewBody) previewBody.innerHTML = escapeHtml(body).replace(/\n/g, '<br>');
        if (previewLink) {
            previewLink.classList.toggle('d-none', link === '');
        }

        // Update icon input preview
        if (iconPreviewBox) {
            iconPreviewBox.innerHTML = '<i class="' + escapeHtml(icon || 'fa-solid fa-bell') + '"></i>';
        }
    }

    // Bind events
    if (titleInput) titleInput.addEventListener('input', updatePreview);
    if (bodyInput) bodyInput.addEventListener('input', updatePreview);
    if (linkInput) linkInput.addEventListener('input', updatePreview);
    if (iconInput) iconInput.addEventListener('input', updatePreview);

    // Initial render
    updatePreview();
})();
