/* global HTMX, bootstrap, Cropper */
(function () {
  'use strict';

  // ── Avatar Cropper (client-side crop → DataTransfer → file input) ─────────
  var cropperInstance = null;

  function notifyFeedback(message, type, options) {
    if (typeof window.notify === 'function') {
      window.notify(Object.assign({
        message: message,
        type: type || 'info',
        source: 'contatti-avatar'
      }, options || {}));
      return;
    }

    console.warn('[contatti-avatar]', message);
  }

  function initAvatarCropper() {
    document.querySelectorAll('input[data-avatar-crop]').forEach(function (input) {
      if (input._ctCropBound) return;
      input._ctCropBound = true;

      input.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;
        var file = this.files[0];

        // Validate
        if (file.size > 2 * 1024 * 1024) {
          notifyFeedback(t('js.contacts.avatar.too_large', 'L\'immagine non può superare 2 MB.'), 'warning', {
            title: t('js.contacts.avatar.invalid_title', 'File non valido'),
            channel: 'banner',
            duration: 9000
          });
          this.value = '';
          return;
        }
        if (!file.type.match(/^image\/(png|jpe?g|webp|gif)$/)) {
          notifyFeedback(t('js.contacts.avatar.unsupported_format', 'Formato immagine non supportato.'), 'warning', {
            title: t('js.contacts.avatar.unsupported_format_title', 'Formato non supportato'),
            channel: 'banner',
            duration: 9000
          });
          this.value = '';
          return;
        }

        var fileInput = this;
        var previewId = fileInput.dataset.avatarCrop;
        var reader = new FileReader();
        reader.onload = function (e) {
          openCropperModal(e.target.result, fileInput, previewId);
        };
        reader.readAsDataURL(file);
      });
    });
  }

  function openCropperModal(src, fileInput, previewId) {
    var modal = document.getElementById('pf-cropper-modal');
    var imgEl = document.getElementById('pf-cropper-image');
    if (!modal || !imgEl) {
      // Fallback: no cropper modal, just preview
      updateAvatarPreview(previewId, src);
      return;
    }

    // Destroy previous
    destroyCropper();

    imgEl.src = src;
    var bsModal = bootstrap.Modal.getOrCreateInstance(modal);

    // Clone save button to remove old listeners
    var saveBtn = document.getElementById('pf-cropper-save');
    if (saveBtn) {
      var newBtn = saveBtn.cloneNode(true);
      saveBtn.parentNode.replaceChild(newBtn, saveBtn);
      newBtn.addEventListener('click', function () {
        handleCropSave(fileInput, previewId, newBtn, bsModal);
      });
    }

    modal.addEventListener('shown.bs.modal', function onShown() {
      modal.removeEventListener('shown.bs.modal', onShown);
      if (cropperInstance) cropperInstance.destroy();
      cropperInstance = new Cropper(imgEl, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 0.9,
        restore: false,
        guides: false,
        center: true,
        highlight: false,
        cropBoxMovable: true,
        cropBoxResizable: true,
        toggleDragModeOnDblclick: false,
        minCropBoxWidth: 64,
        minCropBoxHeight: 64
      });
    });

    // Cleanup on close
    modal.addEventListener('hidden.bs.modal', function onHidden() {
      modal.removeEventListener('hidden.bs.modal', onHidden);
      destroyCropper();
    });

    // Init toolbar buttons
    modal.querySelectorAll('[data-cropper-action]').forEach(function (btn) {
      var newToolBtn = btn.cloneNode(true);
      btn.parentNode.replaceChild(newToolBtn, btn);
      newToolBtn.addEventListener('click', function () {
        if (!cropperInstance) return;
        var action = this.getAttribute('data-cropper-action');
        switch (action) {
          case 'rotate-left':  cropperInstance.rotate(-90); break;
          case 'rotate-right': cropperInstance.rotate(90);  break;
          case 'zoom-in':      cropperInstance.zoom(0.1);   break;
          case 'zoom-out':     cropperInstance.zoom(-0.1);  break;
          case 'reset':        cropperInstance.reset();      break;
        }
      });
    });

    bsModal.show();
  }

  function handleCropSave(fileInput, previewId, saveBtn, bsModal) {
    if (!cropperInstance) return;

    var originalHtml = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + t('js.contacts.avatar.cropping', 'Ritaglio...');
    saveBtn.disabled = true;

    var canvas = cropperInstance.getCroppedCanvas({
      width: 256,
      height: 256,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high'
    });

    canvas.toBlob(function (blob) {
      saveBtn.innerHTML = originalHtml;
      saveBtn.disabled = false;

      if (!blob) {
        notifyFeedback(t('js.contacts.avatar.crop_error', 'Errore nel ritaglio dell\'immagine.'), 'danger', {
          title: t('js.contacts.avatar.crop_error_title', 'Ritaglio non completato'),
          channel: 'banner',
          duration: 10000
        });
        return;
      }

      // Set cropped blob on file input via DataTransfer
      var dt = new DataTransfer();
      dt.items.add(new File([blob], 'avatar.png', { type: 'image/png' }));
      fileInput.files = dt.files;

      // Update preview using data URL (blob URLs can be revoked by GC)
      var reader = new FileReader();
      reader.onload = function () {
        updateAvatarPreview(previewId, reader.result);
        // Close modal after preview is set
        if (bsModal) bsModal.hide();
      };
      reader.readAsDataURL(blob);
    }, 'image/png');
  }

  function updateAvatarPreview(previewId, src) {
    var target = document.getElementById(previewId);
    if (!target) return;
    var img = target.querySelector('img') || document.createElement('img');
    img.src = src;
    target.innerHTML = '';
    target.appendChild(img);
    target.style.setProperty('--ct-avatar-bg', 'transparent');
  }

  function destroyCropper() {
    if (cropperInstance) {
      cropperInstance.destroy();
      cropperInstance = null;
    }
    var imgEl = document.getElementById('pf-cropper-image');
    if (imgEl) imgEl.src = '';
  }

  // ── Accordion form sections, submit validation, char counter, tag preview:
  //    gestiti globalmente dai componenti
  //      public/assets/js/components/form-sections.js    → AppFormSections
  //      public/assets/js/components/form-validation.js  → AppFormValidation
  //    Qui nessuna inizializzazione locale per evitare doppi binding.

  // ── Auto-fill ricorrenza titolo based on tipo ─────────────────────────────
  function initRicorrenzaTipo() {
    document.addEventListener('change', function (e) {
      if (!e.target.matches('[data-ric-tipo]')) return;
      var titoloInput = document.querySelector('[data-ric-titolo]');
      var annoRow     = document.querySelector('[data-ric-anno-row]');
      var annualeBox  = document.querySelector('[data-ric-annuale]');
      if (!titoloInput) return;

      var nomeContatto = document.querySelector('[data-contatto-nome]')?.textContent.trim() || '';
      var tipo         = e.target.value;

      // Auto-fill titolo solo se vuoto
      if (titoloInput.value.trim() === '') {
        var typeLabels = {
          compleanno: t('js.contacts.recurrence.type_birthday', 'Compleanno'),
          anniversario: t('js.contacts.recurrence.type_anniversary', 'Anniversario'),
          evento: ''
        };
        var titleTemplate = t('js.contacts.recurrence.title_template', ':type di :name');
        titoloInput.value = nomeContatto
          ? (typeLabels[tipo] ? titleTemplate.replace(':type', typeLabels[tipo]).replace(':name', nomeContatto) : '')
          : (typeLabels[tipo] || '');
      }

      // Mostra anno_riferimento solo per compleanno
      if (annoRow) annoRow.classList.toggle('d-none', tipo !== 'compleanno');

      // Compleanno sempre annuale
      if (annualeBox && tipo === 'compleanno') {
        annualeBox.checked  = true;
        annualeBox.disabled = true;
      } else if (annualeBox) {
        annualeBox.disabled = false;
      }
    });
  }

  // ── Tooltip Bootstrap re-init dopo HTMX swap ─────────────────────────────
  function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      if (!bootstrap.Tooltip.getInstance(el)) new bootstrap.Tooltip(el);
    });
  }

  document.body.addEventListener('htmx:afterSwap',  function () { initTooltips(); initRicorrenzaTipo(); });
  document.body.addEventListener('htmx:afterSettle', function () { initTooltips(); });

  // ── Categoria pill click → imposta filtro e invia HTMX ───────────────────
  document.addEventListener('click', function (e) {
    var pill = e.target.closest('.ct-cat-pill[data-cat-id]');
    if (!pill) return;
    e.preventDefault();

    // Toggle active
    document.querySelectorAll('.ct-cat-pill').forEach(function (p) { p.classList.remove('active'); });
    pill.classList.add('active');

    // Aggiorna hidden input categoria_id e triggera HTMX sull'input ricerca
    var catInput = document.querySelector('input[name="categoria_id"]');
    if (catInput) catInput.value = pill.dataset.catId === '0' ? '' : pill.dataset.catId;

    var searchInput = document.querySelector('[data-ct-search]');
    if (searchInput) {
      htmx.trigger(searchInput, 'change');
    }
  });

  // ── Toggle preferiti ──────────────────────────────────────────────────────
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-ct-preferiti-toggle]');
    if (!btn) return;
    btn.classList.toggle('active');

    var prefInput = document.querySelector('input[name="preferiti"]');
    if (prefInput) prefInput.value = btn.classList.contains('active') ? '1' : '';

    var searchInput = document.querySelector('[data-ct-search]');
    if (searchInput) htmx.trigger(searchInput, 'change');
  });

  // ── Sort pill click → imposta ordinamento e invia HTMX ────────────────────
  document.addEventListener('click', function (e) {
    var pill = e.target.closest('[data-ct-sort-col]');
    if (!pill) return;
    e.preventDefault();

    var sortInput = document.querySelector('input[name="sort"]');
    var dirInput  = document.querySelector('input[name="dir"]');

    var newCol     = pill.dataset.ctSortCol;
    var currentCol = sortInput ? sortInput.value : 'nome';
    var currentDir = dirInput  ? dirInput.value  : 'asc';

    // Stessa colonna → inverti direzione; nuova colonna → direzione di default
    var defaultDir = (newCol === 'created_at') ? 'desc' : 'asc';
    var newDir = (newCol === currentCol)
      ? (currentDir === 'asc' ? 'desc' : 'asc')
      : defaultDir;

    // Aggiorna hidden inputs
    if (sortInput) sortInput.value = newCol;
    if (dirInput)  dirInput.value  = newDir;

    // Aggiorna classe active
    document.querySelectorAll('[data-ct-sort-col]').forEach(function (p) {
      p.classList.remove('active');
      var icon = p.querySelector('.ct-sort-arrow');
      if (icon) icon.remove();
    });
    pill.classList.add('active');

    // Aggiorna freccia direzionale
    var arrow = document.createElement('i');
    arrow.className = 'fa-solid ' + (newDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') + ' ms-1 ct-sort-arrow';
    arrow.style.fontSize = '.7em';
    arrow.setAttribute('aria-hidden', 'true');
    pill.appendChild(arrow);

    var searchEl = document.querySelector('[data-ct-search]');
    if (searchEl) htmx.trigger(searchEl, 'change');
  });

  // ── Color picker a palette per categorie ─────────────────────────────────
  function initCatColorPicker() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.ct-cat-swatch-btn[data-color]');
      if (!btn) return;
      var picker = btn.closest('.ct-cat-color-picker');
      if (!picker) return;

      // Deseleziona tutti, seleziona il cliccato
      picker.querySelectorAll('.ct-cat-swatch-btn').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');

      // Aggiorna il valore hidden
      var hidden = picker.querySelector('.ct-cat-color-val');
      if (hidden) hidden.value = btn.dataset.color;

      // Aggiorna il swatch visivo nella riga (modifica inline)
      var row = btn.closest('.ct-cat-row');
      if (row) {
        var swatch = row.querySelector('.ct-cat-swatch');
        if (swatch) swatch.style.setProperty('--ct-cat-color', btn.dataset.color);
      }
    });
  }

  // ── Color picker nativo legacy (kept for backward compat) ─────────────────
  function initColorPickers() {
    document.querySelectorAll('.ct-color-picker').forEach(function (picker) {
      picker.addEventListener('input', function () {
        var swatch = document.querySelector('[data-color-preview="' + this.dataset.colorFor + '"]');
        if (swatch) swatch.style.background = this.value;
      });
    });
  }

  // ── Init on first load ────────────────────────────────────────────────────
  function init() {
    initAvatarCropper();
    initRicorrenzaTipo();
    initTooltips();
    initColorPickers();
    initCatColorPicker();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
