/**
 * Form Validation & UX — componenti condivisi per form entity (create/edit).
 *
 * Aggancia automaticamente i seguenti comportamenti a qualsiasi <form data-app-form>:
 *   • Submit intercept: blocca se !checkValidity(), applica .was-validated,
 *     espande le sezioni contenenti campi invalidi, scrolla al primo e focus.
 *   • Scroll + focus automatico se in pagina è presente #app-form-errors-summary
 *     (renderizzato server-side quando ci sono errori Validator).
 *
 * Bonus utility attivate via data-attributes, utilizzabili anche fuori da form:
 *   • [data-char-counter="id-target"] su input/textarea → aggiorna testuale
 *     "len / maxlength" nell'elemento target, aggiungendo .is-near-limit /
 *     .is-over-limit. Richiede maxlength per il conteggio "/max".
 *   • [data-tag-preview="id-target"] su input text → renderizza chip Bootstrap
 *     splittando su virgola.
 *
 * Reinit dopo htmx:afterSwap. Idempotente via flag _appFormBound / _charCounterBound
 * / _tagPreviewBound per evitare doppi listener.
 */
(function () {
  'use strict';

  function initFormValidation(root) {
    root = root || document;
    root.querySelectorAll('form[data-app-form]').forEach(function (form) {
      if (form._appFormBound) return;
      form._appFormBound = true;

      form.addEventListener('submit', function (e) {
        if (!form.checkValidity()) {
          e.preventDefault();
          e.stopPropagation();
          form.classList.add('was-validated');
          if (window.AppFormSections && window.AppFormSections.expandWithInvalid) {
            window.AppFormSections.expandWithInvalid(form);
          }
          var firstInvalid = form.querySelector(':invalid, .is-invalid');
          if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(function () {
              try { firstInvalid.focus({ preventScroll: true }); } catch (_) {}
            }, 200);
          }
        } else {
          form.classList.add('was-validated');
        }
      });
    });

    // Se c'è un summary server-side, porta l'utente lì ed espandi le sezioni
    var summary = root.querySelector ? root.querySelector('#app-form-errors-summary') : null;
    if (summary && !summary._appSummaryHandled) {
      summary._appSummaryHandled = true;
      summary.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (window.AppFormSections && window.AppFormSections.expandWithInvalid) {
        window.AppFormSections.expandWithInvalid(document);
      }
      var firstServerInvalid = document.querySelector('.is-invalid');
      if (firstServerInvalid) {
        setTimeout(function () {
          try { firstServerInvalid.focus({ preventScroll: true }); } catch (_) {}
        }, 250);
      }
    }
  }

  function initCharCounters(root) {
    root = root || document;
    root.querySelectorAll('[data-char-counter]').forEach(function (input) {
      if (input._charCounterBound) return;
      input._charCounterBound = true;
      var target = document.getElementById(input.dataset.charCounter);
      if (!target) return;
      var max = parseInt(input.getAttribute('maxlength') || '0', 10);
      var update = function () {
        var len = (input.value || '').length;
        target.textContent = max > 0 ? (len + ' / ' + max) : String(len);
        target.classList.toggle('is-near-limit', max > 0 && len >= max * 0.9 && len < max);
        target.classList.toggle('is-over-limit', max > 0 && len >= max);
      };
      input.addEventListener('input', update);
      update();
    });
  }

  function initTagPreview(root) {
    root = root || document;
    root.querySelectorAll('[data-tag-preview]').forEach(function (input) {
      if (input._tagPreviewBound) return;
      input._tagPreviewBound = true;
      var target = document.getElementById(input.dataset.tagPreview);
      if (!target) return;
      var render = function () {
        target.innerHTML = '';
        (input.value || '')
          .split(',')
          .map(function (s) { return s.trim(); })
          .filter(Boolean)
          .forEach(function (tag) {
            var span = document.createElement('span');
            span.className = 'badge text-bg-secondary';
            span.textContent = tag;
            target.appendChild(span);
          });
      };
      input.addEventListener('input', render);
      render();
    });
  }

  function initAll(root) {
    initFormValidation(root);
    initCharCounters(root);
    initTagPreview(root);
  }

  window.AppFormValidation = { init: initAll };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { initAll(document); });
  } else {
    initAll(document);
  }

  document.addEventListener('htmx:afterSwap', function (e) { initAll(e.target || document); });
})();
