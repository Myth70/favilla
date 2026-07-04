/**
 * Form Sections — accordion espandibili per form entity.
 *
 * Struttura markup:
 *   <fieldset class="app-form-section">
 *     <legend class="visually-hidden">Titolo</legend>
 *     <div class="app-form-section-header" role="button" tabindex="0"
 *          aria-expanded="true|false" aria-controls="id-body">
 *       …titolo + <i class="app-chevron"></i>
 *     </div>
 *     <div class="app-form-section-body [app-form-section-collapsed]" id="id-body">
 *       …campi
 *     </div>
 *   </fieldset>
 *
 * Toggle via click, Enter o Space sull'header. Idempotente: non riaggancia
 * listener se già inizializzato (flag dataset.appSectionInit).
 * Reinit automatico dopo htmx:afterSwap per sezioni caricate dinamicamente.
 *
 * Esposto anche window.AppFormSections.expandWithInvalid(form) per espandere
 * automaticamente le sezioni che contengono campi :invalid (usato da validazione).
 */
(function () {
  'use strict';

  function getBody(header) {
    var id = header.getAttribute('aria-controls');
    if (id) {
      var byId = document.getElementById(id);
      if (byId) return byId;
    }
    // Fallback: sibling successivo
    var sib = header.nextElementSibling;
    while (sib && !sib.classList.contains('app-form-section-body')) {
      sib = sib.nextElementSibling;
    }
    return sib;
  }

  function setOpen(header, open) {
    var body = getBody(header);
    header.setAttribute('aria-expanded', open ? 'true' : 'false');
    header.classList.toggle('open', open);
    if (body) body.classList.toggle('app-form-section-collapsed', !open);
  }

  function onHeaderActivate(header) {
    var isOpen = header.getAttribute('aria-expanded') === 'true'
              || header.classList.contains('open');
    setOpen(header, !isOpen);
  }

  function initHeader(header) {
    if (header.dataset.appSectionInit === '1') return;
    header.dataset.appSectionInit = '1';

    // Stato iniziale: se non ha aria-expanded e non ha .open, considera chiuso
    // se il body ha .app-form-section-collapsed.
    if (!header.hasAttribute('aria-expanded')) {
      var body = getBody(header);
      var collapsed = body && body.classList.contains('app-form-section-collapsed');
      header.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    header.addEventListener('click', function () { onHeaderActivate(header); });
    header.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
        e.preventDefault();
        onHeaderActivate(header);
      }
    });
  }

  function init(root) {
    root = root || document;
    if (!root.querySelectorAll) return;
    root.querySelectorAll('.app-form-section-header').forEach(initHeader);
  }

  /** Espandi tutte le sezioni il cui body contiene almeno un input :invalid. */
  function expandWithInvalid(scope) {
    scope = scope || document;
    scope.querySelectorAll('.app-form-section-body').forEach(function (body) {
      if (body.querySelector(':invalid, .is-invalid')) {
        var sectionEl = body.closest('.app-form-section');
        if (!sectionEl) return;
        var header = sectionEl.querySelector('.app-form-section-header');
        if (header) setOpen(header, true);
      }
    });
  }

  window.AppFormSections = {
    init: init,
    expandWithInvalid: expandWithInvalid
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }

  document.addEventListener('htmx:afterSwap', function (e) {
    init(e.target || document);
  });
})();
