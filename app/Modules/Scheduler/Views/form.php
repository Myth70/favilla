<?php
/**
 * Scheduler — form crea/modifica job.
 *
 * Variabili: $job (array|null), $isEdit (bool)
 */
$view->layout('main');
$view->pushStyle('css/scheduler.css');
?>
<?php $view->start('content'); ?>

<?php
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

// Valori del form: preferisce $_old (dopo redirect su errore), poi $job, poi default
$fName     = $old['name']             ?? ($job['name']             ?? '');
$fSlug     = $old['slug']             ?? ($job['slug']             ?? '');
$fCommand  = $old['command']          ?? ($job['command']          ?? '');
$fArgs     = $old['args_raw']         ?? ($job ? (implode("\n", json_decode($job['args_json'] ?? '[]', true) ?: [])) : '');
$fInterval = $old['interval_minutes'] ?? ($job['interval_minutes'] ?? 60);
$fEnabled  = isset($old['enabled'])   ? (bool)$old['enabled'] : (isset($job) ? (bool)($job['enabled'] ?? 1) : true);

$allowedCommands = is_array($allowedCommands ?? null) ? $allowedCommands : [];
if ($fCommand === '' && !empty($allowedCommands)) {
    $fCommand = (string) $allowedCommands[0];
}
$isCommandAllowed = in_array($fCommand, $allowedCommands, true);

$presets = [
    5    => t('scheduler.presets.m5'),
    10   => t('scheduler.presets.m10'),
    15   => t('scheduler.presets.m15'),
    30   => t('scheduler.presets.m30'),
    60   => t('scheduler.presets.h1'),
    120  => t('scheduler.presets.h2'),
    360  => t('scheduler.presets.h6'),
    720  => t('scheduler.presets.h12'),
    1440 => t('scheduler.presets.d1'),
    2880 => t('scheduler.presets.d2'),
    10080=> t('scheduler.presets.d7'),
];
$isCustomInterval = !array_key_exists((int)$fInterval, $presets);

$heroTitle = $isEdit ? t('scheduler.edit_prefix', ['name' => $job['name']]) : t('scheduler.new_job');

$adminButtons = '<a href="' . e(route('scheduler.index')) . '" class="btn btn-outline-secondary btn-sm">'
              . '<i class="fa-solid fa-arrow-left me-1"></i>' . e(t('common.action.cancel')) . '</a>';

$view->include('partials/pf-hero-admin', [
    'adminTitle'    => $heroTitle,
    'adminIcon'     => 'fa-solid fa-clock',
    'adminSubtitle' => $isEdit ? t('scheduler.form_subtitle_edit') : t('scheduler.form_subtitle_new'),
    'adminButtons'  => $adminButtons,
]);
?>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <?php if ($isEdit): ?>
            <form method="POST" action="<?= e(route('scheduler.update', ['id' => $job['id']])) ?>" novalidate data-app-form>
            <input type="hidden" name="_method" value="PUT">
        <?php else: ?>
            <form method="POST" action="<?= e(route('scheduler.store')) ?>" novalidate data-app-form>
        <?php endif; ?>
        <?= csrf_field() ?>

        <!-- Sezione: Identità -->
        <fieldset class="app-form-section">
            <legend class="visually-hidden"><?= e(t('scheduler.form.identity_legend')) ?></legend>
            <div class="app-form-section-header" role="button" tabindex="0"
                 aria-expanded="true" aria-controls="sch-identity-body">
                <span class="app-card-icon"><i class="fa-solid fa-tag"></i></span>
                <span class="fw-semibold flex-grow-1"><?= e(t('scheduler.form.identity')) ?></span>
                <i class="fa-solid fa-chevron-down app-chevron"></i>
            </div>
            <div class="app-form-section-body" id="sch-identity-body">
                <div class="mb-3">
                    <label for="sch-name" class="form-label fw-semibold"><?= e(t('scheduler.form.name')) ?> <span class="text-danger">*</span></label>
                    <input type="text" id="sch-name" name="name"
                           class="form-control" maxlength="255" required
                           aria-required="true"
                           aria-describedby="sch-name-feedback"
                           value="<?= e($fName) ?>"
                           placeholder="<?= e(t('scheduler.form.name_placeholder')) ?>">
                    <div id="sch-name-feedback" class="invalid-feedback"><?= e(t('scheduler.form.name_feedback')) ?></div>
                </div>

                <div class="mb-0">
                    <label for="sch-slug" class="form-label fw-semibold"><?= e(t('scheduler.form.slug')) ?> <span class="text-danger">*</span></label>
                    <input type="text" id="sch-slug" name="slug"
                           class="form-control font-monospace" maxlength="100" required
                           pattern="[a-z0-9][a-z0-9._\-]*[a-z0-9]|[a-z0-9]{1}"
                           aria-required="true"
                           aria-describedby="sch-slug-help sch-slug-feedback"
                           value="<?= e($fSlug) ?>"
                           placeholder="<?= e(t('scheduler.form.slug_placeholder')) ?>">
                    <div id="sch-slug-help" class="form-text"><?= e(t('scheduler.form.slug_help')) ?></div>
                    <div id="sch-slug-feedback" class="invalid-feedback"><?= e(t('scheduler.form.slug_feedback')) ?></div>
                </div>
            </div>
        </fieldset>

        <!-- Sezione: Esecuzione -->
        <fieldset class="app-form-section">
            <legend class="visually-hidden"><?= e(t('scheduler.form.execution_legend')) ?></legend>
            <div class="app-form-section-header" role="button" tabindex="0"
                 aria-expanded="true" aria-controls="sch-exec-body">
                <span class="app-card-icon"><i class="fa-solid fa-terminal"></i></span>
                <span class="fw-semibold flex-grow-1"><?= e(t('scheduler.form.execution')) ?></span>
                <i class="fa-solid fa-chevron-down app-chevron"></i>
            </div>
            <div class="app-form-section-body" id="sch-exec-body">
                <div class="mb-3">
                    <label for="sch-command" class="form-label fw-semibold"><?= e(t('scheduler.form.command')) ?> <span class="text-danger">*</span></label>
                    <select id="sch-command" name="command" class="form-select font-monospace" required
                            aria-required="true" aria-describedby="sch-command-help">
                        <?php if (!$isCommandAllowed && $fCommand !== ''): ?>
                            <option value="<?= e($fCommand) ?>" selected>
                                <?= e(t('scheduler.form.command_not_allowed', ['command' => $fCommand])) ?>
                            </option>
                        <?php endif; ?>
                        <?php foreach ($allowedCommands as $command): ?>
                            <option value="<?= e($command) ?>" <?= $fCommand === $command ? 'selected' : '' ?>>
                                <?= e($command) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="sch-command-help" class="form-text"><?= e(t('scheduler.form.command_help')) ?></div>
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold"><?= e(t('scheduler.form.args')) ?></label>
                    <div id="sch-args-list" class="d-flex flex-column gap-2">
                        <?php
                        $argLines = array_filter(array_map('trim', explode("\n", $fArgs)));
                        if (empty($argLines)) { $argLines = ['']; }
                        foreach ($argLines as $arg): ?>
                            <div class="input-group sch-arg-row">
                                <input type="text" class="form-control font-monospace sch-arg-input"
                                       placeholder="<?= e(t('scheduler.form.arg_placeholder')) ?>"
                                       value="<?= e($arg) ?>">
                                <button type="button" class="btn btn-outline-secondary sch-arg-remove"
                                        data-bs-toggle="tooltip" title="<?= e(t('scheduler.form.remove_arg')) ?>">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="sch-args-add" class="btn btn-outline-secondary btn-sm mt-2">
                        <i class="fa-solid fa-plus me-1"></i><?= e(t('scheduler.form.add_arg')) ?>
                    </button>
                    <input type="hidden" id="sch-args-raw" name="args_raw" value="<?= e($fArgs) ?>">
                    <div class="form-text"><?= e(t('scheduler.form.args_hint')) ?></div>
                </div>
            </div>
        </fieldset>

        <!-- Sezione: Pianificazione -->
        <fieldset class="app-form-section">
            <legend class="visually-hidden"><?= e(t('scheduler.form.planning_legend')) ?></legend>
            <div class="app-form-section-header" role="button" tabindex="0"
                 aria-expanded="true" aria-controls="sch-schedule-body">
                <span class="app-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                <span class="fw-semibold flex-grow-1"><?= e(t('scheduler.form.planning')) ?></span>
                <i class="fa-solid fa-chevron-down app-chevron"></i>
            </div>
            <div class="app-form-section-body" id="sch-schedule-body">
                <div class="mb-3">
                    <label for="sch-interval-select" class="form-label fw-semibold"><?= e(t('scheduler.form.interval')) ?> <span class="text-danger">*</span></label>
                    <select id="sch-interval-select" class="form-select">
                        <?php foreach ($presets as $mins => $label): ?>
                            <option value="<?= $mins ?>" <?= (!$isCustomInterval && (int)$fInterval === $mins) ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom" <?= $isCustomInterval ? 'selected' : '' ?>><?= e(t('scheduler.form.custom')) ?></option>
                    </select>
                    <div id="sch-interval-custom" class="mt-2 <?= $isCustomInterval ? '' : 'd-none' ?>">
                        <div class="input-group sch-input-group-md">
                            <input type="number" id="sch-interval-input" name="interval_minutes"
                                   class="form-control" min="1" max="525600"
                                   inputmode="numeric"
                                   value="<?= e($fInterval) ?>"
                                   placeholder="<?= e(t('scheduler.form.minutes_placeholder')) ?>">
                            <span class="input-group-text"><?= e(t('scheduler.form.min')) ?></span>
                        </div>
                    </div>
                    <input type="hidden" id="sch-interval-hidden" name="interval_minutes" value="<?= e($fInterval) ?>">
                </div>

                <div class="mb-0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="sch-enabled"
                               name="enabled" value="1"
                               <?= $fEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sch-enabled"><?= e(t('scheduler.form.enabled_label')) ?></label>
                    </div>
                    <div class="form-text"><?= e(t('scheduler.form.enabled_hint')) ?></div>
                </div>
            </div>
        </fieldset>

        <!-- Azioni -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="<?= e(route('scheduler.index')) ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i>
                <?= $isEdit ? e(t('scheduler.form.save_changes')) : e(t('scheduler.form.create_job')) ?>
            </button>
        </div>

        </form>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
(function () {
    'use strict';

    var ARG_PLACEHOLDER = <?= json_encode(t('scheduler.form.arg_placeholder'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var REMOVE_ARG = <?= json_encode(t('scheduler.form.remove_arg'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

    // ── Slug auto-generate da nome (solo create) ──────────────────────────────
    <?php if (!$isEdit): ?>
    var nameEl = document.getElementById('sch-name');
    var slugEl = document.getElementById('sch-slug');
    var slugTouched = <?= $fSlug !== '' ? 'true' : 'false' ?>;

    slugEl.addEventListener('input', function () { slugTouched = true; });

    nameEl.addEventListener('input', function () {
        if (slugTouched && slugEl.value !== '') return;
        slugEl.value = nameEl.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '.')
            .replace(/^\.+|\.+$/g, '');
        slugTouched = false;
    });
    <?php endif; ?>

    // ── Intervallo: select + custom ───────────────────────────────────────────
    var intervalSelect = document.getElementById('sch-interval-select');
    var intervalCustom = document.getElementById('sch-interval-custom');
    var intervalInput  = document.getElementById('sch-interval-input');
    var intervalHidden = document.getElementById('sch-interval-hidden');

    function syncInterval() {
        if (intervalSelect.value === 'custom') {
            intervalCustom.classList.remove('d-none');
            intervalInput.required = true;
            intervalHidden.name = '';
            intervalInput.name = 'interval_minutes';
        } else {
            intervalCustom.classList.add('d-none');
            intervalInput.required = false;
            intervalInput.name = '';
            intervalHidden.name = 'interval_minutes';
            intervalHidden.value = intervalSelect.value;
        }
    }

    intervalSelect.addEventListener('change', syncInterval);
    syncInterval();

    // ── Argomenti dinamici ────────────────────────────────────────────────────
    var argsList   = document.getElementById('sch-args-list');
    var argsAdd    = document.getElementById('sch-args-add');
    var argsRaw    = document.getElementById('sch-args-raw');

    function buildArgsRow(value) {
        var row = document.createElement('div');
        row.className = 'input-group sch-arg-row';
        row.innerHTML =
            '<input type="text" class="form-control font-monospace sch-arg-input"' +
            '       placeholder="' + escHtml(ARG_PLACEHOLDER) + '" value="' + escHtml(value) + '">' +
            '<button type="button" class="btn btn-outline-secondary sch-arg-remove" data-bs-toggle="tooltip" title="' + escHtml(REMOVE_ARG) + '">' +
            '    <i class="fa-solid fa-xmark"></i>' +
            '</button>';
        row.querySelector('.sch-arg-remove').addEventListener('click', function () {
            if (argsList.querySelectorAll('.sch-arg-row').length > 1) {
                row.remove();
            } else {
                row.querySelector('.sch-arg-input').value = '';
            }
            syncArgs();
        });
        row.querySelector('.sch-arg-input').addEventListener('input', syncArgs);
        return row;
    }

    function syncArgs() {
        var vals = [];
        argsList.querySelectorAll('.sch-arg-input').forEach(function (inp) {
            if (inp.value.trim()) vals.push(inp.value.trim());
        });
        argsRaw.value = vals.join("\n");
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Collega remove ai row esistenti
    argsList.querySelectorAll('.sch-arg-remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.sch-arg-row');
            if (argsList.querySelectorAll('.sch-arg-row').length > 1) {
                row.remove();
            } else {
                row.querySelector('.sch-arg-input').value = '';
            }
            syncArgs();
        });
    });
    argsList.querySelectorAll('.sch-arg-input').forEach(function (inp) {
        inp.addEventListener('input', syncArgs);
    });

    argsAdd.addEventListener('click', function () {
        var row = buildArgsRow('');
        argsList.appendChild(row);
        row.querySelector('.sch-arg-input').focus();
    });

    // Sincronizza prima del submit
    var form = argsAdd.closest('form');
    form.addEventListener('submit', function () { syncArgs(); });
})();
</script>

<?php $view->end(); ?>
