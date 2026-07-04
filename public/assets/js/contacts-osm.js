/* global bootstrap */
(function () {
  'use strict';

  var FORM_ATTR = '[data-ct-osm-form]';
  var SHOW_PREVIEW_ATTR = '[data-ct-osm-preview]';
  var NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
  var SEARCH_TIMEOUT_MS = 4500;

  function setStatus(el, message, level) {
    if (!el) return;
    var cls = 'alert-secondary';
    if (level === 'success') cls = 'alert-success';
    if (level === 'warning') cls = 'alert-warning';
    if (level === 'danger') cls = 'alert-danger';
    el.className = 'alert py-2 px-3 small mt-2 mb-2 ' + cls;
    el.textContent = message;
    el.classList.remove('d-none');
  }

  function buildEmbedUrl(lat, lng) {
    var delta = 0.004;
    var left = (lng - delta).toFixed(6);
    var right = (lng + delta).toFixed(6);
    var top = (lat + delta).toFixed(6);
    var bottom = (lat - delta).toFixed(6);
    var bbox = [left, bottom, right, top].join(',');

    return 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox) + '&layer=mapnik&marker=' + encodeURIComponent(lat.toFixed(6) + ',' + lng.toFixed(6));
  }

  function renderPreview(previewEl, lat, lng) {
    if (!previewEl) return;

    previewEl.innerHTML = '';
    previewEl.classList.remove('d-none');

    var iframe = document.createElement('iframe');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
    iframe.setAttribute('title', t('js.contacts.osm.map_title', 'Mappa OpenStreetMap'));
    iframe.src = buildEmbedUrl(lat, lng);

    iframe.addEventListener('error', function () {
      previewEl.innerHTML = '<div class="small text-muted p-3">' + t('js.contacts.osm.map_unreachable', 'Mappa non raggiungibile al momento.') + '</div>';
    });

    previewEl.appendChild(iframe);
  }

  function clearGeo(latInput, lngInput, sourceInput, statusPill) {
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';
    if (sourceInput) sourceInput.value = 'manual';
    if (statusPill) statusPill.textContent = t('js.contacts.osm.geo_status_template', 'Geolocalizzazione: :status').replace(':status', t('js.contacts.osm.geo_manual', 'manuale'));
  }

  function selectResult(item, elements) {
    var lat = parseFloat(item.lat || '');
    var lng = parseFloat(item.lon || '');
    if (!isFinite(lat) || !isFinite(lng)) {
      setStatus(elements.status, t('js.contacts.osm.invalid_coords', 'Coordinate OSM non valide per il risultato selezionato.'), 'warning');
      return;
    }

    elements.programmaticUpdate = true;
    elements.address.value = item.display_name || elements.address.value;
    elements.programmaticUpdate = false;

    elements.lat.value = lat.toFixed(8);
    elements.lng.value = lng.toFixed(8);
    elements.source.value = 'osm';

    if (elements.statusPill) {
      elements.statusPill.textContent = t('js.contacts.osm.geo_status_template', 'Geolocalizzazione: :status').replace(':status', t('js.contacts.osm.geo_selected', 'selezionata da OpenStreetMap'));
    }

    renderPreview(elements.preview, lat, lng);
    setStatus(elements.status, t('js.contacts.osm.address_selected', 'Indirizzo selezionato da OpenStreetMap. Puoi comunque modificarlo manualmente.'), 'success');
  }

  function renderResults(items, elements) {
    elements.results.innerHTML = '';

    if (!items.length) {
      setStatus(elements.status, t('js.contacts.osm.no_results', 'Nessun risultato trovato. Inserisci l\'indirizzo manualmente.'), 'warning');
      return;
    }

    items.forEach(function (item) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'list-group-item list-group-item-action ct-osm-result-item';
      button.setAttribute('data-bs-toggle', 'tooltip');
      button.setAttribute('title', t('js.contacts.osm.use_address_tooltip', 'Usa questo indirizzo'));
      button.textContent = item.display_name || t('js.contacts.osm.no_description', 'Risultato senza descrizione');
      button.addEventListener('click', function () {
        selectResult(item, elements);
      });
      elements.results.appendChild(button);
    });

    if (window.bootstrap && bootstrap.Tooltip) {
      elements.results.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        if (!bootstrap.Tooltip.getInstance(el)) {
          new bootstrap.Tooltip(el);
        }
      });
    }
  }

  function fetchWithTimeout(url, timeoutMs) {
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timeoutId = null;

    if (controller) {
      timeoutId = setTimeout(function () {
        controller.abort();
      }, timeoutMs);
    }

    return fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Accept-Language': 'it'
      },
      signal: controller ? controller.signal : undefined
    }).finally(function () {
      if (timeoutId !== null) {
        clearTimeout(timeoutId);
      }
    });
  }

  function handleSearch(elements) {
    var query = elements.query.value.trim();
    if (!query) {
      setStatus(elements.status, t('js.contacts.osm.empty_query', 'Inserisci un indirizzo da cercare su OpenStreetMap.'), 'warning');
      return;
    }

    if (!window.fetch) {
      setStatus(elements.status, t('js.contacts.osm.fetch_unsupported', 'Ricerca mappa non supportata dal browser. Usa inserimento manuale.'), 'warning');
      clearGeo(elements.lat, elements.lng, elements.source, elements.statusPill);
      return;
    }

    if (navigator.onLine === false) {
      setStatus(elements.status, t('js.contacts.osm.offline_manual', 'Sei offline: continua con indirizzo manuale.'), 'warning');
      clearGeo(elements.lat, elements.lng, elements.source, elements.statusPill);
      return;
    }

    setStatus(elements.status, t('js.contacts.osm.searching', 'Ricerca in corso su OpenStreetMap...'), 'secondary');

    var url = NOMINATIM_URL + '?format=jsonv2&addressdetails=1&limit=6&q=' + encodeURIComponent(query);

    fetchWithTimeout(url, SEARCH_TIMEOUT_MS)
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (items) {
        renderResults(Array.isArray(items) ? items : [], elements);
      })
      .catch(function () {
        setStatus(elements.status, t('js.contacts.osm.unreachable_manual', 'OpenStreetMap non raggiungibile. Usa inserimento manuale.'), 'warning');
        clearGeo(elements.lat, elements.lng, elements.source, elements.statusPill);
      });
  }

  function initFormIntegration(root) {
    var panel = root.querySelector('#ct-osm-panel');
    var openBtn = root.querySelector('#ct-open-osm-panel');
    var closeBtn = root.querySelector('#ct-close-osm-panel');
    var searchBtn = root.querySelector('#ct-osm-search');
    var queryInput = root.querySelector('#ct-osm-query');
    var addressInput = root.querySelector('#ct-indirizzo');
    var latInput = root.querySelector('#ct-latitude');
    var lngInput = root.querySelector('#ct-longitude');
    var sourceInput = root.querySelector('#ct-geocoding-source');
    var statusEl = root.querySelector('#ct-osm-status');
    var resultsEl = root.querySelector('#ct-osm-results');
    var previewEl = root.querySelector('#ct-osm-preview');
    var statusPill = root.querySelector('[data-ct-geo-status-pill]');

    if (!panel || !openBtn || !searchBtn || !queryInput || !addressInput || !statusEl || !resultsEl || !previewEl) {
      return;
    }

    if (root._ctOsmBound) return;
    root._ctOsmBound = true;

    var elements = {
      panel: panel,
      query: queryInput,
      address: addressInput,
      lat: latInput,
      lng: lngInput,
      source: sourceInput,
      status: statusEl,
      results: resultsEl,
      preview: previewEl,
      statusPill: statusPill,
      programmaticUpdate: false
    };

    openBtn.addEventListener('click', function () {
      panel.classList.toggle('d-none');
      if (!panel.classList.contains('d-none') && !queryInput.value.trim()) {
        queryInput.value = addressInput.value.trim();
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        panel.classList.add('d-none');
      });
    }

    searchBtn.addEventListener('click', function () {
      handleSearch(elements);
    });

    queryInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        handleSearch(elements);
      }
    });

    addressInput.addEventListener('input', function () {
      if (elements.programmaticUpdate) return;
      clearGeo(latInput, lngInput, sourceInput, statusPill);
      previewEl.classList.add('d-none');
      previewEl.innerHTML = '';
    });

    var lat = parseFloat((latInput && latInput.value) || '');
    var lng = parseFloat((lngInput && lngInput.value) || '');
    if (isFinite(lat) && isFinite(lng)) {
      renderPreview(previewEl, lat, lng);
      if (statusPill) {
        statusPill.textContent = t('js.contacts.osm.geo_status_template', 'Geolocalizzazione: :status').replace(':status', t('js.contacts.osm.geo_available', 'disponibile'));
      }
    }
  }

  function initShowPreviews() {
    document.querySelectorAll(SHOW_PREVIEW_ATTR).forEach(function (container) {
      if (container._ctOsmBound) return;
      container._ctOsmBound = true;

      var lat = parseFloat(container.getAttribute('data-lat') || '');
      var lng = parseFloat(container.getAttribute('data-lng') || '');
      if (!isFinite(lat) || !isFinite(lng)) return;

      if (navigator.onLine === false) {
        container.innerHTML = '<div class="small text-muted p-3">' + t('js.contacts.osm.map_offline', 'Mappa non disponibile offline.') + '</div>';
        return;
      }

      renderPreview(container, lat, lng);
    });
  }

  function init() {
    var formRoot = document.querySelector(FORM_ATTR);
    if (formRoot) {
      initFormIntegration(formRoot);
    }
    initShowPreviews();
  }

  document.body.addEventListener('htmx:afterSwap', function () {
    init();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
