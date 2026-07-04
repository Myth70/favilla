/**
 * radial-menu.js — Favilla Radial Context Menu
 * Dipendenze: nessuna (vanilla JS)
 * Requisito DOM: elemento #rm-root già presente nel layout
 */

(function () {
    'use strict';

    // ── Configurazione ──────────────────────────────────────────────────────
    const RADIUS  = 88;    // px dal punto di click
    const START_A = 200;   // angolo inizio ventaglio (gradi)
    const END_A   = 340;   // angolo fine ventaglio (gradi)
    // ────────────────────────────────────────────────────────────────────────

    const root = document.getElementById('rm-root');
    if (!root) return;

    // Le voci vengono lette dagli attributi data del root, iniettati dal PHP
    let items = [];
    try {
        items = JSON.parse(root.dataset.rmItems || '[]');
    } catch (e) {
        console.warn('[RadialMenu] Impossibile parsare data-rm-items', e);
        return;
    }

    // ── Build DOM items ──────────────────────────────────────────────────────
    items.forEach(function (item, i) {
        const total = items.length;
        const angle = total > 1
            ? START_A + (END_A - START_A) * (i / (total - 1))
            : (START_A + END_A) / 2;
        const rad = angle * Math.PI / 180;
        const tx  = Math.cos(rad) * RADIUS;
        const ty  = Math.sin(rad) * RADIUS;

        const wrap = document.createElement('div');
        wrap.className = 'rm-item';
        wrap.style.setProperty('--rm-tx', tx + 'px');
        wrap.style.setProperty('--rm-ty', ty + 'px');

        const btn = document.createElement('a');
        btn.className = 'rm-btn';
        btn.href      = item.url;
        btn.setAttribute('data-rm-tooltip', item.label);
        btn.innerHTML = '<i class="' + item.icon + '"></i>';

        wrap.appendChild(btn);
        root.appendChild(wrap);
    });

    // ── Open / Close ─────────────────────────────────────────────────────────
    function openMenu(x, y) {
        const margin = RADIUS + 60;
        const cx = Math.min(Math.max(x, margin), window.innerWidth  - margin);
        const cy = Math.min(Math.max(y, margin), window.innerHeight - margin);

        root.style.setProperty('--rm-ox', cx + 'px');
        root.style.setProperty('--rm-oy', cy + 'px');
        root.classList.add('rm-visible');
    }

    function closeMenu() {
        root.classList.remove('rm-visible');
    }

    // ── Context menu intercept ────────────────────────────────────────────────
    document.addEventListener('contextmenu', function (e) {
        const tag = (e.target.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag)) return;

        e.preventDefault();
        openMenu(e.clientX, e.clientY);
    });

    // ── Chiusura ──────────────────────────────────────────────────────────────
    document.addEventListener('mousedown', function (e) {
        if (root.classList.contains('rm-visible') && !root.contains(e.target)) {
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMenu();
    });

    const closeBtn = root.querySelector('.rm-close');
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);

})();
