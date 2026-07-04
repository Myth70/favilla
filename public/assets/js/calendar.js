(function () {
    'use strict';

    var calendarEl = document.getElementById('cal-calendar');
    if (!calendarEl) return;

    // ── Config from data-attributes ─────────────────────────────────────
    var eventsUrl = calendarEl.dataset.eventsUrl;
    var createUrl = calendarEl.dataset.createUrl;
    var showUrl   = calendarEl.dataset.showUrl;   // contains __ID__ placeholder
    var editUrl   = calendarEl.dataset.editUrl;   // contains __ID__ placeholder
    var moveUrl   = calendarEl.dataset.moveUrl;   // contains __ID__ placeholder
    var agendaUrl = calendarEl.dataset.agendaUrl || '';
    var initialEditId = (calendarEl.dataset.initialEditId || '').trim();
    var csrfToken = calendarEl.dataset.csrf;
    var canCreate = calendarEl.dataset.canCreate === '1';
    var canEdit   = calendarEl.dataset.canEdit === '1';
    var currentUserId = parseInt(calendarEl.dataset.currentUserId || '0', 10) || 0;
    var queryInput = document.getElementById('cal-filter-query');
    var clearFilterButton = document.getElementById('cal-filter-clear');
    var scopeButtons = Array.prototype.slice.call(document.querySelectorAll('[data-cal-scope]'));

    function getActiveScope() {
        var activeButton = document.querySelector('[data-cal-scope].active');
        return activeButton ? activeButton.dataset.calScope : 'all';
    }

    function normalizeText(value) {
        return String(value || '').toLowerCase();
    }

    function matchesUiFilters(payload) {
        var query = normalizeText(queryInput ? queryInput.value.trim() : '');
        var scope = getActiveScope();
        var haystack = [payload.title, payload.description, payload.location, payload.creatorName].map(normalizeText).join(' ');

        if (query && !haystack.includes(query)) {
            return false;
        }

        if (scope === 'mine') {
            return !payload.isHoliday && payload.createdBy === currentUserId;
        }

        if (scope === 'shared') {
            return !payload.isHoliday && (payload.visibility === 'public' || payload.visibility === 'role');
        }

        if (scope === 'all-day') {
            return payload.allDay;
        }

        return true;
    }

    function applyCalendarFilters() {
        if (!calendar) return;

        calendar.getEvents().forEach(function (event) {
            var props = event.extendedProps || {};
            var visible = matchesUiFilters({
                title: event.title,
                description: props.description,
                location: props.location,
                creatorName: props.creator_name,
                visibility: props.visibility || 'personal',
                createdBy: parseInt(props.created_by || '0', 10) || 0,
                isHoliday: !!props.isHoliday,
                allDay: !!event.allDay
            });
            var desiredDisplay = visible ? 'auto' : 'none';

            if (event.display !== desiredDisplay) {
                event.setProp('display', desiredDisplay);
            }
        });
    }

    function applyAgendaFilters() {
        var agendaItems = Array.prototype.slice.call(document.querySelectorAll('[data-cal-agenda-item]'));
        var agendaEmpty = document.getElementById('cal-agenda-empty');
        var visibleCount = 0;

        agendaItems.forEach(function (item) {
            var visible = matchesUiFilters({
                title: item.dataset.title,
                description: item.dataset.description,
                location: item.dataset.location,
                creatorName: '',
                visibility: item.dataset.visibility || 'personal',
                createdBy: parseInt(item.dataset.owned || '0', 10) === 1 ? currentUserId : 0,
                isHoliday: false,
                allDay: item.dataset.allDay === '1'
            });

            item.classList.toggle('d-none', !visible);
            if (visible) {
                visibleCount++;
            }
        });

        if (agendaEmpty && agendaItems.length > 0) {
            agendaEmpty.classList.toggle('d-none', visibleCount > 0);
        }
    }

    function refreshAgendaPanel() {
        if (!agendaUrl) {
            applyAgendaFilters();
            return Promise.resolve();
        }

        return htmx.ajax('GET', agendaUrl, { target: '#cal-agenda-panel', swap: 'innerHTML' }).then(function () {
            applyAgendaFilters();
        });
    }

    function applyUiFilters() {
        applyCalendarFilters();
        applyAgendaFilters();
    }

    scopeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            scopeButtons.forEach(function (candidate) {
                candidate.classList.remove('active');
            });
            button.classList.add('active');
            applyUiFilters();
        });
    });

    if (queryInput) {
        queryInput.addEventListener('input', applyUiFilters);
    }

    if (clearFilterButton) {
        clearFilterButton.addEventListener('click', function () {
            if (queryInput) {
                queryInput.value = '';
            }
            applyUiFilters();
        });
    }

    // ── Modal management ────────────────────────────────────────────────
    var modalEl      = document.getElementById('cal-modal');
    var modalContent = document.getElementById('cal-modal-content');
    var bsModal      = new bootstrap.Modal(modalEl);

    function loadModal(url) {
        htmx.ajax('GET', url, { target: '#cal-modal-content', swap: 'innerHTML' }).then(function () {
            bsModal.show();
        });
    }

    // ── Move endpoint (drag & drop / resize) ────────────────────────────
    function moveEvent(eventId, start, end, allDay, revertFn) {
        var url = moveUrl.replace('__ID__', eventId);
        var formData = new URLSearchParams();
        formData.append('_method', 'PUT');
        formData.append('_token', csrfToken);
        formData.append('start', start);
        if (end) formData.append('end', end);
        formData.append('allDay', allDay ? '1' : '0');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken
            },
            body: formData.toString()
        })
        .then(function (res) {
            if (!res.ok) {
                revertFn();
                return res.text().then(function (body) {
                    var message = t('js.calendar.move_error', 'Errore nello spostamento');
                    if (body) {
                        try {
                            var parsed = JSON.parse(body);
                            if (parsed && parsed.error) {
                                message = parsed.error;
                            }
                        } catch (e) {
                            // Keep default message when body is not JSON
                        }
                    }
                    showToast(message, 'danger');
                });
            } else {
                showToast(t('js.calendar.moved', 'Evento spostato!'), 'success');
            }
        })
        .catch(function () {
            revertFn();
            showToast(t('js.calendar.network_error', 'Errore di rete'), 'danger');
        });
    }

    function showToast(message, type) {
        if (typeof window.notify === 'function') {
            window.notify({
                message: message,
                type: type,
                source: 'calendar'
            });
            return;
        }

        document.body.dispatchEvent(new CustomEvent('notify', {
            detail: { message: message, type: type, source: 'calendar' }
        }));
    }

    // ── Festività italiane (holiday source) ─────────────────────────────

    /**
     * Calcola la data di Pasqua con l'algoritmo di Gauss/Meeus.
     * Ritorna un oggetto Date.
     */
    function computeEaster(year) {
        var a = year % 19;
        var b = Math.floor(year / 100);
        var c = year % 100;
        var d = Math.floor(b / 4);
        var e = b % 4;
        var f = Math.floor((b + 8) / 25);
        var g = Math.floor((b - f + 1) / 3);
        var h = (19 * a + b - d - g + 15) % 30;
        var i = Math.floor(c / 4);
        var k = c % 4;
        var l = (32 + 2 * e + 2 * i - h - k) % 7;
        var m = Math.floor((a + 11 * h + 22 * l) / 451);
        var month = Math.floor((h + l - 7 * m + 114) / 31);
        var day   = ((h + l - 7 * m + 114) % 31) + 1;
        return new Date(year, month - 1, day);
    }

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }
    function dateStr(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }

    /**
     * Genera le festività italiane per un dato anno.
     * Ritorna array di oggetti FullCalendar event.
     */
    function getItalianHolidays(year) {
        var easter = computeEaster(year);
        var easterMonday = new Date(easter);
        easterMonday.setDate(easterMonday.getDate() + 1);

        var holidays = [
            { date: year + '-01-01', title: t('js.calendar.holiday.new_year', 'Capodanno') },
            { date: year + '-01-06', title: t('js.calendar.holiday.epiphany', 'Epifania') },
            { date: dateStr(easter),       title: t('js.calendar.holiday.easter', 'Pasqua') },
            { date: dateStr(easterMonday), title: t('js.calendar.holiday.easter_monday', 'Lunedì dell\'Angelo') },
            { date: year + '-04-25', title: t('js.calendar.holiday.liberation_day', 'Festa della Liberazione') },
            { date: year + '-05-01', title: t('js.calendar.holiday.labour_day', 'Festa dei Lavoratori') },
            { date: year + '-06-02', title: t('js.calendar.holiday.republic_day', 'Festa della Repubblica') },
            { date: year + '-08-15', title: t('js.calendar.holiday.ferragosto', 'Ferragosto') },
            { date: year + '-11-01', title: t('js.calendar.holiday.all_saints', 'Ognissanti') },
            { date: year + '-12-08', title: t('js.calendar.holiday.immaculate_conception', 'Immacolata Concezione') },
            { date: year + '-12-25', title: t('js.calendar.holiday.christmas', 'Natale') },
            { date: year + '-12-26', title: t('js.calendar.holiday.st_stephen', 'Santo Stefano') }
        ];

        return holidays.map(function (h) {
            return {
                id:        'holiday-' + h.date,
                title:     '🇮🇹 ' + h.title,
                start:     h.date,
                allDay:    true,
                display:   'block',
                color:     '#dc354520',
                textColor: '#dc3545',
                editable:  false,
                classNames: ['cal-holiday'],
                extendedProps: { isHoliday: true }
            };
        });
    }

    /**
     * Event source per le festività: genera per l'anno corrente ±1
     * in base al range richiesto da FullCalendar.
     */
    function holidayEventSource(fetchInfo, successCallback) {
        var startYear = fetchInfo.start.getFullYear();
        var endYear   = fetchInfo.end.getFullYear();
        var events = [];
        for (var y = startYear; y <= endYear; y++) {
            events = events.concat(getItalianHolidays(y));
        }
        successCallback(events);
    }

    // ── FullCalendar init ───────────────────────────────────────────────
    var isMobile = window.innerWidth < 768;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'it',
        initialView: isMobile ? 'listMonth' : 'dayGridMonth',
        height: 'auto',

        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  isMobile
                ? 'listMonth,dayGridMonth'
                : 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },

        // Fix icone prev/next — disabilita icone FC (non caricate) e usa testo
        buttonIcons: false,

        buttonText: {
            prev:     ' ',
            next:     ' ',
            today:    ' ',
            month:    ' ',
            week:     ' ',
            day:      ' ',
            list:     ' '
        },

        // ── Event Sources ──────────────────────────────────────────────
        eventSources: [
            // 1) Eventi utente dal backend
            {
                url:    eventsUrl,
                method: 'GET'
            },
            // 2) Festività italiane (generate client-side)
            {
                events: holidayEventSource
            }
        ],

        // ── Interazione ─────────────────────────────────────────────────
        navLinks:   true,
        selectable: canCreate,

        // [FEATURE 1-2] Drag & Drop + Resize
        editable:         canEdit,
        eventDurationEditable: canEdit,
        eventStartEditable:    canEdit,
        dragRevertDuration: 300,

        // [FEATURE 3] Select range — click+drag per creare eventi multi-giorno/orario
        select: function (info) {
            if (!canCreate) return;
            var params = '?date=' + info.startStr
                + '&endDate=' + info.endStr
                + '&allDay=' + (info.allDay ? '1' : '0');
            loadModal(createUrl + params);
            calendar.unselect();
        },

        // Click su data vuota → apri modal crea con data precompilata
        dateClick: function (info) {
            if (!canCreate) return;
            var url = createUrl + '?date=' + info.dateStr + '&allDay=' + (info.allDay ? '1' : '0');
            loadModal(url);
        },

        // Click su evento → apri modal dettaglio (ma NON per festività)
        eventClick: function (info) {
            var props = info.event.extendedProps || {};
            if (props.isHoliday) {
                info.jsEvent.preventDefault();
                return; // non aprire modal per festività
            }
            info.jsEvent.preventDefault();
            var url = showUrl.replace('__ID__', info.event.id);
            loadModal(url);
        },

        // [FEATURE 1] Drag & Drop — spostamento evento
        eventDrop: function (info) {
            var props = info.event.extendedProps || {};
            if (props.isHoliday) { info.revert(); return; }
            moveEvent(
                info.event.id,
                info.event.startStr,
                info.event.endStr || null,
                info.event.allDay,
                info.revert
            );
        },

        // [FEATURE 2] Resize — cambio durata evento
        eventResize: function (info) {
            var props = info.event.extendedProps || {};
            if (props.isHoliday) { info.revert(); return; }
            moveEvent(
                info.event.id,
                info.event.startStr,
                info.event.endStr || null,
                info.event.allDay,
                info.revert
            );
        },

        // [FEATURE 4] Now Indicator — linea rossa "ora corrente"
        nowIndicator: true,

        // [FEATURE 5] Business Hours — evidenzia orari lavorativi
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5],  // lun-ven
            startTime:  '08:00',
            endTime:    '18:00'
        },

        // [FEATURE 6] Day Max Events — "+N altri" con popover
        dayMaxEvents: 3,

        // [FEATURE 7] Sticky Headers — intestazioni fisse durante scroll
        stickyHeaderDates: true,

        // [FEATURE 8] Icone visibilità nel rendering eventi
        eventContent: function (arg) {
            var props = arg.event.extendedProps || {};

            // Festività: rendering semplice con bandiera
            if (props.isHoliday) {
                var hDiv = document.createElement('div');
                hDiv.classList.add('cal-holiday-content');
                hDiv.textContent = arg.event.title;
                return { domNodes: [hDiv] };
            }

            var icon  = props.visibility === 'role' ? '👥 ' : '';
            var loc   = props.location ? ' 📍' : '';

            var container = document.createElement('div');
            container.classList.add('cal-event-content');

            // Dot per time grid (non all-day)
            if (arg.timeText) {
                var time = document.createElement('span');
                time.classList.add('fc-event-time');
                time.textContent = arg.timeText;
                container.appendChild(time);
            }

            var title = document.createElement('span');
            title.classList.add('fc-event-title');
            title.textContent = icon + arg.event.title + loc;
            container.appendChild(title);

            return { domNodes: [container] };
        },

        // Tooltip on hover
        eventDidMount: function (info) {
            var props = info.event.extendedProps || {};
            var lines;

            function escHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            if (props.isHoliday) {
                lines = [info.event.title + t('js.calendar.national_holiday_suffix', ' — Festività nazionale')];
            } else {
                lines = [info.event.title];
                if (props.location)    lines.push('📍 ' + props.location);
                if (props.description) lines.push(props.description.substring(0, 80));
                if (props.visibility === 'role') lines.push('👥 ' + t('js.calendar.shared_by_role', 'Condiviso per ruolo'));
                if (props.creator_name) lines.push('👤 ' + props.creator_name);
            }

            info.el.setAttribute('data-bs-toggle', 'tooltip');
            info.el.setAttribute('data-bs-placement', 'top');
            var tooltip = new bootstrap.Tooltip(info.el, {
                title: lines.map(escHtml).join('<br>'),
                html: true
            });
            info.el._bsTooltip = tooltip;
        },

        // Cleanup tooltip on unmount to avoid "ghost" tooltips
        eventWillUnmount: function (info) {
            if (info.el._bsTooltip) {
                info.el._bsTooltip.dispose();
                info.el._bsTooltip = null;
            }
        },

        // Loading indicator
        loading: function (isLoading) {
            calendarEl.style.opacity = isLoading ? '0.6' : '1';
        },

        eventsSet: function () {
            applyUiFilters();
        }
    });

    calendar.render();

    // ── Toolbar button icons + tooltips ─────────────────────────────────
    function decorateToolbarButtons() {
        var toolbarMap = {
            'fc-prev-button':         { icon: 'fa-solid fa-chevron-left',  tip: t('js.calendar.toolbar.prev', 'Precedente') },
            'fc-next-button':         { icon: 'fa-solid fa-chevron-right', tip: t('js.calendar.toolbar.next', 'Successivo') },
            'fc-today-button':        { icon: 'fa-solid fa-circle-dot',    tip: t('js.calendar.toolbar.today', 'Vai ad oggi') },
            'fc-dayGridMonth-button': { icon: 'fa-solid fa-calendar',      tip: t('js.calendar.toolbar.month', 'Vista mensile') },
            'fc-timeGridWeek-button': { icon: 'fa-solid fa-calendar-week', tip: t('js.calendar.toolbar.week', 'Vista settimanale') },
            'fc-timeGridDay-button':  { icon: 'fa-solid fa-calendar-day',  tip: t('js.calendar.toolbar.day', 'Vista giornaliera') },
            'fc-listMonth-button':    { icon: 'fa-solid fa-list',          tip: t('js.calendar.toolbar.list', 'Vista lista') }
        };

        Object.keys(toolbarMap).forEach(function (cls) {
            var btn = calendarEl.querySelector('.' + cls);
            if (!btn) return;

            var cfg = toolbarMap[cls];

            // Inietta icona FA
            btn.innerHTML = '<i class="' + cfg.icon + '"></i>';

            // Forza sempre label coerente per tooltip/accessibilita
            btn.setAttribute('aria-label', cfg.tip);
            btn.setAttribute('title', cfg.tip);
            btn.setAttribute('data-bs-toggle', 'tooltip');
            btn.setAttribute('data-bs-placement', 'bottom');
            btn.setAttribute('data-bs-title', cfg.tip);
            btn.setAttribute('data-bs-trigger', 'hover');

            var existingTooltip = bootstrap.Tooltip.getInstance(btn);
            if (existingTooltip) {
                existingTooltip.dispose();
            }
            bootstrap.Tooltip.getOrCreateInstance(btn);
        });
    }

    setTimeout(decorateToolbarButtons, 0);

    // Deep-link edit support: /calendar?edit=123
    if (canEdit && initialEditId && /^\d+$/.test(initialEditId)) {
        loadModal(editUrl.replace('__ID__', initialEditId));
    }

    // ── "Nuovo Evento" button (header) ──────────────────────────────────
    var btnNew = document.getElementById('cal-btn-new');
    if (btnNew) {
        btnNew.addEventListener('click', function () {
            loadModal(createUrl);
        });
    }

    // ── HX-Trigger listeners ────────────────────────────────────────────
    document.body.addEventListener('closeModal', function () {
        bsModal.hide();
    });

    document.body.addEventListener('refetchEvents', function () {
        calendar.refetchEvents();
        refreshAgendaPanel();
    });

    // Re-init modal form after HTMX swap (runs in trusted page context — no CSP nonce issues)
    modalEl.addEventListener('htmx:afterSwap', function () {

        // ── Tooltip ─────────────────────────────────────────────────────────
        modalEl.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });

        // ── Elemento DOM del form ────────────────────────────────────────────
        var allDayChk       = document.getElementById('cal-all-day');
        var timeInputs      = document.querySelectorAll('.cal-time-input');
        var durationHelper  = document.getElementById('cal-duration-helper');
        var startInput      = document.getElementById('cal-start');
        var endInput        = document.getElementById('cal-end');
        var visibilityEl    = document.getElementById('cal-visibility');
        var roleGroup       = document.getElementById('cal-role-group');
        var colorHidden     = document.getElementById('cal-color');
        var colorCustom     = document.getElementById('cal-color-custom');
        var colorRadios     = document.querySelectorAll('.cal-color-radio');
        var swatches        = document.querySelectorAll('.cal-swatch-dot');
        var recurFreq       = document.getElementById('cal-recur-freq');
        var recurOptions    = document.getElementById('cal-recur-options');
        var recurInterval   = document.getElementById('cal-recur-interval');
        var recurUnitLabel  = document.getElementById('cal-recur-unit-label');
        var recurCountGroup = document.getElementById('cal-recur-count-group');
        var recurCount      = document.getElementById('cal-recur-count');
        var recurDateGroup  = document.getElementById('cal-recur-date-group');
        var recurEndPicker  = document.getElementById('cal-recurrence-end-picker');
        var ruleHidden      = document.getElementById('cal-recurrence-rule');
        var endHidden       = document.getElementById('cal-recurrence-end');
        var unitLabels      = {
            daily: t('js.calendar.unit.daily', 'giorno/i'),
            weekly: t('js.calendar.unit.weekly', 'settimana/e'),
            monthly: t('js.calendar.unit.monthly', 'mese/i')
        };

        // ── All-day toggle ───────────────────────────────────────────────────
        var endGroup = document.getElementById('cal-end-group');

        function syncAllDayMode() {
            var isAllDay = !!(allDayChk && allDayChk.checked);

            // Cambia tipo del solo campo inizio
            if (startInput) {
                var prev = startInput.value;
                if (isAllDay) {
                    startInput.type = 'date';
                    if (prev && prev.length > 10) startInput.value = prev.substring(0, 10);
                } else {
                    startInput.type = 'datetime-local';
                    if (prev && prev.length === 10) startInput.value = prev + 'T09:00';
                }
            }

            // Mostra/nascondi la data di fine (non ha senso per giornata intera)
            if (endGroup) {
                endGroup.classList.toggle('d-none', isAllDay);
                if (isAllDay && endInput) endInput.value = '';
            }

            // Mostra/nascondi i pulsanti durata rapida
            if (durationHelper) {
                durationHelper.classList.toggle('d-none', isAllDay);
            }
        }
        if (allDayChk) {
            allDayChk.addEventListener('change', syncAllDayMode);
            syncAllDayMode();
        }

        // ── Visibilità → mostra/nascondi ruolo ──────────────────────────────
        function syncVisibility() {
            if (!visibilityEl || !roleGroup) return;
            roleGroup.classList.toggle('d-none', visibilityEl.value !== 'role');
        }
        if (visibilityEl) {
            visibilityEl.addEventListener('change', syncVisibility);
            syncVisibility();
        }

        // ── Colore ───────────────────────────────────────────────────────────
        function clearSwatchBorders() {
            swatches.forEach(function (dot) {
                dot.style.setProperty('--cal-swatch-border', 'transparent');
            });
        }
        colorRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                clearSwatchBorders();
                var dot = this.closest('label').querySelector('.cal-swatch-dot');
                if (dot) dot.style.setProperty('--cal-swatch-border', 'var(--text-primary)');
                if (colorHidden) colorHidden.value = this.value;
                if (colorCustom) colorCustom.value = this.value;
            });
        });
        if (colorCustom) {
            colorCustom.addEventListener('input', function () {
                colorRadios.forEach(function (r) { r.checked = false; });
                clearSwatchBorders();
                if (colorHidden) colorHidden.value = colorCustom.value;
            });
        }

        // ── Durata rapida ────────────────────────────────────────────────────
        modalEl.querySelectorAll('.cal-dur-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!startInput || !endInput) return;
                if (allDayChk && allDayChk.checked) return;
                if (!startInput.value) return;
                var mins = parseInt(btn.getAttribute('data-minutes') || '0', 10);
                if (!mins) return;
                var s = new Date(startInput.value);
                if (isNaN(s.getTime())) return;
                var e = new Date(s.getTime() + mins * 60000);
                var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
                endInput.value = e.getFullYear() + '-' + pad(e.getMonth() + 1) + '-' + pad(e.getDate())
                               + 'T' + pad(e.getHours()) + ':' + pad(e.getMinutes());
            });
        });

        // ── Recurrence ───────────────────────────────────────────────────────
        function buildRrule() {
            if (!recurFreq || !ruleHidden || !endHidden) return;
            var freq = recurFreq.value;
            if (freq === 'none') {
                ruleHidden.value = '';
                endHidden.value  = '';
                return;
            }
            var freqMap  = { daily: 'DAILY', weekly: 'WEEKLY', monthly: 'MONTHLY' };
            var interval = Math.max(1, parseInt((recurInterval && recurInterval.value) || '1', 10));
            var parts    = ['FREQ=' + freqMap[freq], 'INTERVAL=' + interval];
            var endTypeEl = document.querySelector('[name="recur_end_type"]:checked');
            var endType   = endTypeEl ? endTypeEl.value : 'never';
            if (endType === 'count' && recurCount) {
                var count = parseInt(recurCount.value || '', 10);
                if (!isNaN(count) && count > 0) {
                    parts.push('COUNT=' + count);
                }
            }
            ruleHidden.value = parts.join(';');
            if (endType === 'date' && recurEndPicker && recurEndPicker.value) {
                endHidden.value = recurEndPicker.value;
            } else {
                endHidden.value = '';
            }
        }

        function syncRecurFreq() {
            if (!recurFreq || !recurOptions) return;
            recurOptions.classList.toggle('d-none', recurFreq.value === 'none');
            if (recurUnitLabel) {
                recurUnitLabel.textContent = unitLabels[recurFreq.value] || t('js.calendar.unit.daily', 'giorno/i');
            }
            buildRrule();
        }

        function syncEndType() {
            var endTypeEl = document.querySelector('[name="recur_end_type"]:checked');
            var endType   = endTypeEl ? endTypeEl.value : 'never';
            if (recurCountGroup) recurCountGroup.classList.toggle('d-none', endType !== 'count');
            if (recurDateGroup)  recurDateGroup.classList.toggle('d-none',  endType !== 'date');
            buildRrule();
        }

        if (recurFreq) {
            recurFreq.addEventListener('change', syncRecurFreq);
            syncRecurFreq();
        }
        if (recurInterval) {
            recurInterval.addEventListener('input', buildRrule);
            recurInterval.addEventListener('change', buildRrule);
        }
        document.querySelectorAll('[name="recur_end_type"]').forEach(function (radio) {
            radio.addEventListener('change', syncEndType);
        });
        syncEndType();
        if (recurCount) {
            recurCount.addEventListener('input', buildRrule);
            recurCount.addEventListener('change', buildRrule);
        }
        if (recurEndPicker) {
            recurEndPicker.addEventListener('change', buildRrule);
        }
        buildRrule();

    });

    // After modal hides, clear content to avoid stale forms
    modalEl.addEventListener('hidden.bs.modal', function () {
        modalContent.innerHTML = '';
    });

})();
