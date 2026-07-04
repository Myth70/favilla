/**
 * Reports Wizard — guided creation flow.
 * - Keeps the visual "is-selected" state in sync with radio inputs
 * - Auto-submits the form when a module/source is picked in step 2
 */
(function () {
    'use strict';

    function initWizard() {
        var form = document.getElementById('wizard-form');
        if (!form) return;

        // Sync visual selection when a radio changes.
        form.addEventListener('change', function (ev) {
            var target = ev.target;
            if (!target || target.type !== 'radio') return;

            var name = target.name;
            form.querySelectorAll('input[type="radio"][name="' + name + '"]').forEach(function (r) {
                var card = r.closest('.rp-choice-card');
                if (card) card.classList.toggle('is-selected', r.checked);
            });

            if (target.dataset.rpAutosubmit !== undefined) {
                // If picking a module, reset source_key so the next page shows sources.
                if (name === 'module') {
                    var src = form.querySelector('input[name="source_key"]');
                    if (src) src.value = '';
                }
                form.submit();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWizard);
    } else {
        initWizard();
    }
})();
