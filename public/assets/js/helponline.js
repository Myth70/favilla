(function () {
    'use strict';

    function initHelpOnline() {
        var root = document.getElementById('ho-root');
        var offcanvasEl = document.getElementById('ho-offcanvas');
        var host = document.getElementById('ho-panel-host');
        if (!root || !offcanvasEl || !host || typeof bootstrap === 'undefined') {
            return;
        }

        var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
        var state = { loaded: false, loading: false, pending: false };

        function csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        function notify(message, type) {
            if (typeof window.notify === 'function') {
                window.notify({ message: message, type: type || 'info', channel: 'toast' });
            }
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = String(s == null ? '' : s);
            return d.innerHTML;
        }

        function request(url, body, onSuccess) {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { json: json, ok: response.ok };
                }).catch(function () {
                    return { json: null, ok: response.ok };
                });
            }).then(function (result) {
                onSuccess(result.json, result.ok);
            }).catch(function () {
                onSuccess(null, false);
                notify(t('js.helponline.unreachable', 'Help Online non risponde in questo momento.'), 'danger');
            });
        }

        function panelParams() {
            return new URLSearchParams({
                contextPath: root.dataset.currentPath || window.location.pathname || '/',
                pageTitle: root.dataset.pageTitle || document.title || ''
            });
        }

        function renderLoading() {
            host.innerHTML = '<div class="ho-panel-loading">'
                + '<div class="ho-panel-spinner" aria-hidden="true"></div>'
                + '<div class="text-muted small">' + t('js.helponline.loading', 'Caricamento guida...') + '</div>'
                + '</div>';
        }

        function renderError() {
            host.innerHTML = '<div class="ho-panel-loading"><div class="text-muted small">' + t('js.helponline.load_error', 'Impossibile caricare il pannello help.') + '</div></div>';
        }

        function ensurePanelLoaded(forceReload) {
            if (state.loading || (state.loaded && !forceReload)) {
                return;
            }

            state.loading = true;
            renderLoading();

            fetch(root.dataset.panelUrl + '?' + panelParams().toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.text();
            }).then(function (html) {
                host.innerHTML = html;
                state.loaded = true;
                state.loading = false;
                bindAutosize();
                bindCounter();
                var input = host.querySelector('#ho-chat-input');
                if (input) {
                    setTimeout(function () { input.focus(); }, 50);
                }
            }).catch(function () {
                state.loading = false;
                renderError();
            });
        }

        function autosize(el) {
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 128) + 'px';
        }

        function updateCounter() {
            var input = host.querySelector('#ho-chat-input');
            var counter = host.querySelector('#ho-chat-counter');
            var sendBtn = host.querySelector('#ho-chat-form .ho-chat-send');
            if (!input || !counter) return;

            var max = parseInt(input.getAttribute('maxlength'), 10) || 500;
            var len = input.value.length;
            counter.textContent = len + ' / ' + max;
            counter.classList.toggle('is-warning', len >= max * 0.8 && len < max);
            counter.classList.toggle('is-danger', len >= max);

            if (sendBtn && !state.pending) {
                sendBtn.disabled = input.value.trim() === '';
            }
        }

        function bindAutosize() {
            var input = host.querySelector('#ho-chat-input');
            if (!input) return;
            autosize(input);
            input.addEventListener('input', function () {
                autosize(input);
                updateCounter();
            });
        }

        function bindCounter() {
            updateCounter();
        }

        function scrollMessages() {
            var scroller = host.querySelector('#ho-chat-scroll');
            if (scroller) {
                scroller.scrollTop = scroller.scrollHeight;
            }
        }

        function messagesNode() {
            return host.querySelector('#ho-chat-messages');
        }

        function clearStarterSections() {
            host.querySelectorAll('[data-ho-starter]').forEach(function (node) { node.remove(); });
        }

        function resetConversation() {
            // Remove user/assistant messages but keep welcome.
            var wrap = messagesNode();
            if (wrap) {
                wrap.querySelectorAll('.ho-message:not(.ho-message-welcome)').forEach(function (m) { m.remove(); });
            }
            // Reload panel to restore starter sections.
            ensurePanelLoaded(true);
        }

        function appendUserMessage(message) {
            var wrap = messagesNode();
            if (!wrap) return;

            var article = document.createElement('article');
            article.className = 'ho-message ho-message-user';
            article.innerHTML = '<div class="ho-message-card"><div class="ho-message-body"></div></div>';
            article.querySelector('.ho-message-body').textContent = message;
            wrap.appendChild(article);
            scrollMessages();
        }

        function appendTyping() {
            var wrap = messagesNode();
            if (!wrap) return null;

            var article = document.createElement('article');
            article.className = 'ho-message ho-message-assistant ho-message-typing';
            article.innerHTML = '<div class="ho-message-avatar" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></div>'
                + '<div class="ho-message-card"><div class="ho-typing"><span></span><span></span><span></span></div></div>';
            wrap.appendChild(article);
            scrollMessages();
            return article;
        }

        function renderRelated(related) {
            if (!Array.isArray(related) || related.length === 0) {
                return '';
            }

            return '<div class="mt-3 ho-related-block">'
                + '<div class="ho-section-eyebrow"><i class="fa-solid fa-link" aria-hidden="true"></i><span>' + t('js.helponline.related', 'Correlati') + '</span></div>'
                + related.map(function (item) {
                    return '<a class="ho-topic mb-1" href="' + escHtml(item.url) + '">'
                        + '<div class="ho-topic-icon"><i class="fa-solid fa-arrow-right"></i></div>'
                        + '<div class="ho-topic-text">'
                        + '<div class="ho-topic-title">' + escHtml(item.title) + '</div>'
                        + '<div class="ho-topic-sub">' + escHtml(item.excerpt || '') + '</div>'
                        + '</div>'
                        + '<i class="fa-solid fa-chevron-right ho-topic-chevron" aria-hidden="true"></i>'
                        + '</a>';
                }).join('')
                + '</div>';
        }

        function appendAssistantMessage(payload, queryId, related) {
            var wrap = messagesNode();
            if (!wrap || !payload) return;

            var article = document.createElement('article');
            article.className = 'ho-message ho-message-assistant';

            var actions = '';
            if (payload.targetUrl) {
                actions += '<a href="' + escHtml(payload.targetUrl) + '" class="btn btn-sm btn-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>' + t('js.helponline.open_module', 'Apri modulo') + '</a>';
            }
            if (payload.openUrl) {
                actions += '<a href="' + escHtml(payload.openUrl) + '" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-book me-1" aria-hidden="true"></i>' + t('js.helponline.open_full_guide', 'Guida completa') + '</a>';
            }

            var feedback = '';
            if (queryId) {
                var helpfulLabel = t('js.helponline.helpful_aria', 'Risposta utile');
                var notHelpfulLabel = t('js.helponline.not_helpful_aria', 'Risposta non utile');
                feedback = '<div class="ho-feedback-row" role="group" aria-label="' + t('js.helponline.rate_answer_aria', 'Valuta la risposta') + '">'
                    + '<span class="small text-muted me-auto">' + t('js.helponline.was_helpful', 'Ti è stata utile?') + '</span>'
                    + '<button type="button" class="btn btn-sm btn-outline-success" data-ho-feedback="1" data-ho-query-id="' + escHtml(queryId) + '" aria-label="' + helpfulLabel + '" title="' + helpfulLabel + '"><i class="fa-solid fa-thumbs-up" aria-hidden="true"></i></button>'
                    + '<button type="button" class="btn btn-sm btn-outline-danger" data-ho-feedback="0" data-ho-query-id="' + escHtml(queryId) + '" aria-label="' + notHelpfulLabel + '" title="' + notHelpfulLabel + '"><i class="fa-solid fa-thumbs-down" aria-hidden="true"></i></button>'
                    + '</div>';
            }

            var confidence = parseInt(payload.confidence, 10);
            var confidenceBadge = '';
            if (!isNaN(confidence) && confidence > 0) {
                var tone = confidence >= 60 ? 'success' : (confidence >= 30 ? 'warning' : 'secondary');
                confidenceBadge = '<span class="badge text-bg-' + tone + '" title="' + t('js.helponline.confidence_title', 'Confidenza della risposta') + '">' + confidence + '%</span>';
            }

            article.innerHTML = '<div class="ho-message-avatar" aria-hidden="true"><i class="fa-solid fa-circle-question"></i></div>'
                + '<div class="ho-message-card">'
                + '<div class="d-flex justify-content-between gap-2 align-items-start">'
                + '<div class="ho-message-title">' + escHtml(payload.title) + '</div>'
                + confidenceBadge
                + '</div>'
                + '<div class="ho-message-body">' + (payload.html || '') + '</div>'
                + (actions ? '<div class="d-flex flex-wrap gap-2 mt-3">' + actions + '</div>' : '')
                + renderRelated(related)
                + feedback
                + '</div>';
            wrap.appendChild(article);
            scrollMessages();
        }

        function setSubmitDisabled(disabled) {
            var btn = host.querySelector('#ho-chat-form .ho-chat-send');
            if (btn) {
                btn.disabled = !!disabled;
            }
        }

        function sendMessage(message, chunk) {
            if (!message || state.pending) {
                return;
            }

            state.pending = true;
            setSubmitDisabled(true);
            clearStarterSections();
            appendUserMessage(message);
            var typingNode = appendTyping();

            request(root.dataset.askUrl, new URLSearchParams({
                message: message,
                chunk: chunk ? String(chunk) : '',
                context_path: root.dataset.currentPath || window.location.pathname || '/',
                page_title: root.dataset.pageTitle || document.title || ''
            }).toString(), function (json, ok) {
                state.pending = false;
                if (typingNode && typingNode.parentNode) {
                    typingNode.parentNode.removeChild(typingNode);
                }

                if (!json || json.ok !== true) {
                    notify((json && json.message) || t('js.helponline.answer_unavailable', 'Risposta non disponibile.'), 'warning');
                    setSubmitDisabled(true);
                    return;
                }

                appendAssistantMessage(json.answer, json.queryId, json.related || []);

                var input = host.querySelector('#ho-chat-input');
                if (input) {
                    input.value = '';
                    autosize(input);
                    updateCounter();
                    input.focus();
                }
            });
        }

        offcanvasEl.addEventListener('show.bs.offcanvas', function () {
            ensurePanelLoaded(false);
        });

        // Header icon opens the offcanvas (it carries a tooltip, not data-bs-toggle="offcanvas").
        var headerBtn = document.getElementById('ho-launcher-header-btn');
        if (headerBtn) {
            headerBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var tooltip = bootstrap.Tooltip.getInstance(headerBtn);
                if (tooltip) { tooltip.hide(); }
                offcanvas.show();
            });
        }

        document.addEventListener('submit', function (event) {
            if (!event.target || event.target.id !== 'ho-chat-form') {
                return;
            }

            event.preventDefault();
            var input = event.target.querySelector('#ho-chat-input');
            var message = input ? input.value.trim() : '';
            if (message === '') {
                return;
            }

            sendMessage(message);
        });

        // Enter to send, Shift+Enter for newline.
        document.addEventListener('keydown', function (event) {
            var target = event.target;
            if (target && target.id === 'ho-chat-input' && event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                var form = target.closest('form');
                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                }
            }
        });

        document.addEventListener('click', function (event) {
            var resetBtn = event.target.closest('[data-ho-action="reset"]');
            if (resetBtn) {
                event.preventDefault();
                resetConversation();
                return;
            }

            var suggestion = event.target.closest('[data-ho-suggestion]');
            if (suggestion) {
                sendMessage(
                    suggestion.getAttribute('data-ho-suggestion') || '',
                    suggestion.getAttribute('data-ho-chunk') || ''
                );
                return;
            }

            var feedback = event.target.closest('[data-ho-feedback]');
            if (feedback) {
                var feedbackRow = feedback.closest('.ho-feedback-row');
                var queryId = feedback.getAttribute('data-ho-query-id');
                var clickedValue = feedback.getAttribute('data-ho-feedback') || '';
                var wasActive = feedback.classList.contains('active');
                // Toggle off when re-clicking the active vote, otherwise switch.
                var nextValue = wasActive ? '' : clickedValue;

                if (feedbackRow) {
                    feedbackRow.querySelectorAll('[data-ho-feedback]').forEach(function (btn) {
                        btn.classList.remove('active');
                    });
                    if (nextValue !== '') {
                        feedback.classList.add('active');
                    }
                }

                request(root.dataset.feedbackUrl, new URLSearchParams({
                    query_id: queryId || '',
                    helpful: nextValue
                }).toString(), function (json, ok) {
                    if (ok && json && json.ok) {
                        notify(nextValue === '' ? t('js.helponline.feedback_removed', 'Feedback rimosso.') : t('js.helponline.feedback_saved', 'Feedback registrato.'), 'success');
                    } else {
                        // Roll back optimistic UI.
                        if (feedbackRow) {
                            feedbackRow.querySelectorAll('[data-ho-feedback]').forEach(function (btn) {
                                btn.classList.remove('active');
                            });
                            if (wasActive) { feedback.classList.add('active'); }
                        }
                        notify((json && json.message) || t('js.helponline.feedback_not_saved', 'Feedback non registrato.'), 'warning');
                    }
                });
            }
        });

        // Global ? shortcut to toggle the panel; Esc closes from inside.
        document.addEventListener('keydown', function (event) {
            var target = event.target;
            var typing = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);

            if (event.key === '?' && !typing && !event.ctrlKey && !event.altKey && !event.metaKey) {
                event.preventDefault();
                if (offcanvasEl.classList.contains('show')) {
                    offcanvas.hide();
                } else {
                    offcanvas.show();
                    ensurePanelLoaded(false);
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHelpOnline);
    } else {
        initHelpOnline();
    }
})();
