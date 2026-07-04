/**
 * Reports Module — general JS
 */
(function() {
    'use strict';

    // ── Reinit Bootstrap tooltips after HTMX swaps ────────────────
    document.body.addEventListener('htmx:afterSwap', function() {
        var tooltipEls = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipEls.forEach(function(el) {
            new bootstrap.Tooltip(el);
        });
    });

    // ── Color swatch preview in style forms ───────────────────────
    document.querySelectorAll('.rp-color-input').forEach(function(input) {
        var swatch = document.querySelector(input.dataset.swatch);
        if (swatch) {
            input.addEventListener('input', function() {
                swatch.style.backgroundColor = this.value;
            });
        }
    });

})();
