/**
 * Notifications — Web Push toggle (pagina Impostazioni notifiche).
 * Legge la configurazione dai data-attribute di #nts-push (chiave VAPID
 * pubblica, URL subscribe/unsubscribe) e dialoga con PushController via fetch
 * form-encoded + header X-CSRF-Token. Stringhe via window.t (js.php).
 */
(function () {
    'use strict';

    var root = document.getElementById('nts-push');
    if (!root) return;

    var t = window.t || function (key, fallback) { return fallback; };
    var statusEl = document.getElementById('nts-push-status');
    var devicesEl = document.getElementById('nts-push-devices');
    var enableBtn = document.getElementById('nts-push-enable');
    var disableBtn = document.getElementById('nts-push-disable');
    var vapidKey = root.getAttribute('data-vapid-key') || '';
    var subscribeUrl = root.getAttribute('data-subscribe-url') || '';
    var unsubscribeUrl = root.getAttribute('data-unsubscribe-url') || '';
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
        || window.navigator.standalone === true;

    function setStatus(text, cssClass) {
        statusEl.textContent = text;
        statusEl.className = 'small ' + (cssClass || 'text-secondary');
    }

    function show(el, visible) {
        el.classList.toggle('d-none', !visible);
    }

    function setDeviceCount(count) {
        count = parseInt(count, 10) || 0;
        root.setAttribute('data-device-count', String(count));
        if (count > 0) {
            devicesEl.textContent = t('js.push.devices', 'Dispositivi collegati: {n}').replace('{n}', String(count));
            show(devicesEl, true);
        } else {
            show(devicesEl, false);
        }
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; i++) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function post(url, subscription) {
        var body = new URLSearchParams();
        var json = subscription.toJSON ? subscription.toJSON() : subscription;
        body.set('endpoint', json.endpoint || '');
        if (json.keys) {
            body.set('p256dh', json.keys.p256dh || '');
            body.set('auth', json.keys.auth || '');
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: body,
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        });
    }

    function getRegistration() {
        // app.js registra il SW al load: ready attende che sia attivo.
        return navigator.serviceWorker.ready;
    }

    function refresh() {
        if (!supported) {
            // Su iOS il push esiste solo dentro la PWA installata (Safari 16.4+).
            if (isIos && !isStandalone) {
                setStatus(t('js.push.ios_hint', 'Su iPhone/iPad: prima installa Favilla (Condividi → Aggiungi alla schermata Home), poi attiva le notifiche da qui.'), 'text-warning');
            } else {
                setStatus(t('js.push.unsupported', 'Questo browser non supporta le notifiche push.'), 'text-secondary');
            }
            show(enableBtn, false);
            show(disableBtn, false);
            setDeviceCount(root.getAttribute('data-device-count'));
            return;
        }

        if (Notification.permission === 'denied') {
            setStatus(t('js.push.denied', 'Notifiche bloccate dal browser: sbloccale dalle impostazioni del sito.'), 'text-danger');
            show(enableBtn, false);
            show(disableBtn, false);
            setDeviceCount(root.getAttribute('data-device-count'));
            return;
        }

        getRegistration()
            .then(function (registration) { return registration.pushManager.getSubscription(); })
            .then(function (subscription) {
                setDeviceCount(root.getAttribute('data-device-count'));
                if (subscription) {
                    setStatus(t('js.push.active', 'Attive su questo dispositivo.'), 'text-success');
                    show(enableBtn, false);
                    show(disableBtn, true);
                } else {
                    setStatus(t('js.push.inactive', 'Non attive su questo dispositivo.'), 'text-secondary');
                    show(enableBtn, true);
                    show(disableBtn, false);
                }
            })
            .catch(function () {
                setStatus(t('js.push.error', 'Errore push: riprova.'), 'text-danger');
            });
    }

    function enable() {
        enableBtn.disabled = true;
        setStatus(t('js.push.enabling', 'Attivazione in corso…'), 'text-secondary');

        Notification.requestPermission()
            .then(function (permission) {
                if (permission !== 'granted') {
                    throw new Error('permission_denied');
                }
                return getRegistration();
            })
            .then(function (registration) {
                // Se esiste già una subscription (es. creata con una vecchia
                // applicationServerKey dopo una rotazione delle chiavi VAPID),
                // disiscrivila prima: subscribe() con una chiave diversa
                // lancerebbe InvalidStateError.
                return registration.pushManager.getSubscription().then(function (existing) {
                    if (!existing) {
                        return registration;
                    }
                    return existing.unsubscribe()
                        .catch(function () { return null; })
                        .then(function () { return registration; });
                });
            })
            .then(function (registration) {
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey),
                });
            })
            .then(function (subscription) {
                return post(subscribeUrl, subscription).then(function (data) {
                    if (data && typeof data.device_count !== 'undefined') {
                        root.setAttribute('data-device-count', String(data.device_count));
                    }
                });
            })
            .then(function () {
                root.classList.add('nts-tg-bar--linked');
                refresh();
            })
            .catch(function (err) {
                if (err && err.message === 'permission_denied') {
                    refresh();
                } else {
                    console.warn('[Favilla] Push subscribe fallita:', err);
                    setStatus(t('js.push.error', 'Errore push: riprova.'), 'text-danger');
                    show(enableBtn, true);
                }
            })
            .finally(function () {
                enableBtn.disabled = false;
            });
    }

    function disable() {
        disableBtn.disabled = true;

        getRegistration()
            .then(function (registration) { return registration.pushManager.getSubscription(); })
            .then(function (subscription) {
                if (!subscription) return null;
                return post(unsubscribeUrl, subscription).then(function (data) {
                    if (data && typeof data.device_count !== 'undefined') {
                        root.setAttribute('data-device-count', String(data.device_count));
                    }
                    return subscription.unsubscribe();
                });
            })
            .then(function () {
                root.classList.remove('nts-tg-bar--linked');
                refresh();
            })
            .catch(function (err) {
                console.warn('[Favilla] Push unsubscribe fallita:', err);
                setStatus(t('js.push.error', 'Errore push: riprova.'), 'text-danger');
            })
            .finally(function () {
                disableBtn.disabled = false;
            });
    }

    enableBtn.addEventListener('click', enable);
    disableBtn.addEventListener('click', disable);

    refresh();
}());
