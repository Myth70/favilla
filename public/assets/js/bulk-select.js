/**
 * BulkSelect — reusable multi-row selection utility.
 *
 * Usage in a module view:
 *
 *   BulkSelect.init({
 *     checkboxSelector : '.my-item-check',   // individual row checkboxes
 *     selectAllId      : 'my-select-all',    // optional "select all" checkbox id
 *     countId          : 'my-bulk-count',    // optional element that shows selected count
 *     barId            : 'my-bulk-bar',      // optional bar/toolbar to show/hide (needs .active)
 *     activeClass      : 'active',           // class added to bar when selection > 0  (default 'active')
 *     formId           : 'my-bulk-form',     // optional form that collects ids[] on submit
 *     idsInputName     : 'ids[]',            // hidden input name for collected ids       (default 'ids[]')
 *     confirmMsg       : null,               // confirm() message before form submit, null = no confirm
 *     onSelectionChange: function(ids) {},   // optional callback with array of selected values
 *   });
 *
 * After HTMX swaps, call BulkSelect.reset() to clear selection state.
 *
 * Loaded via $view->pushScript('js/bulk-select.js') on any CRUD page that needs bulk ops.
 */
(function (global) {
    'use strict';

    var BulkSelect = {

        _cfg: null,
        _eventsBound: false,

        init: function (options) {
            this._cfg = Object.assign({
                checkboxSelector : '.bulk-check',
                selectAllId      : null,
                countId          : null,
                barId            : null,
                activeClass      : 'active',
                formId           : null,
                idsInputName     : 'ids[]',
                confirmMsg       : null,
                onSelectionChange: null,
            }, options || {});

            if (!this._eventsBound) {
                this._bindEvents();
                this._eventsBound = true;
            }
        },

        // ── Public ──────────────────────────────────────────────────────

        /** Return values of all currently checked boxes. */
        getSelectedIds: function () {
            var checked = document.querySelectorAll(this._cfg.checkboxSelector + ':checked');
            return Array.prototype.map.call(checked, function (cb) { return cb.value; });
        },

        /** Deselect all boxes and hide the bulk bar. */
        reset: function () {
            var cfg = this._cfg;
            if (!cfg) return;

            document.querySelectorAll(cfg.checkboxSelector).forEach(function (cb) {
                cb.checked = false;
            });
            var sa = cfg.selectAllId ? document.getElementById(cfg.selectAllId) : null;
            if (sa) { sa.checked = false; sa.indeterminate = false; }
            this._updateBar(0);
        },

        // ── Private ─────────────────────────────────────────────────────

        _bindEvents: function () {
            var self = this;

            // Checkbox changes (event delegation — works after HTMX swaps)
            document.addEventListener('change', function (e) {
                var cfg = self._cfg;
                if (!cfg) return;

                // "Select all" checkbox
                if (cfg.selectAllId && e.target.id === cfg.selectAllId) {
                    document.querySelectorAll(cfg.checkboxSelector).forEach(function (cb) {
                        cb.checked = e.target.checked;
                    });
                    self._refresh();
                    return;
                }

                // Individual row checkbox
                if (e.target.matches(cfg.checkboxSelector)) {
                    self._refresh();
                }
            });

            // Bulk form submit — collect ids, optional confirm
            document.addEventListener('submit', function (e) {
                var cfg = self._cfg;
                if (!cfg || !cfg.formId || e.target.id !== cfg.formId) return;
                e.preventDefault();

                var ids = self.getSelectedIds();
                if (ids.length === 0) return;

                var form = e.target;
                var doSubmit = function () {
                    // Clear any previously appended hidden inputs
                    form.querySelectorAll('input[name="' + cfg.idsInputName + '"]').forEach(function (el) {
                        el.remove();
                    });
                    ids.forEach(function (id) {
                        var inp   = document.createElement('input');
                        inp.type  = 'hidden';
                        inp.name  = cfg.idsInputName;
                        inp.value = id;
                        form.appendChild(inp);
                    });
                    form.submit();
                };

                if (cfg.confirmMsg) {
                    window.appConfirm(cfg.confirmMsg.replace('{n}', ids.length)).then(function (ok) {
                        if (ok) doSubmit();
                    });
                    return;
                }
                doSubmit();
            });

            // Reset state only when the swapped fragment contains this bulk table.
            document.addEventListener('htmx:afterSwap', function (e) {
                var cfg = self._cfg;
                if (!cfg) return;

                var target = e && e.detail ? e.detail.target : null;
                if (self._isRelevantSwapTarget(target, cfg)) {
                    self.reset();
                }
            });
        },

        _isRelevantSwapTarget: function (target, cfg) {
            if (!target || target.nodeType !== 1) return false;

            var hasRowChecks = target.matches(cfg.checkboxSelector) || !!target.querySelector(cfg.checkboxSelector);
            if (hasRowChecks) return true;

            if (cfg.selectAllId) {
                if (target.id === cfg.selectAllId || !!target.querySelector('#' + cfg.selectAllId)) return true;
            }

            if (cfg.formId) {
                if (target.id === cfg.formId || !!target.querySelector('#' + cfg.formId)) return true;
            }

            if (cfg.barId) {
                if (target.id === cfg.barId || !!target.querySelector('#' + cfg.barId)) return true;
            }

            return false;
        },

        _refresh: function () {
            var cfg    = this._cfg;
            var all    = document.querySelectorAll(cfg.checkboxSelector);
            var checked = document.querySelectorAll(cfg.checkboxSelector + ':checked');

            // Update "select all" indeterminate state
            var sa = cfg.selectAllId ? document.getElementById(cfg.selectAllId) : null;
            if (sa) {
                sa.checked       = all.length > 0 && all.length === checked.length;
                sa.indeterminate = checked.length > 0 && checked.length < all.length;
            }

            this._updateBar(checked.length);

            if (typeof cfg.onSelectionChange === 'function') {
                cfg.onSelectionChange(this.getSelectedIds());
            }
        },

        _updateBar: function (count) {
            var cfg    = this._cfg;
            var bar    = cfg.barId    ? document.getElementById(cfg.barId)    : null;
            var countEl = cfg.countId ? document.getElementById(cfg.countId)  : null;

            if (bar) {
                if (count > 0) {
                    bar.classList.add(cfg.activeClass);
                } else {
                    bar.classList.remove(cfg.activeClass);
                }
            }
            if (countEl) {
                countEl.textContent = count;
            }
        },
    };

    global.BulkSelect = BulkSelect;

})(window);
