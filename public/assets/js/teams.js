(function () {
    'use strict';

    var Teams = {
        heartbeatInterval: null,
        lastMessageTimestamp: '',
        lastStateTimestamp: '',
        lastConvStateTimestamp: '',
        activeConversationId: null,
        typingTimeout: null,
        wasAtBottom: true,
        _sending: false
    };

    Teams.hasPendingAttachment = function () {
        var attachment = document.getElementById('tm-msg-attachment');
        return !!(attachment && attachment.files && attachment.files.length > 0);
    };

    // URL oggetto delle thumbnail correnti — revocati al re-render successivo
    // per evitare leak in memoria del browser.
    Teams._attachmentPreviewUrls = [];

    Teams._revokeAttachmentPreviews = function () {
        Teams._attachmentPreviewUrls.forEach(function (u) {
            try { URL.revokeObjectURL(u); } catch (e) { /* ignore */ }
        });
        Teams._attachmentPreviewUrls = [];
    };

    // Renderizza gli allegati selezionati come chip rimuovibili sopra la
    // textarea (stile Telegram). Le immagini ottengono una thumbnail inline
    // (preview reale via URL.createObjectURL), gli altri file un'icona
    // tipografica. NON sovrascrive più la textarea: l'utente può comporre
    // una caption insieme agli allegati.
    Teams.renderAttachmentChips = function () {
        var attachment = document.getElementById('tm-msg-attachment');
        var container  = document.getElementById('tm-attachment-chips');
        var input      = document.getElementById('tm-msg-input');
        if (!container) return;

        // Reset eventuale stato readonly lasciato dalla vecchia implementazione
        if (input) {
            input.readOnly = false;
            input.classList.remove('text-muted');
        }

        // Revoca eventuali thumbnail precedenti
        Teams._revokeAttachmentPreviews();

        if (!attachment || !attachment.files || attachment.files.length === 0) {
            container.innerHTML = '';
            container.classList.add('d-none');
            return;
        }

        var escapeMap = { '<':'&lt;', '>':'&gt;', '&':'&amp;', '"':'&quot;', "'":'&#39;' };
        var files = Array.prototype.slice.call(attachment.files);
        container.classList.remove('d-none');
        container.innerHTML = files.map(function (f, i) {
            var name = f.name || 'file';
            var safe = name.replace(/[<>&"']/g, function (c) { return escapeMap[c]; });
            var isImage = (f.type && f.type.indexOf('image/') === 0)
                       || /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(name);

            if (isImage) {
                var url = URL.createObjectURL(f);
                Teams._attachmentPreviewUrls.push(url);
                return '<span class="tm-chip tm-chip-img" title="' + safe + '">'
                     + '<img src="' + url + '" alt="" class="tm-chip-thumb">'
                     + '<button type="button" class="tm-chip-x tm-chip-x-overlay" data-remove="' + i + '" aria-label="Rimuovi ' + safe + '">&times;</button>'
                     + '</span>';
            }

            var icon = /\.pdf$/i.test(name) ? 'fa-file-pdf'
                     : /\.(zip|rar|7z|tar|gz)$/i.test(name) ? 'fa-file-zipper'
                     : /\.(docx?|odt)$/i.test(name) ? 'fa-file-word'
                     : /\.(xlsx?|ods|csv)$/i.test(name) ? 'fa-file-excel'
                     : /\.(pptx?|odp)$/i.test(name) ? 'fa-file-powerpoint'
                     : /\.(mp3|wav|ogg|m4a|flac)$/i.test(name) ? 'fa-file-audio'
                     : /\.(mp4|mov|avi|mkv|webm)$/i.test(name) ? 'fa-file-video'
                     : 'fa-file';
            return '<span class="tm-chip">'
                 + '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>'
                 + '<span class="tm-chip-name" title="' + safe + '">' + safe + '</span>'
                 + '<button type="button" class="tm-chip-x" data-remove="' + i + '" aria-label="Rimuovi ' + safe + '">&times;</button>'
                 + '</span>';
        }).join('');

        // I chip cambiano l'altezza dell'input area: riposiziona la pill se visibile.
        if (typeof Teams.repositionNewMessagesPill === 'function') {
            Teams.repositionNewMessagesPill();
        }
    };

    // Rimuove un singolo file dal FileList ricostruendolo via DataTransfer.
    Teams.removeAttachment = function (idx) {
        var attachment = document.getElementById('tm-msg-attachment');
        if (!attachment || !attachment.files) return;
        var dt = new DataTransfer();
        Array.prototype.forEach.call(attachment.files, function (f, i) {
            if (i !== idx) dt.items.add(f);
        });
        attachment.files = dt.files;
        Teams.renderAttachmentChips();
        Teams.updateSendButtonState();
    };

    // Backward-compatible alias: i call site esistenti continuano a funzionare
    // ma ora producono chip invece di sovrascrivere la textarea.
    Teams.updateAttachmentLabel = Teams.renderAttachmentChips;

    // Counter caratteri sotto la textarea: passa a giallo a 4500 e a rosso a 4900.
    Teams.updateCharCounter = function () {
        var input   = document.getElementById('tm-msg-input');
        var counter = document.getElementById('tm-char-counter');
        if (!input || !counter) return;
        var len = input.value.length;
        counter.textContent = len + '/5000';
        counter.classList.toggle('tm-char-warn',   len >= 4500 && len < 4900);
        counter.classList.toggle('tm-char-danger', len >= 4900);
    };

    Teams.updateSendButtonState = function () {
        var input = document.getElementById('tm-msg-input');
        var btn = document.getElementById('tm-send-btn');
        if (!btn) return;

        var hasText = !!(input && input.value.trim() !== '');
        var hasAttachment = Teams.hasPendingAttachment();
        btn.disabled = !(hasText || hasAttachment);
    };

    // ═══════════════════════════════════════════════════════════════
    // 1. HEARTBEAT — Presence tracking (every 10s)
    // ═══════════════════════════════════════════════════════════════
    Teams.startHeartbeat = function () {
        var container = document.querySelector('.tm-container');
        if (!container) return;
        var url = container.dataset.heartbeatUrl;

        function beat() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) return;
            var body = 'active_conversation_id=' + (Teams.activeConversationId || '');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-CSRF-Token', meta.content);
            xhr.send(body);
        }

        beat();
        Teams.heartbeatInterval = setInterval(beat, 10000);
    };

    Teams.stopHeartbeat = function () {
        if (Teams.heartbeatInterval) {
            clearInterval(Teams.heartbeatInterval);
            Teams.heartbeatInterval = null;
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 2. MESSAGE INPUT — Enter to send, Shift+Enter for newline
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('keydown', function (e) {
        if (e.target.id !== 'tm-msg-input') return;

        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (Teams._sending) return;
            var form = document.getElementById('tm-msg-form');
            if (form && (e.target.value.trim() !== '' || Teams.hasPendingAttachment())) {
                Teams._sending = true;
                htmx.trigger(form, 'submit');
            }
        }

        // Typing indicator (debounced)
        Teams.reportTyping();
    });

    // Enable/disable send button + auto-resize + char counter
    document.addEventListener('input', function (e) {
        if (e.target.id !== 'tm-msg-input') return;
        Teams.updateSendButtonState();
        Teams.updateCharCounter();
        e.target.style.height = 'auto';
        e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
        // Auto-resize cambia l'altezza dell'input area: riposiziona la pill se visibile
        Teams.repositionNewMessagesPill && Teams.repositionNewMessagesPill();
    });

    document.addEventListener('change', function (e) {
        if (e.target.id !== 'tm-msg-attachment') return;
        Teams.updateAttachmentLabel();
        Teams.updateSendButtonState();
    });

    // ═══════════════════════════════════════════════════════════════
    // 3. AFTER MESSAGE SENT — Document-level listener (more reliable
    //    than hx-on:: attribute on dynamically swapped elements)
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('htmx:afterRequest', function (e) {
        var form = e.detail.elt;
        if (!form || form.id !== 'tm-msg-form') return;

        // Reset sending flag (sia su successo che errore)
        Teams._sending = false;

        if (!e.detail.successful) return;

        var input = document.getElementById('tm-msg-input');
        if (input) {
            input.value = '';
            input.style.height = 'auto';
            input.readOnly = false;
            input.classList.remove('text-muted');
        }
        var attachment = document.getElementById('tm-msg-attachment');
        if (attachment) {
            attachment.value = '';
        }
        Teams.renderAttachmentChips();
        Teams.updateCharCounter();
        Teams.updateSendButtonState();
        Teams.clearReply();

        // Cancella typing indicator
        if (Teams.typingTimeout) {
            clearTimeout(Teams.typingTimeout);
            Teams.typingTimeout = null;
        }

        setTimeout(function () {
            Teams.scrollToBottom();
            Teams.updateLastTimestamp();
        }, 50);
    });

    // Expose for callbacks
    window.Teams = Teams;

    // ═══════════════════════════════════════════════════════════════
    // 4. SCROLL MANAGEMENT
    // ═══════════════════════════════════════════════════════════════
    Teams.scrollToBottom = function () {
        var container = document.getElementById('tm-messages');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    };

    // Track if user is scrolled to bottom
    document.addEventListener('scroll', function (e) {
        if (e.target.id !== 'tm-messages') return;
        var el = e.target;
        Teams.wasAtBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < 80;
        if (Teams.wasAtBottom) {
            Teams.hideNewMessagesPill();
        }
    }, true);

    // ─ New messages pill (when user is scrolled up and new messages arrive) ─
    Teams._newMessagesCount = 0;

    // Posiziona la pill appena sopra il bordo superiore dell'input area.
    // L'altezza dell'input area è dinamica (chips allegati, char counter,
    // textarea auto-resize, reply banner) quindi non possiamo usare un
    // bottom fisso in CSS.
    Teams.repositionNewMessagesPill = function () {
        var pill = document.getElementById('tm-new-messages-pill');
        if (!pill) return;
        var chat = document.getElementById('tm-chat');
        var inputArea = chat ? chat.querySelector('.tm-input-area') : null;
        var h = inputArea ? inputArea.offsetHeight : 64;
        pill.style.bottom = (h + 12) + 'px';
    };

    Teams.showNewMessagesPill = function (additional) {
        var messagesEl = document.getElementById('tm-messages');
        if (!messagesEl) return;
        Teams._newMessagesCount += additional;
        var pill = document.getElementById('tm-new-messages-pill');
        if (!pill) {
            pill = document.createElement('button');
            pill.id = 'tm-new-messages-pill';
            pill.className = 'tm-new-messages-pill';
            pill.type = 'button';
            pill.addEventListener('click', function () {
                Teams.scrollToBottom();
                Teams.hideNewMessagesPill();
            });
            messagesEl.parentNode.appendChild(pill);
        }
        var count = Teams._newMessagesCount;
        var label = count === 1
            ? t('js.teams.new_message_one', '1 nuovo messaggio')
            : t('js.teams.new_messages_many', '{count} nuovi messaggi').replace('{count}', count);
        pill.innerHTML = '<i class="fa-solid fa-arrow-down me-1"></i>' + label;
        pill.classList.add('tm-new-messages-pill-visible');
        Teams.repositionNewMessagesPill();
    };

    Teams.hideNewMessagesPill = function () {
        var pill = document.getElementById('tm-new-messages-pill');
        if (pill) {
            pill.classList.remove('tm-new-messages-pill-visible');
        }
        Teams._newMessagesCount = 0;
    };

    // ═══════════════════════════════════════════════════════════════
    // 5. POLLING — Dynamic "since" parameter
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('htmx:configRequest', function (e) {
        var el = e.detail.elt;

        // Inject "since" / "since_state" / "conv_state_since" timestamps into
        // the unified /state poll (every 3s).
        if (el && el.id === 'tm-new-messages-target') {
            e.detail.parameters['since']            = Teams.lastMessageTimestamp   || '';
            e.detail.parameters['since_state']      = Teams.lastStateTimestamp     || '';
            e.detail.parameters['conv_state_since'] = Teams.lastConvStateTimestamp || '';
        }

        // Inject active conversation ID and show_hidden into conversation list poll
        if (el && el.id === 'tm-conv-list') {
            e.detail.parameters['active'] = Teams.activeConversationId || '';
            if (Teams._showHidden) {
                e.detail.parameters['show_hidden'] = '1';
            }
        }
    });

    // Init Teams.lastStateTimestamp from the sentinel rendered server-side
    // (in chat_panel.php). Aggiornato OOB dal polling stesso ad ogni risposta.
    Teams.refreshStateTimestamp = function () {
        var sentinel = document.getElementById('tm-poll-state-sentinel');
        if (sentinel && sentinel.dataset && sentinel.dataset.stateTs) {
            Teams.lastStateTimestamp = sentinel.dataset.stateTs;
        }
    };
    Teams.refreshStateTimestamp();

    // Init Teams.lastConvStateTimestamp dal sentinel conv-state (server-rendered
    // in chat_panel.php). Usato dal polling /state per il dirty-check della
    // lista conv: se il server vede update dopo questo timestamp, emette
    // HX-Trigger: teamsConvRefresh che fa rifetchare /teams/conversations.
    Teams.refreshConvStateTimestamp = function () {
        var sentinel = document.getElementById('tm-conv-state-sentinel');
        if (sentinel && sentinel.dataset && sentinel.dataset.stateTs) {
            Teams.lastConvStateTimestamp = sentinel.dataset.stateTs;
        }
    };
    Teams.refreshConvStateTimestamp();

    // After new messages arrive via polling: update timestamp, scroll
    document.addEventListener('htmx:afterSwap', function (e) {
        var target = e.detail.target;

        // New messages from polling
        if (target && target.id === 'tm-new-messages-target') {
            var resp = (e.detail.xhr && e.detail.xhr.response) ? e.detail.xhr.response : '';
            // Conta SOLO i wrapper esterni dei bubble nuovi (non gli OOB mutati).
            // Match esatto su `id="tm-msg-N"`: esiste solo sul wrapper, mai sugli
            // interni (tm-msg-bubble, tm-msg-content, …). I mutati hanno anche
            // hx-swap-oob nello stesso tag e vanno esclusi.
            var bubbleTags = resp.match(/<[a-z]+\s[^>]*\bid="tm-msg-\d+"[^>]*>/gi) || [];
            var newCount = 0;
            for (var i = 0; i < bubbleTags.length; i++) {
                if (!/hx-swap-oob/.test(bubbleTags[i])) newCount++;
            }

            // Aggiorna lastStateTimestamp leggendolo DIRETTAMENTE dal response,
            // non dal DOM. Il sentinel viene OOB-swappato DOPO il main swap,
            // quindi a questo punto il DOM è ancora vecchio. Leggere dal response
            // garantisce coerenza.
            var sentinelMatch = resp.match(/id="tm-poll-state-sentinel"[^>]*data-state-ts="([^"]+)"/);
            if (sentinelMatch) {
                Teams.lastStateTimestamp = sentinelMatch[1];
            }
            var convSentinelMatch = resp.match(/id="tm-conv-state-sentinel"[^>]*data-state-ts="([^"]+)"/);
            if (convSentinelMatch) {
                Teams.lastConvStateTimestamp = convSentinelMatch[1];
            }

            Teams.updateLastTimestamp();
            Teams.applyGrouping();
            if (Teams.wasAtBottom) {
                Teams.scrollToBottom();
            } else if (newCount > 0) {
                Teams.showNewMessagesPill(newCount);
            }
            // Un nuovo messaggio può aver portato media/allegati/link nuovi:
            // resetto i tab non visibili del group panel al placeholder così
            // alla prossima apertura del tab i dati saranno freschi.
            if (newCount > 0) {
                Teams.invalidateGroupPanelTabs();
            }
        }

        // Chat panel loaded (conversation switch)
        if (target && target.id === 'tm-chat-panel') {
            Teams.initConversation();
            Teams.highlightActiveConversation();
            Teams.initGroupAvatarCropper();
            Teams.updateAttachmentLabel();
            Teams.updateSendButtonState();
            // Mobile: show chat panel
            var layout = document.querySelector('.tm-layout');
            if (layout) layout.classList.add('tm-show-chat');
        }

        // Re-init Bootstrap tooltips
        Teams.reinitTooltips();
    });

    // Preserve scroll position when loading older messages.
    // Also clear stale reply / new-messages-pill state before the chat panel
    // gets replaced (avoids carrying a reply_to_id across conversations).
    document.addEventListener('htmx:beforeSwap', function (e) {
        var target = e.detail.target;
        if (target && target.id === 'tm-load-older') {
            var messages = document.getElementById('tm-messages');
            if (messages) {
                Teams._scrollHeightBefore = messages.scrollHeight;
            }
        }
        if (target && target.id === 'tm-chat-panel') {
            Teams.clearReply();
            Teams.hideNewMessagesPill();
        }
    });

    document.addEventListener('htmx:afterSettle', function (e) {
        var target = e.detail.target;
        // Restoring scroll after older messages load
        if (Teams._scrollHeightBefore) {
            var messages = document.getElementById('tm-messages');
            if (messages) {
                var diff = messages.scrollHeight - Teams._scrollHeightBefore;
                messages.scrollTop += diff;
            }
            Teams._scrollHeightBefore = null;
            Teams.applyGrouping();
        }
        // Re-apply grouping when a single bubble was edited/replaced
        if (target && target.classList && target.classList.contains('tm-msg')) {
            Teams.applyGrouping();
        }
    });

    Teams.updateLastTimestamp = function () {
        var messages = document.querySelectorAll('[data-timestamp]');
        if (messages.length > 0) {
            var last = messages[messages.length - 1];
            Teams.lastMessageTimestamp = last.dataset.timestamp;
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 6. CONVERSATION SWITCH
    // ═══════════════════════════════════════════════════════════════
    Teams.initConversation = function () {
        Teams.hideNewMessagesPill();
        Teams.clearReply();
        var chat = document.getElementById('tm-chat');
        if (chat) {
            Teams.activeConversationId = chat.dataset.conversationId;
            Teams.lastMessageTimestamp = chat.dataset.lastMessageAt || '';
            // Resync dei sentinel state dopo lo swap del chat panel
            // (il nuovo conv ha sentinel freschi server-rendered).
            Teams.refreshStateTimestamp();
            Teams.refreshConvStateTimestamp();
            Teams.applyGrouping();
            Teams.scrollToBottom();
        } else {
            Teams.activeConversationId = null;
        }
    };

    Teams.highlightActiveConversation = function () {
        // Rimuovi evidenziazione da tutte le conversazioni
        document.querySelectorAll('.tm-conv-item').forEach(function (item) {
            item.classList.remove('tm-conv-active');
        });
        // Evidenzia quella attiva
        if (Teams.activeConversationId) {
            var container = document.querySelector('.tm-container');
            var baseUrl = container ? container.dataset.baseUrl : '';
            var activeLink = baseUrl + '/' + Teams.activeConversationId;
            document.querySelectorAll('.tm-conv-item').forEach(function (item) {
                if (item.getAttribute('href') === activeLink) {
                    item.classList.add('tm-conv-active');
                }
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 7. TYPING INDICATOR — Debounced POST
    // ═══════════════════════════════════════════════════════════════
    Teams.reportTyping = function () {
        if (Teams.typingTimeout) clearTimeout(Teams.typingTimeout);
        Teams.typingTimeout = setTimeout(function () {
            if (!Teams.activeConversationId) return;
            var container = document.querySelector('.tm-container');
            if (!container) return;
            var url = container.dataset.typingUrl;
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-CSRF-Token', meta.content);
            xhr.send('conversation_id=' + Teams.activeConversationId);
        }, 300);
    };

    // ═══════════════════════════════════════════════════════════════
    // 8. MOBILE — Toggle sidebar/chat visibility
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        // Back button: show sidebar
        if (e.target.closest('#tm-back-btn')) {
            var layout = document.querySelector('.tm-layout');
            if (layout) layout.classList.remove('tm-show-chat');
        }

        // Conversation click: update active id + highlight subito (sincrono col
        // click) per due motivi:
        //  1) UX: feedback immediato senza attendere la GET /teams/{id}.
        //  2) Il refresh della conv list innescato da `HX-Trigger:
        //     teamsConvRefresh` parte subito dopo lo swap del chat panel; il
        //     suo configRequest legge `Teams.activeConversationId`, che senza
        //     questo preempt sarebbe ancora il valore della conv precedente
        //     (initConversation gira solo nell'afterSwap del chat panel, e
        //     dipende dall'ordine listener vs dispatch dell'HX-Trigger).
        var convItem = e.target.closest('.tm-conv-item');
        if (convItem) {
            var href = convItem.getAttribute('href') || '';
            var m = href.match(/\/teams\/(\d+)(?:\?|$)/);
            if (m) {
                Teams.activeConversationId = m[1];
                Teams.highlightActiveConversation();
            }
            var layout2 = document.querySelector('.tm-layout');
            if (layout2) layout2.classList.add('tm-show-chat');
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // 9. NEW CONVERSATION MODAL — User selection
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tm-user-select-btn');
        if (!btn) return;

        var userId   = btn.dataset.userId;
        var userName = btn.dataset.userName;
        var userEmail = btn.dataset.userEmail;
        var userAvatar = btn.dataset.userAvatar;
        var userInitials = btn.dataset.userInitials;

        // Check context: direct tab or group tab
        var directTab = btn.closest('#tm-tab-direct');
        var groupTab  = btn.closest('#tm-tab-group');
        // Also check add-member context (offcanvas)
        var addMemberCtx = btn.closest('#tm-group-panel');

        if (directTab) {
            // Direct: select single user
            var hiddenInput = document.getElementById('tm-direct-user-id');
            var selectedDiv = document.getElementById('tm-direct-selected');
            var submitBtn   = document.getElementById('tm-direct-submit');
            var resultsDiv  = document.getElementById('tm-direct-user-results');

            if (hiddenInput) hiddenInput.value = userId;
            if (submitBtn) submitBtn.disabled = false;
            if (resultsDiv) resultsDiv.innerHTML = '';

            if (selectedDiv) {
                var avatarHtml = userAvatar
                    ? '<img src="' + userAvatar + '" alt="" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;">'
                    : '<span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width:28px;height:28px;background-color:var(--accent,#3b82f6);color:#fff;font-size:0.7rem;font-weight:600;">' + userInitials + '</span>';
                selectedDiv.innerHTML = avatarHtml + '<div><div class="small fw-medium">' + userName + '</div><div class="text-muted" style="font-size:0.75rem;">' + userEmail + '</div></div>';
                selectedDiv.classList.remove('d-none');
            }
        } else if (groupTab) {
            // Group: multi-select, add member tag
            var selectedDiv2 = document.getElementById('tm-group-selected-members');
            if (!selectedDiv2) return;

            // Check if already added
            if (selectedDiv2.querySelector('[data-member-id="' + userId + '"]')) return;

            var tag = document.createElement('span');
            tag.className = 'tm-selected-member-tag';
            tag.dataset.memberId = userId;
            tag.innerHTML = userName + '<input type="hidden" name="members[]" value="' + userId + '"><button type="button" class="btn-close btn-close-white" style="font-size:0.5rem;margin-left:4px;"></button>';
            selectedDiv2.appendChild(tag);

            // Close button on tag
            tag.querySelector('.btn-close').addEventListener('click', function () {
                tag.remove();
            });
        } else if (addMemberCtx) {
            // Add member from offcanvas
            var convId = document.getElementById('tm-chat') ? document.getElementById('tm-chat').dataset.conversationId : '';
            if (!convId) return;

            var container = document.querySelector('.tm-container');
            var baseUrl = container ? container.dataset.baseUrl : '';

            var meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) return;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', baseUrl + '/' + convId + '/members', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-CSRF-Token', meta.content);
            xhr.setRequestHeader('HX-Request', 'true');
            xhr.onload = function () {
                if (xhr.status >= 200 && xhr.status < 300) {
                    var memberList = document.getElementById('tm-member-list');
                    if (memberList) memberList.innerHTML = xhr.responseText;
                    var addResults = document.getElementById('tm-add-member-results');
                    if (addResults) addResults.innerHTML = '';
                }
            };
            xhr.send('members[]=' + userId);
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // 10. EDIT MESSAGE — Inline edit
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.tm-edit-msg-btn');
        if (!editBtn) return;

        var msgId  = editBtn.dataset.messageId;
        var convId = editBtn.dataset.convId;
        var body   = editBtn.dataset.body;
        var msgEl  = document.getElementById('tm-msg-' + msgId);
        if (!msgEl) return;

        var bubbleEl = msgEl.querySelector('.tm-msg-bubble');
        if (!bubbleEl) return;

        var container = document.querySelector('.tm-container');
        var baseUrl = container ? container.dataset.baseUrl : '';

        // Replace bubble content with edit form
        var originalHtml = bubbleEl.innerHTML;
        bubbleEl.innerHTML = '<form class="tm-edit-form" hx-post="' + baseUrl + '/' + convId + '/messages/' + msgId + '" hx-target="#tm-msg-' + msgId + '" hx-swap="outerHTML">' +
            '<input type="hidden" name="_method" value="PUT">' +
            '<textarea class="form-control form-control-sm" name="body" rows="1"></textarea>' +
            '<button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check"></i></button>' +
            '<button type="button" class="btn btn-sm btn-outline-secondary tm-edit-cancel"><i class="fa-solid fa-times"></i></button>' +
            '</form>';

        // Set textarea value programmatically to avoid encoding issues
        var textarea = bubbleEl.querySelector('textarea');
        if (textarea) {
            textarea.value = body;
            textarea.focus();
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        // Cancel button
        var cancelBtn = bubbleEl.querySelector('.tm-edit-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                bubbleEl.innerHTML = originalHtml;
            });
        }

        // HTMX process the new form
        if (typeof htmx !== 'undefined') {
            htmx.process(bubbleEl);
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // 11. GLOBAL SEARCH TOGGLE
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        if (e.target.closest('#tm-search-global-btn')) {
            var container = document.querySelector('.tm-container');
            var searchUrl = container ? container.dataset.searchUrl : '';
            if (searchUrl) {
                window.location.href = searchUrl;
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // 12. UTILITIES
    // ═══════════════════════════════════════════════════════════════
    Teams.reinitTooltips = function () {
        if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    };

    // ═══════════════════════════════════════════════════════════════
    // 14. HIDE/SHOW CONVERSATIONS TOGGLE
    // ═══════════════════════════════════════════════════════════════
    // Inizializza dallo stato PHP (il btn è reso con classe 'active' se showHidden=true)
    Teams._showHidden = !!(document.getElementById('tm-toggle-hidden-btn') || {}).classList &&
        document.getElementById('tm-toggle-hidden-btn').classList.contains('active');

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#tm-toggle-hidden-btn')) return;
        Teams._showHidden = !Teams._showHidden;
        var btn = document.getElementById('tm-toggle-hidden-btn');
        if (btn) {
            var icon = btn.querySelector('i');
            if (Teams._showHidden) {
                btn.classList.add('active');
                btn.title = t('js.teams.hide_hidden_conversations', 'Nascondi conversazioni nascoste');
                if (icon) { icon.className = 'fa-solid fa-eye'; }
            } else {
                btn.classList.remove('active');
                btn.title = t('js.teams.show_hidden_conversations', 'Mostra conversazioni nascoste');
                if (icon) { icon.className = 'fa-solid fa-eye-slash'; }
            }
        }
        // Trigger refresh immediato
        htmx.trigger(document.getElementById('tm-conv-list'), 'teamsConvRefresh');
    });

    // ═══════════════════════════════════════════════════════════════
    // 15. EMOJI REACTIONS — Picker toggle
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        // Apri il picker dalla voce "Aggiungi reazione" del dropdown del meta
        var addBtn = e.target.closest('.tm-reaction-add-btn');
        if (addBtn) {
            e.preventDefault();
            e.stopPropagation();
            var msgId  = addBtn.dataset.messageId;
            var picker = document.getElementById('tm-picker-' + msgId);
            if (!picker) return;

            // Chiudi esplicitamente il dropdown Bootstrap del meta (se aperto)
            // prima di mostrare il picker, per evitare flicker o sovrapposizioni.
            var hostDropdown = addBtn.closest('.dropdown');
            if (hostDropdown && window.bootstrap) {
                var hostToggle = hostDropdown.querySelector('[data-bs-toggle="dropdown"]');
                if (hostToggle) {
                    var inst = bootstrap.Dropdown.getInstance(hostToggle);
                    if (inst) inst.hide();
                }
            }

            // Chiudi tutti gli altri picker aperti
            document.querySelectorAll('.tm-emoji-picker:not(.d-none)').forEach(function (p) {
                if (p !== picker) p.classList.add('d-none');
            });

            // Ancorati al meta-wrap del messaggio (stabile anche dopo che il dropdown si chiude).
            var msgEl   = document.getElementById('tm-msg-' + msgId);
            var anchor  = (msgEl && msgEl.querySelector('.tm-msg-meta-wrap')) || addBtn;
            var rect    = anchor.getBoundingClientRect();

            picker.classList.remove('d-none');
            picker.style.position = 'fixed';
            picker.style.top      = (rect.bottom + 6) + 'px';
            picker.style.left     = Math.max(4, rect.right - 220) + 'px';
            return;
        }

        // Chiudi il picker dopo aver cliccato un'emoji
        var emojiBtn = e.target.closest('.tm-emoji-btn');
        if (emojiBtn) {
            var parentPicker = emojiBtn.closest('.tm-emoji-picker');
            if (parentPicker) parentPicker.classList.add('d-none');
            return;
        }

        // Apri popover readers ("Visualizzato da N") in-place, stile picker.
        // NOTA: niente preventDefault/stopPropagation — HTMX deve poter intercettare
        // il click per fare GET su hx-get e popolare #tm-readers-popover-body.
        var readersBtn = e.target.closest('.tm-readers-btn');
        if (readersBtn) {
            // Chiudi il dropdown del meta se aperto
            var rHostDropdown = readersBtn.closest('.dropdown');
            if (rHostDropdown && window.bootstrap) {
                var rHostToggle = rHostDropdown.querySelector('[data-bs-toggle="dropdown"]');
                if (rHostToggle) {
                    var rInst = bootstrap.Dropdown.getInstance(rHostToggle);
                    if (rInst) rInst.hide();
                }
            }

            var rPop = document.getElementById('tm-readers-popover');
            if (!rPop) return;

            // Reset body a "Caricamento..." (HTMX lo sostituirà al ritorno della richiesta)
            var rBody = document.getElementById('tm-readers-popover-body');
            if (rBody) {
                rBody.innerHTML = '<div class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>' + t('js.teams.loading', 'Caricamento...') + '</div>';
            }

            // Ancora al meta-wrap del messaggio
            var rMsgId  = readersBtn.dataset.messageId;
            var rMsgEl  = document.getElementById('tm-msg-' + rMsgId);
            var rAnchor = (rMsgEl && rMsgEl.querySelector('.tm-msg-meta-wrap')) || readersBtn;
            var rRect   = rAnchor.getBoundingClientRect();

            rPop.classList.remove('d-none');
            rPop.style.position = 'fixed';
            rPop.style.top      = (rRect.bottom + 6) + 'px';
            rPop.style.left     = Math.max(4, rRect.right - 260) + 'px';
            return;
        }

        // Chiudi popover readers cliccando fuori (no chiude se click su un altro readers-btn,
        // perché il blocco sopra lo riposiziona).
        if (!e.target.closest('.tm-readers-popover') && !e.target.closest('.tm-readers-btn')) {
            var rPopClose = document.getElementById('tm-readers-popover');
            if (rPopClose && !rPopClose.classList.contains('d-none')) {
                rPopClose.classList.add('d-none');
            }
        }

        // Chiudi picker cliccando fuori
        if (!e.target.closest('.tm-emoji-picker') && !e.target.closest('.tm-reaction-add-btn')) {
            document.querySelectorAll('.tm-emoji-picker:not(.d-none)').forEach(function (p) {
                p.classList.add('d-none');
            });
        }
    });

    // ESC chiude popover readers + emoji picker
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var rPopEsc = document.getElementById('tm-readers-popover');
        if (rPopEsc && !rPopEsc.classList.contains('d-none')) {
            rPopEsc.classList.add('d-none');
        }
        document.querySelectorAll('.tm-emoji-picker:not(.d-none)').forEach(function (p) {
            p.classList.add('d-none');
        });
    });

    // Dopo HTMX swap del wrapper reazioni: riprocessa per registrare hx-* sui nuovi elementi
    document.addEventListener('htmx:afterSwap', function (e) {
        var target = e.detail.target;
        if (target && target.id && target.id.indexOf('tm-rx-') === 0) {
            if (typeof htmx !== 'undefined') htmx.process(target);
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // 13. GROUP AVATAR CROPPER
    // ═══════════════════════════════════════════════════════════════
    Teams.initGroupAvatarCropper = function () {
        var config = document.getElementById('tm-group-cropper-config');
        var input  = document.getElementById('tm-group-avatar-input');
        var btn    = document.getElementById('tm-group-avatar-btn');
        if (!config || !input || typeof AvatarCropper === 'undefined') return;

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');

        AvatarCropper.init({
            context:   config.dataset.context,
            contextId: parseInt(config.dataset.contextId, 10),
            cropUrl:   config.dataset.cropUrl,
            csrfToken: csrfMeta ? csrfMeta.content : '',
            onSuccess: function (data) {
                var preview = document.getElementById('tm-group-avatar-preview');
                if (preview) {
                    if (preview.tagName === 'IMG') {
                        preview.src = data.url;
                    } else {
                        var img = document.createElement('img');
                        img.src = data.url;
                        img.alt = '';
                        img.id = 'tm-group-avatar-preview';
                        img.className = 'tm-avatar-img-lg';
                        preview.parentNode.replaceChild(img, preview);
                    }
                }
                if (typeof window.notify === 'function') {
                    window.notify(t('js.teams.avatar_updated', 'Avatar del gruppo aggiornato.'), 'success');
                }
            },
            onError: function (msg) {
                alert(msg);
            }
        });

        // File input change → open cropper
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                AvatarCropper.open(input.files[0]);
                input.value = '';
            }
        });

        // Button click → trigger file input
        if (btn) {
            btn.addEventListener('click', function () {
                input.click();
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // MESSAGE GROUPING (client-side post-swap)
    //   I bubble vengono renderizzati sempre con avatar+sender; questa
    //   funzione aggiunge/rimuove la classe .tm-msg-grouped confrontando
    //   data-user-id e data-timestamp con il messaggio precedente. Una
    //   data divider o un break > 5 min interrompe il gruppo.
    // ═══════════════════════════════════════════════════════════════
    Teams.GROUPING_WINDOW_SECONDS = 300;

    Teams.applyGrouping = function (root) {
        var container = (root && root.id === 'tm-messages')
            ? root
            : document.getElementById('tm-messages');
        if (!container) return;
        var nodes = container.querySelectorAll('.tm-msg');
        var prevUserId = null;
        var prevTs = null;
        nodes.forEach(function (msg) {
            // System message: spezza il gruppo
            if (msg.classList.contains('tm-msg-system')) {
                msg.classList.remove('tm-msg-grouped');
                prevUserId = null;
                prevTs = null;
                return;
            }
            // Date divider tra questo e il precedente?
            var dividerBetween = false;
            var sib = msg.previousElementSibling;
            while (sib && !sib.classList?.contains('tm-msg')) {
                if (sib.classList?.contains('tm-date-divider')) {
                    dividerBetween = true;
                    break;
                }
                sib = sib.previousElementSibling;
            }
            var userId = msg.dataset.userId || '';
            var ts = Date.parse(msg.dataset.timestamp || '');
            if (isNaN(ts)) ts = null;

            var consecutive = !dividerBetween
                && prevUserId !== null
                && prevUserId === userId
                && prevTs !== null
                && ts !== null
                && (ts - prevTs) / 1000 >= 0
                && (ts - prevTs) / 1000 < Teams.GROUPING_WINDOW_SECONDS;

            msg.classList.toggle('tm-msg-grouped', consecutive);

            prevUserId = userId;
            prevTs = ts;
        });
    };

    // ═══════════════════════════════════════════════════════════════
    // CSP-SAFE EVENT DELEGATION
    //   Inline onclick="..." è bloccato dalla CSP (no unsafe-inline).
    //   Tutti i comportamenti delle view sono delegati qui.
    // ═══════════════════════════════════════════════════════════════
    Teams._highlightAndScroll = function (targetId) {
        var p = document.getElementById(targetId);
        if (!p) return;
        p.scrollIntoView({ block: 'center', behavior: 'smooth' });
        p.classList.add('tm-msg-highlight');
        setTimeout(function () { p.classList.remove('tm-msg-highlight'); }, 1200);
    };

    document.addEventListener('click', function (e) {
        // Unhide conversazione (button dentro un <a> parent: evita navigazione)
        var unhideBtn = e.target.closest('.tm-conv-unhide-btn');
        if (unhideBtn) {
            e.preventDefault();
            e.stopPropagation();
            // hx-post resta gestito da HTMX a prescindere
            return;
        }

        // Scroll a un messaggio (reply quote, pinned list)
        var scrollLink = e.target.closest('.tm-scroll-to-msg');
        if (scrollLink) {
            var targetId = scrollLink.dataset.targetId;
            if (targetId) {
                e.preventDefault();
                Teams._highlightAndScroll(targetId);
            }
            return;
        }

        // Inserisci greeting predefinito nell'input (empty messages state)
        var greetBtn = e.target.closest('.tm-empty-greet-btn');
        if (greetBtn) {
            var input = document.getElementById('tm-msg-input');
            if (input) {
                e.preventDefault();
                input.value = greetBtn.dataset.greeting || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus();
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // PINNED MESSAGES BADGE REFRESH
    // ═══════════════════════════════════════════════════════════════
    document.body.addEventListener('teamsPinnedRefresh', function () {
        var badge = document.getElementById('tm-pinned-count');
        var convId = Teams.activeConversationId;
        if (!badge || !convId) return;
        var container = document.querySelector('.tm-container');
        var base = container ? container.dataset.baseUrl : '';
        // Quick HEAD-style approach: rely on the partial that already returns
        // the count via header. Use a lightweight fetch to keep badge accurate.
        fetch(base + '/' + convId + '/pinned', {
            credentials: 'same-origin',
            headers: { 'HX-Request': 'true' },
        }).then(function (r) { return r.text(); })
          .then(function (html) {
              var match = html.match(/tm-pinned-item/g);
              var count = match ? match.length : 0;
              badge.textContent = count;
              badge.classList.toggle('d-none', count === 0);
          })
          .catch(function () {});
    });

    // ═══════════════════════════════════════════════════════════════
    // @MENTION AUTOCOMPLETE
    // ═══════════════════════════════════════════════════════════════
    Teams._mention = {
        dropdown: null,
        items: [],
        active: -1,
        matchStart: -1,
        query: '',
        lastFetchKey: '',
    };

    Teams._mentionDropdown = function () {
        var dd = Teams._mention.dropdown;
        if (dd) return dd;
        dd = document.createElement('div');
        dd.id = 'tm-mention-dropdown';
        dd.className = 'tm-mention-dropdown d-none';
        document.body.appendChild(dd);
        Teams._mention.dropdown = dd;
        return dd;
    };

    Teams._mentionHideDropdown = function () {
        var dd = Teams._mention.dropdown;
        if (dd) dd.classList.add('d-none');
        Teams._mention.items = [];
        Teams._mention.active = -1;
        Teams._mention.matchStart = -1;
        Teams._mention.query = '';
    };

    Teams._mentionRender = function (items, input) {
        var dd = Teams._mentionDropdown();
        if (!items.length) {
            Teams._mentionHideDropdown();
            return;
        }
        dd.innerHTML = items.map(function (u, i) {
            var avatar = u.avatar_url
                ? '<img src="' + u.avatar_url + '" class="tm-mention-avatar" alt="">'
                : '<span class="tm-mention-avatar tm-mention-avatar-initials">'
                  + (u.name || '?').trim().charAt(0).toUpperCase()
                  + '</span>';
            return '<button type="button" class="tm-mention-item' + (i === 0 ? ' tm-mention-active' : '') + '"'
                + ' data-mention-name="' + (u.username || (u.name || '').replace(/\s+/g, '')) + '"'
                + ' data-mention-index="' + i + '">'
                + avatar
                + '<span class="tm-mention-info">'
                + '<span class="tm-mention-name">' + (u.name || '') + '</span>'
                + (u.username ? '<small class="text-muted">@' + u.username + '</small>' : '')
                + '</span>'
                + '</button>';
        }).join('');
        Teams._mention.items = items;
        Teams._mention.active = 0;

        // Position near caret (approximated by anchor on the input area)
        var rect = input.getBoundingClientRect();
        dd.style.left = rect.left + 'px';
        dd.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
        dd.style.maxWidth = Math.max(220, rect.width) + 'px';
        dd.classList.remove('d-none');
    };

    Teams._mentionScanInput = function (input) {
        var pos = input.selectionStart;
        var value = input.value.slice(0, pos);
        var match = value.match(/(?:^|[^\w@])@([\p{L}0-9_.\-]{1,30})$/u);
        if (!match) {
            Teams._mentionHideDropdown();
            return;
        }
        Teams._mention.matchStart = pos - match[1].length - 1; // index of "@"
        Teams._mention.query = match[1];
        Teams._mentionFetch(input);
    };

    Teams._mentionFetch = function (input) {
        var convId = Teams.activeConversationId;
        if (!convId) return;
        var key = convId + '|' + Teams._mention.query;
        if (Teams._mention.lastFetchKey === key) return;
        Teams._mention.lastFetchKey = key;
        var container = document.querySelector('.tm-container');
        var base = container ? container.dataset.baseUrl : '';
        var url = base + '/' + convId + '/mentions/autocomplete?q=' + encodeURIComponent(Teams._mention.query);
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (items) { Teams._mentionRender(items || [], input); })
            .catch(function () { Teams._mentionHideDropdown(); });
    };

    Teams._mentionInsert = function (input, name) {
        if (!input || Teams._mention.matchStart < 0) return;
        var pos = input.selectionStart;
        var before = input.value.slice(0, Teams._mention.matchStart);
        var after  = input.value.slice(pos);
        var insert = '@' + name + ' ';
        input.value = before + insert + after;
        var caret = before.length + insert.length;
        input.setSelectionRange(caret, caret);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        Teams._mentionHideDropdown();
    };

    document.addEventListener('input', function (e) {
        if (e.target.id !== 'tm-msg-input') return;
        Teams._mentionScanInput(e.target);
    });

    document.addEventListener('keydown', function (e) {
        if (e.target.id !== 'tm-msg-input') return;
        var dd = Teams._mention.dropdown;
        if (!dd || dd.classList.contains('d-none') || !Teams._mention.items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            Teams._mention.active = (Teams._mention.active + 1) % Teams._mention.items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            Teams._mention.active = (Teams._mention.active - 1 + Teams._mention.items.length) % Teams._mention.items.length;
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            var u = Teams._mention.items[Teams._mention.active];
            if (u) Teams._mentionInsert(e.target, u.username || (u.name || '').replace(/\s+/g, ''));
            return;
        } else if (e.key === 'Escape') {
            Teams._mentionHideDropdown();
            return;
        } else {
            return;
        }
        // Update active highlight
        var items = dd.querySelectorAll('.tm-mention-item');
        items.forEach(function (el, i) {
            el.classList.toggle('tm-mention-active', i === Teams._mention.active);
        });
    }, true);

    document.addEventListener('click', function (e) {
        var item = e.target.closest('.tm-mention-item');
        if (item) {
            e.preventDefault();
            var input = document.getElementById('tm-msg-input');
            Teams._mentionInsert(input, item.dataset.mentionName);
            return;
        }
        // Click outside dropdown → close
        if (!e.target.closest('#tm-mention-dropdown') && !e.target.closest('#tm-msg-input')) {
            Teams._mentionHideDropdown();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // REPLY / QUOTE
    // ═══════════════════════════════════════════════════════════════
    Teams.setReply = function (messageId, userName, preview) {
        var hidden  = document.getElementById('tm-reply-to-id');
        var banner  = document.getElementById('tm-reply-banner');
        var nameEl  = document.getElementById('tm-reply-banner-name');
        var prevEl  = document.getElementById('tm-reply-banner-preview');
        if (!hidden || !banner) return;
        hidden.value = String(messageId);
        if (nameEl) nameEl.textContent = userName || t('js.teams.default_user_name', 'Utente');
        if (prevEl) prevEl.textContent = preview || '';
        banner.classList.remove('d-none');
        var input = document.getElementById('tm-msg-input');
        if (input) input.focus();
        // Banner reply visibile → input area più alta → riposiziona la pill
        if (typeof Teams.repositionNewMessagesPill === 'function') {
            Teams.repositionNewMessagesPill();
        }
    };

    Teams.clearReply = function () {
        var hidden = document.getElementById('tm-reply-to-id');
        var banner = document.getElementById('tm-reply-banner');
        if (hidden) hidden.value = '';
        if (banner) banner.classList.add('d-none');
        if (typeof Teams.repositionNewMessagesPill === 'function') {
            Teams.repositionNewMessagesPill();
        }
    };

    // Click handlers delegated on document (so they survive HTMX swaps).
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tm-reply-btn');
        if (btn) {
            e.preventDefault();
            Teams.setReply(
                btn.dataset.messageId,
                btn.dataset.userName,
                btn.dataset.body
            );
            // Close the open dropdown manually (Bootstrap may keep it open after click).
            var dropdown = btn.closest('.dropdown');
            if (dropdown) {
                var toggle = dropdown.querySelector('[data-bs-toggle="dropdown"]');
                if (toggle && window.bootstrap) {
                    var inst = bootstrap.Dropdown.getInstance(toggle);
                    if (inst) inst.hide();
                }
            }
            return;
        }
        if (e.target.closest('#tm-reply-banner-close')) {
            Teams.clearReply();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // DRAG & DROP FILE ATTACHMENT
    // ═══════════════════════════════════════════════════════════════
    Teams.initDragDrop = function () {
        if (Teams._dragDropBound) return;
        Teams._dragDropBound = true;

        // Suppress default drag behavior on the whole chat container so
        // drops outside the input area don't navigate away.
        var container = document.querySelector('.tm-container');
        if (!container) return;

        container.addEventListener('dragover', function (e) {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types).includes('Files')) return;
            e.preventDefault();
            var inputArea = container.querySelector('.tm-input-area');
            if (inputArea) inputArea.classList.add('tm-drop-target');
        });

        container.addEventListener('dragleave', function (e) {
            if (e.target === container || (e.relatedTarget && container.contains(e.relatedTarget))) return;
            var inputArea = container.querySelector('.tm-input-area');
            if (inputArea) inputArea.classList.remove('tm-drop-target');
        });

        container.addEventListener('drop', function (e) {
            if (!e.dataTransfer || !e.dataTransfer.files || e.dataTransfer.files.length === 0) return;
            var inputArea = container.querySelector('.tm-input-area');
            var fileInput = document.getElementById('tm-msg-attachment');
            if (inputArea) inputArea.classList.remove('tm-drop-target');
            if (!fileInput) return;

            e.preventDefault();

            // Inject dropped files into the existing <input type="file" multiple>.
            try {
                var dt = new DataTransfer();
                for (var i = 0; i < e.dataTransfer.files.length; i++) {
                    dt.items.add(e.dataTransfer.files[i]);
                }
                fileInput.files = dt.files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (err) {
                if (typeof window.notify === 'function') {
                    window.notify({ type: 'danger', message: t('js.teams.invalid_attachment', 'Allegato non valido.') });
                }
            }
        });
    };

    // ═══════════════════════════════════════════════════════════════
    // EMOJI PICKER — Popover per inserire emoji nella textarea
    // ═══════════════════════════════════════════════════════════════
    Teams.EmojiPicker = {
        open: false,
        toggle: function () {
            var pop = document.getElementById('tm-emoji-popover');
            var btn = document.getElementById('tm-emoji-btn');
            if (!pop) return;
            this.open = pop.classList.contains('d-none');
            pop.classList.toggle('d-none', !this.open);
            if (btn) btn.setAttribute('aria-expanded', this.open ? 'true' : 'false');
        },
        close: function () {
            var pop = document.getElementById('tm-emoji-popover');
            var btn = document.getElementById('tm-emoji-btn');
            if (pop) pop.classList.add('d-none');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            this.open = false;
        },
        insert: function (emoji) {
            var input = document.getElementById('tm-msg-input');
            if (!input) return;
            var start = input.selectionStart || 0;
            var end   = input.selectionEnd || 0;
            input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
            var caret = start + emoji.length;
            input.focus();
            input.setSelectionRange(caret, caret);
            // Notifica i listener (auto-resize, counter, send button state)
            input.dispatchEvent(new Event('input', { bubbles: true }));
        },
        switchTab: function (tabKey) {
            document.querySelectorAll('.tm-emoji-tab').forEach(function (t) {
                t.classList.toggle('active', t.dataset.tab === tabKey);
            });
            document.querySelectorAll('[data-tab-panel]').forEach(function (p) {
                p.classList.toggle('d-none', p.dataset.tabPanel !== tabKey);
            });
        }
    };

    document.addEventListener('click', function (e) {
        if (e.target.closest('#tm-emoji-btn')) {
            e.preventDefault();
            Teams.EmojiPicker.toggle();
            return;
        }
        var pick = e.target.closest('.tm-emoji-pick');
        if (pick) {
            e.preventDefault();
            Teams.EmojiPicker.insert(pick.dataset.emoji || '');
            return;
        }
        var tab = e.target.closest('.tm-emoji-tab');
        if (tab) {
            e.preventDefault();
            Teams.EmojiPicker.switchTab(tab.dataset.tab);
            return;
        }
        // Click fuori → chiudi picker se aperto
        if (Teams.EmojiPicker.open
            && !e.target.closest('#tm-emoji-popover')
            && !e.target.closest('#tm-emoji-btn')) {
            Teams.EmojiPicker.close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && Teams.EmojiPicker.open) {
            Teams.EmojiPicker.close();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // FORMAT BUBBLE — Toolbar fluttuante su selezione testo
    //   Wrappa la selezione in markdown leggero (bold, italic, code,
    //   quote). La sintassi finale viene processata server-side da
    //   MarkdownRenderer::render, niente parsing client.
    // ═══════════════════════════════════════════════════════════════
    Teams.FormatBubble = {
        show: function (input) {
            if (!input) return;
            var start = input.selectionStart, end = input.selectionEnd;
            if (start === end) { this.hide(); return; }

            // Priorità al mention dropdown: se sta scrivendo @nome, niente bubble
            var mention = document.getElementById('tm-mention-dropdown');
            if (mention && !mention.classList.contains('d-none')) {
                this.hide();
                return;
            }

            var bubble = document.getElementById('tm-format-bubble');
            if (!bubble) return;
            // Rende visibile per misurare l'altezza
            bubble.classList.remove('d-none');
            var rect = input.getBoundingClientRect();
            var bubbleHeight = bubble.offsetHeight || 36;
            // Posiziona sopra il textarea, allineato a sinistra con un piccolo offset
            var top = rect.top - bubbleHeight - 6;
            if (top < 4) top = rect.bottom + 6; // fallback sotto se non c'è spazio
            bubble.style.left = (rect.left + 8) + 'px';
            bubble.style.top  = top + 'px';
        },
        hide: function () {
            var b = document.getElementById('tm-format-bubble');
            if (b) b.classList.add('d-none');
        },
        wrap: function (fmt) {
            var input = document.getElementById('tm-msg-input');
            if (!input) return;
            var start = input.selectionStart, end = input.selectionEnd;
            var sel = input.value.slice(start, end);
            if (!sel) return;
            var wrapped;
            switch (fmt) {
                case 'bold':   wrapped = '**' + sel + '**'; break;
                case 'italic': wrapped = '*'  + sel + '*';  break;
                case 'code':   wrapped = '`'  + sel + '`';  break;
                case 'quote':
                    wrapped = sel.split('\n').map(function (l) {
                        return l.indexOf('> ') === 0 ? l : '> ' + l;
                    }).join('\n');
                    break;
                default: return;
            }
            input.value = input.value.slice(0, start) + wrapped + input.value.slice(end);
            // Riseleziona il testo wrappato per consentire toggle a catena
            input.setSelectionRange(start, start + wrapped.length);
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            this.hide();
        }
    };

    // Trigger primario: l'evento `select` su <textarea> è ben supportato.
    document.addEventListener('select', function (e) {
        if (e.target && e.target.id === 'tm-msg-input') {
            Teams.FormatBubble.show(e.target);
        }
    }, true);

    // Fallback per browser/situazioni in cui `select` non si aggiorna in tempo.
    ['mouseup', 'keyup'].forEach(function (ev) {
        document.addEventListener(ev, function (e) {
            if (!e.target || e.target.id !== 'tm-msg-input') return;
            if (e.target.selectionStart !== e.target.selectionEnd) {
                Teams.FormatBubble.show(e.target);
            } else {
                Teams.FormatBubble.hide();
            }
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.tm-fmt-btn');
        if (btn) {
            e.preventDefault();
            Teams.FormatBubble.wrap(btn.dataset.fmt);
            return;
        }
        // Click fuori dalla bubble e dalla textarea → nascondi
        if (!e.target.closest('#tm-format-bubble')
            && (!e.target.id || e.target.id !== 'tm-msg-input')) {
            Teams.FormatBubble.hide();
        }
    });

    // Lo scroll della lista messaggi sposta il textarea: la bubble non lo segue,
    // meglio nasconderla per evitare disallineamenti.
    document.addEventListener('scroll', function (e) {
        if (e.target && e.target.id === 'tm-messages') {
            Teams.FormatBubble.hide();
        }
    }, true);

    // Resize finestra: chiude bubble + emoji picker per evitare posizionamenti rotti,
    // riposiziona la pill nuovi-messaggi (altezza viewport può cambiare).
    window.addEventListener('resize', function () {
        Teams.FormatBubble.hide();
        if (Teams.EmojiPicker && Teams.EmojiPicker.open) Teams.EmojiPicker.close();
        if (typeof Teams.repositionNewMessagesPill === 'function') {
            Teams.repositionNewMessagesPill();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // ATTACHMENT CHIPS — Rimozione singola via x sulla chip
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('click', function (e) {
        var x = e.target.closest('.tm-chip-x');
        if (!x) return;
        e.preventDefault();
        var idx = parseInt(x.dataset.remove, 10);
        if (!isNaN(idx)) Teams.removeAttachment(idx);
    });

    // ═══════════════════════════════════════════════════════════════
    // IMAGE LIGHTBOX — Espansione fullscreen stile Telegram
    //   Le immagini allegate ai messaggi sono rese inline con
    //   .tm-msg-image; al click si apre un overlay che mostra
    //   l'immagine alla massima risoluzione disponibile.
    // ═══════════════════════════════════════════════════════════════
    Teams.Lightbox = {
        open: function (src, caption) {
            var box = document.getElementById('tm-lightbox');
            if (!box) {
                box = document.createElement('div');
                box.id = 'tm-lightbox';
                box.className = 'tm-lightbox d-none';
                box.setAttribute('role', 'dialog');
                box.setAttribute('aria-label', t('js.teams.image_preview_aria', 'Anteprima immagine'));
                box.innerHTML =
                    '<button type="button" class="tm-lightbox-close" aria-label="' + t('js.teams.close', 'Chiudi') + '">&times;</button>'
                  + '<img class="tm-lightbox-img" alt="">'
                  + '<div class="tm-lightbox-caption"></div>';
                document.body.appendChild(box);
            }
            var img = box.querySelector('.tm-lightbox-img');
            var cap = box.querySelector('.tm-lightbox-caption');
            if (img) img.src = src;
            if (cap) cap.textContent = caption || '';
            box.classList.remove('d-none');
            document.body.classList.add('tm-lightbox-open');
        },
        close: function () {
            var box = document.getElementById('tm-lightbox');
            if (!box) return;
            box.classList.add('d-none');
            var img = box.querySelector('.tm-lightbox-img');
            if (img) img.src = '';
            document.body.classList.remove('tm-lightbox-open');
        }
    };

    document.addEventListener('click', function (e) {
        var thumb = e.target.closest('.tm-msg-image, .tm-gp-media-thumb');
        if (thumb) {
            e.preventDefault();
            Teams.Lightbox.open(
                thumb.dataset.fullsrc || thumb.getAttribute('src'),
                thumb.dataset.caption || thumb.getAttribute('alt') || ''
            );
            return;
        }
        if (e.target.closest('.tm-lightbox-close')) {
            e.preventDefault();
            Teams.Lightbox.close();
            return;
        }
        // Click sull'overlay (non sull'immagine) → chiude
        if (e.target.id === 'tm-lightbox') {
            Teams.Lightbox.close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var box = document.getElementById('tm-lightbox');
            if (box && !box.classList.contains('d-none')) Teams.Lightbox.close();
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // GROUP PANEL OFFCANVAS — Tabs lazy load + member filter
    //   Carica il tab content (media/files/links) la prima volta che il
    //   tab viene mostrato; persiste l'ultimo tab attivo in localStorage
    //   per conversazione.
    // ═══════════════════════════════════════════════════════════════
    /**
     * Invalida i tab media/files/links del group panel (riporta al placeholder),
     * tranne quello attualmente attivo (per non rompere lo scroll dell'utente).
     * Il successivo shown.bs.tab triggererà un nuovo lazy load → dati freschi.
     */
    Teams.invalidateGroupPanelTabs = function () {
        var panel = document.getElementById('tm-group-panel');
        if (!panel) return;
        var activeBtn = panel.querySelector('.tm-gp-tabs .nav-link.active');
        var activeTab = activeBtn && activeBtn.dataset ? activeBtn.dataset.tab : null;
        ['media', 'files', 'links'].forEach(function (key) {
            if (activeTab === key) return;
            var pane = document.getElementById('tm-gp-tab-' + key);
            if (!pane || !pane.dataset.lazyUrl) return;
            pane.innerHTML = '<div class="tm-gp-tab-placeholder text-center py-4 text-muted">'
                           + '<span class="spinner-border spinner-border-sm me-2"></span>' + t('js.teams.loading', 'Caricamento...')
                           + '</div>';
        });
    };

    Teams.initGroupPanel = function () {
        var panel = document.getElementById('tm-group-panel');
        if (!panel) return;
        var convId = panel.dataset.conversationId;
        if (!convId) return;

        // Ripristina l'ultimo tab attivo
        try {
            var lastTab = window.localStorage.getItem('teams.groupPanelTab.' + convId);
            if (lastTab) {
                var btn = document.getElementById('tm-gp-tab-' + lastTab + '-btn');
                if (btn && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance(btn).show();
                }
            }
        } catch (err) { /* localStorage disabilitato — ignore */ }
    };

    // shown.bs.tab → lazy load del contenuto, persist scelta
    document.addEventListener('shown.bs.tab', function (e) {
        var btn = e.target;
        if (!btn || !btn.dataset || !btn.dataset.tab) return;
        var tabKey = btn.dataset.tab; // members | media | files | links
        var panel  = btn.closest('.tm-group-panel');
        if (!panel) return;
        var convId = panel.dataset.conversationId;
        if (!convId) return;

        // Persist scelta
        try {
            window.localStorage.setItem('teams.groupPanelTab.' + convId, tabKey);
        } catch (err) { /* ignore */ }

        // Tab Membri è eager, niente lazy
        if (tabKey === 'members') {
            // Init / re-init filter input quando si torna su Membri
            Teams.initMemberFilter();
            return;
        }

        // Lazy load delle altre 3 sezioni. Carichiamo solo se il pane mostra
        // ancora il placeholder iniziale (cioè non è mai stato popolato in
        // questa istanza del DOM). Una volta caricato il contenuto preserviamo
        // lo scroll: niente refetch al cambio tab.
        // Quando l'utente cambia conversazione, chat_panel viene rimpiazzato
        // e il nuovo pane torna ad avere il placeholder → lazy load corretto.
        var pane = document.getElementById('tm-gp-tab-' + tabKey);
        if (!pane) return;
        if (!pane.querySelector('.tm-gp-tab-placeholder')) return;

        var url = pane.dataset.lazyUrl;
        if (!url || typeof htmx === 'undefined') return;
        htmx.ajax('GET', url, { target: '#' + pane.id, swap: 'innerHTML' });
    });

    // Filtro client-side dei membri (no round-trip server)
    Teams.initMemberFilter = function () {
        var input = document.getElementById('tm-gp-member-filter');
        if (!input || input.dataset.bound === '1') {
            // se è già stato bindato (HTMX afterSwap rimette l'elemento) → noop
            return;
        }
        input.dataset.bound = '1';
        input.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var rows = document.querySelectorAll('#tm-member-list .tm-gp-member-row');
            var anyVisible = false;
            rows.forEach(function (row) {
                var name  = row.dataset.name || '';
                var email = row.dataset.email || '';
                var match = !q || name.indexOf(q) !== -1 || email.indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) anyVisible = true;
            });
            // Hide also section titles when their section is fully empty
            document.querySelectorAll('#tm-member-list .tm-gp-member-section-title').forEach(function (title) {
                // Una sezione titolo è seguita dalle sue righe finché non incontra
                // un'altra section-title. Controlliamo se almeno una è visibile.
                var sibling = title.nextElementSibling;
                var hasVisibleRow = false;
                while (sibling && !sibling.classList.contains('tm-gp-member-section-title')) {
                    if (sibling.classList.contains('tm-gp-member-row')
                        && sibling.style.display !== 'none') {
                        hasVisibleRow = true;
                        break;
                    }
                    sibling = sibling.nextElementSibling;
                }
                title.style.display = hasVisibleRow ? '' : 'none';
            });
        });
    };

    // Re-init member filter dopo HTMX swap su #tm-member-list (es. rimuovi/aggiungi membro)
    document.addEventListener('htmx:afterSwap', function (e) {
        var target = e.detail.target;
        if (!target) return;
        // Il wrapper #tm-member-list viene rimpiazzato interno, non si rebinda
        // automaticamente. Il filter input sta fuori dal wrapper, quindi resta
        // dom-stable; basta resettare il flag bound e ri-applicare il filtro
        // corrente sui nuovi nodi.
        if (target.id === 'tm-member-list') {
            var input = document.getElementById('tm-gp-member-filter');
            if (input && input.value) {
                // ri-applica filtro pre-esistente
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    });

    // ═══════════════════════════════════════════════════════════════
    // 14. INIT
    // ═══════════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', function () {
        Teams.startHeartbeat();
        Teams.initConversation();
        Teams.initGroupAvatarCropper();
        Teams.initDragDrop();
        Teams.updateCharCounter();
        Teams.initGroupPanel();
        Teams.initMemberFilter();
    });

    // Re-init group panel anche dopo lo switch di conversazione (chat panel swap)
    document.addEventListener('htmx:afterSwap', function (e) {
        var target = e.detail.target;
        if (target && target.id === 'tm-chat-panel') {
            Teams.initGroupPanel();
            Teams.initMemberFilter();
        }
    });

    // Cleanup on page leave
    window.addEventListener('beforeunload', function () {
        Teams.stopHeartbeat();
    });

})();
