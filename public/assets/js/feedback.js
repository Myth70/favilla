/**
 * Segnalazioni — cattura contestuale + launcher + console admin.
 *
 * Caricato globalmente (via launcher partial) quando il modulo è attivo e
 * l'utente è loggato. Un collector "early" inline (nel layout) installa il
 * buffer errori prima di questo file; qui lo riusiamo ed estendiamo con HTMX,
 * breadcrumb interazioni, snapshot DOM e invio.
 */
(function () {
    'use strict';

    var MAX_ERRORS = 30;
    var MAX_BREADCRUMB = 30;
    var MAX_DOM_CHARS = 500000;

    // ── Ring buffer (riusa quello dell'early collector se presente) ──────
    var buffer = (window.__sgBuffer = window.__sgBuffer || { errors: [], breadcrumb: [] });

    function ts() {
        return new Date().toISOString();
    }

    function pushCapped(arr, item, max) {
        arr.push(item);
        if (arr.length > max) {
            arr.splice(0, arr.length - max);
        }
    }

    function recordError(item) {
        item.ts = ts();
        pushCapped(buffer.errors, item, MAX_ERRORS);
    }

    function recordCrumb(item) {
        item.ts = ts();
        pushCapped(buffer.breadcrumb, item, MAX_BREADCRUMB);
    }

    // Errori JS / promise: solo se l'early collector non li ha già installati.
    if (!buffer.earlyInstalled) {
        window.addEventListener('error', function (e) {
            if (!e) return;
            recordError({
                type: 'js',
                message: String(e.message || (e.error && e.error.message) || t('js.issue_report.default_js_error', 'Errore script')),
                source: String(e.filename || ''),
                line: e.lineno || null,
                col: e.colno || null,
                stack: e.error && e.error.stack ? String(e.error.stack).slice(0, 2000) : ''
            });
        });
        window.addEventListener('unhandledrejection', function (e) {
            var reason = e && e.reason;
            recordError({
                type: 'js',
                message: t('js.issue_report.unhandled_rejection_prefix', 'Promise non gestita: ') + (reason && reason.message ? reason.message : String(reason)),
                source: '',
                stack: reason && reason.stack ? String(reason.stack).slice(0, 2000) : ''
            });
        });
    }

    // Errori e richieste HTMX
    function htmxInfo(e) {
        var d = e && e.detail ? e.detail : {};
        var path = (d.requestConfig && d.requestConfig.path) || (d.pathInfo && d.pathInfo.requestPath) || '';
        var verb = (d.requestConfig && d.requestConfig.verb) || '';
        var status = d.xhr ? d.xhr.status : null;
        return { path: String(path || ''), verb: String(verb || ''), status: status };
    }

    document.addEventListener('htmx:afterRequest', function (e) {
        var info = htmxInfo(e);
        if (!info.path) return;
        recordCrumb({ kind: 'htmx', verb: info.verb, path: info.path, status: info.status });
    });

    document.addEventListener('htmx:responseError', function (e) {
        var info = htmxInfo(e);
        recordError({ type: 'htmx', verb: info.verb, path: info.path, status: info.status });
    });

    document.addEventListener('htmx:sendError', function (e) {
        var info = htmxInfo(e);
        recordError({ type: 'htmx', verb: info.verb, path: info.path, status: 'send-error' });
    });

    document.addEventListener('htmx:timeout', function (e) {
        var info = htmxInfo(e);
        recordError({ type: 'htmx', verb: info.verb, path: info.path, status: 'timeout' });
    });

    // Breadcrumb dei click significativi
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest
            ? e.target.closest('a, button, [role="button"], [data-bs-toggle], input[type="submit"]')
            : null;
        if (!el) return;
        var sel = el.tagName.toLowerCase();
        if (el.id) {
            sel += '#' + el.id;
        } else if (el.classList && el.classList.length) {
            sel += '.' + el.classList[0];
        }
        var label = (el.getAttribute('aria-label') || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40);
        recordCrumb({ kind: 'click', target: sel + (label ? ' "' + label + '"' : '') });
    }, true);

    // Navigazione iniziale
    recordCrumb({ kind: 'nav', path: window.location.pathname });

    // ── Raccolta contesto ────────────────────────────────────────────────
    function themeState() {
        try {
            if (window.FavillaTheme && typeof window.FavillaTheme.getState === 'function') {
                return window.FavillaTheme.getState();
            }
        } catch (err) { /* ignore */ }
        return null;
    }

    function currentRoute() {
        var root = document.getElementById('sg-root');
        return (root && root.dataset.currentRoute) ? root.dataset.currentRoute : '';
    }

    function collectContext() {
        var dpr = window.devicePixelRatio || 1;
        return {
            url: window.location.href,
            path: window.location.pathname,
            route_name: currentRoute(),
            page_title: document.title || '',
            referrer: document.referrer || '',
            user_agent: navigator.userAgent || '',
            language: navigator.language || '',
            viewport: { w: window.innerWidth, h: window.innerHeight, dpr: dpr },
            viewport_str: window.innerWidth + 'x' + window.innerHeight + '@' + dpr,
            screen: { w: (window.screen && window.screen.width) || null, h: (window.screen && window.screen.height) || null },
            theme: themeState(),
            timestamp_client: ts(),
            errors: buffer.errors.slice(),
            breadcrumb: buffer.breadcrumb.slice()
        };
    }

    /**
     * Snapshot DOM sanitizzato della pagina (sostituisce lo screenshot):
     * rimuove script/widget propri/CSRF e maschera i valori dei campi.
     */
    function captureDom() {
        try {
            var root = document.documentElement;
            if (!root) return '';
            var clone = root.cloneNode(true);

            clone.querySelectorAll('script, noscript, #sg-offcanvas, #sg-root, .toast-container').forEach(function (n) { n.remove(); });
            clone.querySelectorAll('meta[name="csrf-token"], input[name="_token"]').forEach(function (n) { n.remove(); });

            clone.querySelectorAll('input').forEach(function (el) {
                var t = (el.getAttribute('type') || 'text').toLowerCase();
                if (t === 'checkbox' || t === 'radio' || t === 'submit' || t === 'button') return;
                if (t === 'password' || t === 'hidden') { el.setAttribute('value', ''); return; }
                if (el.getAttribute('value')) { el.setAttribute('value', '•••'); }
            });
            clone.querySelectorAll('textarea').forEach(function (el) {
                if (el.textContent && el.textContent.trim() !== '') { el.textContent = '•••'; }
            });

            var out = '<!doctype html>\n<!-- Snapshot DOM Segnalazioni: input mascherati, script rimossi -->\n' + clone.outerHTML;
            if (out.length > MAX_DOM_CHARS) {
                out = out.slice(0, MAX_DOM_CHARS) + '\n<!-- [troncato] -->';
            }
            return out;
        } catch (err) {
            return '';
        }
    }

    // ── Helpers UI ─────────────────────────────────────────────────────────
    function csrfToken(form) {
        var hidden = form && form.querySelector('input[name="_token"]');
        if (hidden && hidden.value) return hidden.value;
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function notify(message, type, opts) {
        if (typeof window.notify === 'function') {
            var payload = { message: message, type: type || 'info', channel: 'toast' };
            if (opts) { Object.assign(payload, opts); }
            window.notify(payload);
        }
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }

    // ── Console admin: copia per LLM ────────────────────────────────────────
    function initCopyButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-sg-copy]') : null;
            if (!btn) return;
            e.preventDefault();
            var target = document.querySelector(btn.getAttribute('data-sg-copy'));
            if (!target) return;
            var text = 'value' in target ? target.value : target.textContent;

            var done = function () { notify(t('js.issue_report.copied', 'Report copiato negli appunti. Incollalo pure nel tuo assistente.'), 'success'); };
            var fail = function () { notify(t('js.issue_report.copy_failed', 'Copia non riuscita. Seleziona e copia manualmente.'), 'warning'); };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(fail);
            } else {
                try {
                    target.removeAttribute('hidden');
                    target.classList.remove('d-none');
                    target.select();
                    document.execCommand('copy');
                    done();
                } catch (err) { fail(); }
            }
        });
    }

    // ── Launcher (offcanvas) ────────────────────────────────────────────────
    function initLauncher() {
        var root = document.getElementById('sg-root');
        var offcanvasEl = document.getElementById('sg-offcanvas');
        var form = document.getElementById('sg-form');
        var launchBtn = document.getElementById('sg-launcher-btn');
        if (!root || !offcanvasEl || !form || typeof bootstrap === 'undefined') {
            return;
        }

        var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);

        if (launchBtn) {
            launchBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var tooltip = bootstrap.Tooltip.getInstance(launchBtn);
                if (tooltip) { tooltip.hide(); }
                offcanvas.show();
            });
        }

        offcanvasEl.addEventListener('shown.bs.offcanvas', function () {
            var summary = document.getElementById('sg-context-summary');
            if (summary) {
                summary.innerHTML =
                    '<div><strong>' + t('js.issue_report.summary.page', 'Pagina:') + '</strong> ' + escHtml(root.dataset.pageTitle || document.title) + '</div>' +
                    '<div><strong>' + t('js.issue_report.summary.url', 'URL:') + '</strong> <span class="text-break">' + escHtml(window.location.pathname) + '</span></div>' +
                    '<div><strong>' + t('js.issue_report.summary.errors', 'Errori catturati:') + '</strong> ' + buffer.errors.length + '</div>' +
                    '<div><strong>' + t('js.issue_report.summary.actions', 'Azioni registrate:') + '</strong> ' + buffer.breadcrumb.length + '</div>' +
                    '<div><strong>' + t('js.issue_report.summary.dom', 'Snapshot DOM:') + '</strong> ' + t('js.issue_report.summary.dom_included', 'incluso (campi mascherati)') + '</div>';
            }
            var firstField = document.getElementById('sg-descrizione');
            if (firstField) { setTimeout(function () { firstField.focus(); }, 80); }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var descrizione = document.getElementById('sg-descrizione');
            if (!descrizione || descrizione.value.trim() === '') {
                if (descrizione) { descrizione.classList.add('is-invalid'); descrizione.focus(); }
                return;
            }
            descrizione.classList.remove('is-invalid');

            var submitBtn = document.getElementById('sg-submit');
            if (submitBtn) { submitBtn.disabled = true; }

            var body = new URLSearchParams({
                _token: csrfToken(form),
                tipo: (document.getElementById('sg-tipo') || {}).value || 'bug',
                severita: (document.getElementById('sg-severita') || {}).value || 'media',
                titolo: (document.getElementById('sg-titolo') || {}).value || '',
                descrizione: descrizione.value,
                passi: (document.getElementById('sg-passi') || {}).value || '',
                contesto: JSON.stringify(collectContext()),
                dom: captureDom()
            });

            fetch(root.dataset.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': csrfToken(form),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { json: json, ok: response.ok };
                }).catch(function () {
                    return { json: null, ok: response.ok };
                });
            }).then(function (result) {
                if (submitBtn) { submitBtn.disabled = false; }
                if (result.ok && result.json && result.json.ok) {
                    offcanvas.hide();
                    form.reset();
                    notify((result.json.message) || t('js.issue_report.sent', 'Segnalazione inviata. Grazie!'), 'success', { duration: 6000 });
                } else {
                    notify((result.json && result.json.message) || t('js.issue_report.send_failed', 'Invio non riuscito. Riprova.'), 'danger');
                }
            }).catch(function () {
                if (submitBtn) { submitBtn.disabled = false; }
                notify(t('js.issue_report.send_error', 'Impossibile inviare la segnalazione in questo momento.'), 'danger');
            });
        });
    }

    function init() {
        initLauncher();
        initCopyButtons();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
