(function () {
    'use strict';

    // ------------------------------------------------------------------
    // Toggle invio utente / ruolo
    // ------------------------------------------------------------------
    var radios = document.querySelectorAll('input[name="send_mode"]');
    var userGroup = document.getElementById('nt-send-user-group');
    var roleGroup = document.getElementById('nt-send-role-group');

    if (radios.length && userGroup && roleGroup) {
        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (this.value === 'role') {
                    userGroup.classList.add('d-none');
                    roleGroup.classList.remove('d-none');
                } else {
                    userGroup.classList.remove('d-none');
                    roleGroup.classList.add('d-none');
                }
            });
        });
    }

    // ------------------------------------------------------------------
    // Icon Picker
    // ------------------------------------------------------------------
    var icons = {
        'Generale': [
            'fa-bell', 'fa-envelope', 'fa-comment', 'fa-comments', 'fa-message',
            'fa-circle-info', 'fa-circle-check', 'fa-triangle-exclamation', 'fa-circle-exclamation',
            'fa-flag', 'fa-bookmark', 'fa-star', 'fa-heart', 'fa-thumbs-up', 'fa-thumbs-down'
        ],
        'Utenti': [
            'fa-user', 'fa-users', 'fa-user-plus', 'fa-user-minus', 'fa-user-check',
            'fa-user-shield', 'fa-user-gear', 'fa-id-card', 'fa-address-book', 'fa-people-group'
        ],
        'Documenti': [
            'fa-file', 'fa-file-lines', 'fa-file-pdf', 'fa-file-excel', 'fa-file-image',
            'fa-folder', 'fa-folder-open', 'fa-paperclip', 'fa-clipboard', 'fa-clipboard-check'
        ],
        'Commerciale': [
            'fa-cart-shopping', 'fa-bag-shopping', 'fa-store', 'fa-receipt', 'fa-money-bill',
            'fa-credit-card', 'fa-wallet', 'fa-coins', 'fa-euro-sign', 'fa-hand-holding-dollar'
        ],
        'Azioni': [
            'fa-check', 'fa-xmark', 'fa-plus', 'fa-minus', 'fa-pen',
            'fa-trash', 'fa-download', 'fa-upload', 'fa-share', 'fa-link',
            'fa-lock', 'fa-unlock', 'fa-eye', 'fa-eye-slash', 'fa-rotate'
        ],
        'Trasporto': [
            'fa-truck', 'fa-truck-fast', 'fa-box', 'fa-boxes-stacked', 'fa-warehouse',
            'fa-dolly', 'fa-pallet', 'fa-barcode', 'fa-qrcode', 'fa-location-dot'
        ],
        'Tecnico': [
            'fa-gear', 'fa-gears', 'fa-wrench', 'fa-screwdriver-wrench', 'fa-code',
            'fa-server', 'fa-database', 'fa-shield-halved', 'fa-bug', 'fa-terminal'
        ],
        'Calendario': [
            'fa-calendar', 'fa-calendar-check', 'fa-calendar-plus', 'fa-calendar-xmark',
            'fa-clock', 'fa-hourglass', 'fa-stopwatch', 'fa-timer'
        ],
        'Grafici': [
            'fa-chart-line', 'fa-chart-bar', 'fa-chart-pie', 'fa-chart-area',
            'fa-arrow-trend-up', 'fa-arrow-trend-down', 'fa-ranking-star', 'fa-gauge-high'
        ]
    };

    var container  = document.getElementById('nt-icon-picker-container');
    var toggle     = document.getElementById('nt-icon-picker-toggle');
    var input      = document.getElementById('nt-icon-input');
    var preview    = document.getElementById('nt-icon-preview');
    var label      = document.getElementById('nt-icon-label');
    var clearBtn   = document.getElementById('nt-icon-clear');

    if (!container || !toggle || !input) return;

    var isOpen = false;

    function buildPicker() {
        var html = '<div class="nt-ip-wrapper">';
        html += '<input type="text" class="nt-ip-search" placeholder="Cerca icona..." id="nt-ip-search">';
        html += '<div id="nt-ip-results">';
        html += renderIcons('');
        html += '</div></div>';
        container.innerHTML = html;

        // Ricerca
        var searchInput = document.getElementById('nt-ip-search');
        searchInput.addEventListener('input', function () {
            var results = document.getElementById('nt-ip-results');
            results.innerHTML = renderIcons(this.value.toLowerCase().trim());
        });

        // Click su icona
        container.addEventListener('click', function (e) {
            var iconEl = e.target.closest('.nt-ip-icon');
            if (!iconEl) return;
            selectIcon(iconEl.getAttribute('data-icon'));
        });
    }

    function renderIcons(filter) {
        var html = '';
        var found = false;

        for (var cat in icons) {
            var filtered = icons[cat].filter(function (ic) {
                return !filter || ic.toLowerCase().indexOf(filter) !== -1 || cat.toLowerCase().indexOf(filter) !== -1;
            });
            if (filtered.length === 0) continue;
            found = true;

            html += '<div class="nt-ip-category">' + cat + '</div>';
            html += '<div class="nt-ip-grid">';
            filtered.forEach(function (ic) {
                var sel = (input.value === ic) ? ' nt-ip-selected' : '';
                var name = ic.replace('fa-', '').replace(/-/g, ' ');
                html += '<div class="nt-ip-icon' + sel + '" data-icon="' + ic + '" title="' + name + '">';
                html += '<i class="fa-solid ' + ic + '"></i>';
                html += '</div>';
            });
            html += '</div>';
        }

        if (!found) {
            html = '<div class="nt-ip-no-results">Nessuna icona trovata</div>';
        }
        return html;
    }

    function selectIcon(iconClass) {
        input.value = iconClass;
        if (preview) {
            preview.innerHTML = '<i class="fa-solid ' + iconClass + '"></i>';
        }
        if (label) {
            label.textContent = iconClass;
        }
        // Aggiorna selezione visiva
        container.querySelectorAll('.nt-ip-icon').forEach(function (el) {
            el.classList.toggle('nt-ip-selected', el.getAttribute('data-icon') === iconClass);
        });
        // Mostra bottone clear
        ensureClearButton();
    }

    function clearIcon() {
        input.value = '';
        if (preview) {
            preview.innerHTML = '<i class="fa-solid fa-icons text-muted"></i>';
        }
        if (label) {
            label.textContent = 'Nessuna';
        }
        container.querySelectorAll('.nt-ip-icon').forEach(function (el) {
            el.classList.remove('nt-ip-selected');
        });
    }

    function ensureClearButton() {
        if (clearBtn) return;
        clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-sm btn-outline-danger';
        clearBtn.id = 'nt-icon-clear';
        clearBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        clearBtn.addEventListener('click', clearIcon);
        toggle.parentNode.insertBefore(clearBtn, toggle.nextSibling);
    }

    toggle.addEventListener('click', function () {
        isOpen = !isOpen;
        if (isOpen) {
            if (!container.innerHTML) buildPicker();
            container.classList.remove('d-none');
            var searchInput = document.getElementById('nt-ip-search');
            if (searchInput) searchInput.focus();
        } else {
            container.classList.add('d-none');
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', clearIcon);
    }
})();
