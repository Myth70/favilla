<?php
// Dati: $event (null se create), $isEdit, $roles, $date, $endDate, $allDay, $errors, $old
$endDate = $endDate ?? '';
$errors  = $errors  ?? [];
$ev = $old ?: ($event ?? []);

$title        = $ev['title'] ?? '';
$description  = $ev['description'] ?? '';
$startDt      = $ev['start_datetime'] ?? ($date ? ($date . 'T09:00') : '');
$endDt        = $ev['end_datetime'] ?? ($endDate ?: '');
$isAllDay     = $allDay || !empty($ev['all_day']);
$category     = $ev['category'] ?? '';
$color        = $ev['color'] ?? '#3b82f6';
$location     = $ev['location'] ?? '';
$visibility   = $ev['visibility'] ?? 'personal';
$visibleRole  = $ev['visible_to_role'] ?? '';
$reminder     = $ev['reminder_minutes'] ?? '';
$recurrenceRule = $ev['recurrence_rule'] ?? '';
$recurrenceEnd  = $ev['recurrence_end'] ?? '';

$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');

// Decomponi RRULE in valori UI guidati
$recurrenceFreq     = 'none';
$recurrenceInterval = 1;
$recurrenceCount    = '';
$recurrenceEndType  = 'never'; // never | count | date

if ($recurrenceRule !== '') {
    $parts = array_filter(array_map('trim', explode(';', strtoupper((string) $recurrenceRule))));
    $tokens = [];
    foreach ($parts as $part) {
        $pair = explode('=', $part, 2);
        if (count($pair) === 2) {
            $tokens[$pair[0]] = $pair[1];
        }
    }

    if (!empty($tokens['FREQ']) && in_array($tokens['FREQ'], ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
        $recurrenceFreq = strtolower($tokens['FREQ']);
        if (!empty($tokens['INTERVAL']) && ctype_digit((string) $tokens['INTERVAL'])) {
            $recurrenceInterval = max(1, (int) $tokens['INTERVAL']);
        }
        if (!empty($tokens['COUNT']) && ctype_digit((string) $tokens['COUNT'])) {
            $recurrenceCount = (string) max(1, (int) $tokens['COUNT']);
            $recurrenceEndType = 'count';
        }
    }
}

if ($recurrenceEnd !== '' && $recurrenceEndType === 'never' && $recurrenceFreq !== 'none') {
    $recurrenceEndType = 'date';
}

// Normalizza formati datetime per gli input HTML
if ($startDt && strlen($startDt) > 16) {
    $startDt = substr(str_replace(' ', 'T', $startDt), 0, 16);
}
if ($endDt && strlen($endDt) > 16) {
    $endDt = substr(str_replace(' ', 'T', $endDt), 0, 16);
}
$recurrenceEndDate = '';
if ($recurrenceEnd) {
    $recurrenceEndDate = substr(str_replace(' ', 'T', $recurrenceEnd), 0, 10);
}

$actionUrl = $isEdit
    ? route('calendar.update', ['id' => $event['id']])
    : route('calendar.store');

$colorPresets = [
    '#3b82f6' => t('calendar.colors.blue'),
    '#8b5cf6' => t('calendar.colors.purple'),
    '#ec4899' => t('calendar.colors.pink'),
    '#ef4444' => t('calendar.colors.red'),
    '#f97316' => t('calendar.colors.orange'),
    '#22c55e' => t('calendar.colors.green'),
    '#14b8a6' => t('calendar.colors.teal'),
    '#64748b' => t('calendar.colors.gray'),
];
?>

<div class="modal-header">
    <h5 class="modal-title" id="cal-modal-label">
        <i class="fa-solid fa-calendar-<?= $isEdit ? 'pen' : 'plus' ?>"></i>
        <?= $isEdit ? e(t('calendar.edit_event')) : e(t('calendar.new_event')) ?>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(t('common.action.close')) ?>"></button>
</div>

<form method="POST"
      action="<?= e($actionUrl) ?>"
      hx-post="<?= e($actionUrl) ?>"
      hx-target="#cal-modal-content"
      hx-swap="innerHTML"
      novalidate data-app-form>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
    <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="modal-body">

        <?php $view->include('partials/app-form-errors', [
            'errors' => $errors,
            'summaryTitle' => t('calendar.form.fix_errors'),
            'summaryBodyClass' => 'small',
        ]); ?>

        <div class="row g-3">

            <!-- Titolo -->
            <div class="col-12">
                <label for="cal-title" class="form-label fw-semibold">
                    <?= e(t('calendar.form.title')) ?> <span class="text-danger">*</span>
                </label>
                <input type="text" id="cal-title" name="title"
                       class="<?= $fc('title') ?>"
                       value="<?= e($title) ?>" required aria-required="true" maxlength="255" autofocus
                       placeholder="<?= e(t('calendar.form.title_placeholder')) ?>"
                       aria-invalid="<?= $fe('title') ? 'true' : 'false' ?>"
                       aria-describedby="cal-title-feedback">
                <div id="cal-title-feedback" class="invalid-feedback">
                    <?= e($fe('title') ?? t('calendar.form.title_hint')) ?>
                </div>
            </div>

            <!-- Data e orario — sezione con bordo -->
            <div class="col-12">
                <div class="border rounded p-3 bg-body-tertiary">

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" id="cal-all-day" name="all_day"
                               value="1" <?= $isAllDay ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="cal-all-day">
                            <i class="fa-regular fa-sun me-1 text-warning"></i><?= e(t('calendar.form.all_day')) ?>
                        </label>
                    </div>

                    <div class="row g-2">
                        <!-- Inizio -->
                        <div class="col-md-6">
                            <label for="cal-start" class="form-label form-label-sm mb-1">
                                <i class="fa-regular fa-clock me-1 text-muted"></i><?= e(t('calendar.form.start')) ?> <span class="text-danger">*</span>
                            </label>
                            <input type="<?= $isAllDay ? 'date' : 'datetime-local' ?>"
                                   id="cal-start" name="start_datetime"
                                   class="<?= $fc('start_datetime', 'form-control form-control-sm cal-time-input') ?>"
                                   value="<?= e($isAllDay && $startDt ? substr($startDt, 0, 10) : $startDt) ?>"
                                   required aria-required="true"
                                   aria-invalid="<?= $fe('start_datetime') ? 'true' : 'false' ?>"
                                   aria-describedby="cal-start-feedback">
                            <div id="cal-start-feedback" class="invalid-feedback">
                                <?= e($fe('start_datetime') ?? t('calendar.form.start_hint')) ?>
                            </div>
                        </div>

                        <!-- Fine -->
                        <div class="col-md-6<?= $isAllDay ? ' d-none' : '' ?>" id="cal-end-group">
                            <label for="cal-end" class="form-label form-label-sm mb-1">
                                <i class="fa-regular fa-clock me-1 text-muted"></i><?= e(t('calendar.form.end')) ?>
                            </label>
                            <input type="datetime-local"
                                   id="cal-end" name="end_datetime"
                                   class="<?= $fc('end_datetime', 'form-control form-control-sm') ?>"
                                   value="<?= e($endDt) ?>"
                                   aria-invalid="<?= $fe('end_datetime') ? 'true' : 'false' ?>"
                                   aria-describedby="cal-end-feedback">
                            <div id="cal-end-feedback" class="invalid-feedback">
                                <?= e($fe('end_datetime') ?? t('calendar.form.end_invalid')) ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <div id="cal-duration-helper"
                                 class="d-flex gap-1 flex-wrap<?= $isAllDay ? ' d-none' : '' ?>">
                                <small class="text-muted me-1 align-self-center"><?= e(t('calendar.form.quick_duration')) ?></small>
                                <?php foreach ([30 => t('calendar.form.dur_30'), 60 => t('calendar.form.dur_60'), 90 => t('calendar.form.dur_90'), 120 => t('calendar.form.dur_120')] as $mins => $label): ?>
                                <button type="button"
                                        class="btn btn-outline-secondary btn-xs py-0 px-1 cal-dur-btn"
                                        data-minutes="<?= $mins ?>"
                                        data-bs-toggle="tooltip" data-bs-placement="bottom"
                                        data-bs-title="<?= e(t('calendar.form.dur_tooltip', ['label' => $label])) ?>">
                                    <?= e($label) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Luogo + Categoria -->
            <div class="col-md-6">
                <label for="cal-location" class="form-label">
                    <i class="fa-solid fa-location-dot me-1 text-muted"></i><?= e(t('calendar.form.location')) ?>
                </label>
                <input type="text" id="cal-location" name="location"
                       class="form-control" value="<?= e($location) ?>" maxlength="255"
                       autocomplete="street-address"
                       placeholder="<?= e(t('calendar.form.location_placeholder')) ?>">
            </div>
            <div class="col-md-6">
                <label for="cal-category" class="form-label">
                    <i class="fa-solid fa-tag me-1 text-muted"></i><?= e(t('calendar.form.category')) ?>
                </label>
                <input type="text" id="cal-category" name="category"
                       class="form-control" value="<?= e($category) ?>" maxlength="50"
                       placeholder="<?= e(t('calendar.form.category_placeholder')) ?>">
            </div>

            <!-- Colore -->
            <div class="col-12">
                <label class="form-label">
                    <i class="fa-solid fa-palette me-1 text-muted"></i><?= e(t('calendar.form.color')) ?>
                </label>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php foreach ($colorPresets as $hex => $name): ?>
                    <label class="cal-color-swatch mb-0"
                           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= e($name) ?>">
                        <input type="radio" name="_color_radio" value="<?= e($hex) ?>"
                               class="d-none cal-color-radio"
                               <?= $color === $hex ? 'checked' : '' ?>>
                        <span style="--cal-swatch-color: <?= e($hex) ?>; --cal-swatch-border: <?= $color === $hex ? 'var(--text-primary)' : 'transparent' ?>;"
                              class="cal-swatch-dot"></span>
                    </label>
                    <?php endforeach; ?>
                    <label class="cal-color-swatch mb-0"
                           data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="<?= e(t('calendar.form.color_custom')) ?>">
                        <input type="color" id="cal-color-custom" value="<?= e($color) ?>"
                               class="form-control form-control-color p-0 cal-color-custom-input cal-color-custom-size"
                               aria-label="<?= e(t('calendar.form.color_custom')) ?>">
                    </label>
                    <input type="hidden" id="cal-color" name="color" value="<?= e($color) ?>">
                </div>
            </div>

            <!-- Descrizione -->
            <div class="col-12">
                <label for="cal-desc" class="form-label">
                    <i class="fa-regular fa-note-sticky me-1 text-muted"></i><?= e(t('calendar.form.description')) ?>
                </label>
                <textarea id="cal-desc" name="description" class="form-control" rows="2"
                          maxlength="2000"
                          placeholder="<?= e(t('calendar.form.description_placeholder')) ?>"><?= e($description) ?></textarea>
            </div>

            <!-- Visibilità + Ruolo -->
            <div class="col-md-6">
                <label for="cal-visibility" class="form-label">
                    <i class="fa-solid fa-eye me-1 text-muted"></i><?= e(t('calendar.form.visibility')) ?>
                </label>
                <select id="cal-visibility" name="visibility" class="form-select">
                    <option value="personal" <?= $visibility === 'personal' ? 'selected' : '' ?>>
                        <?= e(t('calendar.form.vis_personal')) ?>
                    </option>
                    <?php if (!is_single_user() || $visibility !== 'personal'): ?>
                    <option value="role" <?= $visibility === 'role' ? 'selected' : '' ?>>
                        <?= e(t('calendar.form.vis_role')) ?>
                    </option>
                    <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>
                        <?= e(t('calendar.form.vis_public')) ?>
                    </option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-6<?= $visibility === 'role' ? '' : ' d-none' ?>" id="cal-role-group">
                <label for="cal-role" class="form-label">
                    <i class="fa-solid fa-users me-1 text-muted"></i><?= e(t('calendar.form.role')) ?> <span class="text-danger">*</span>
                </label>
                <select id="cal-role" name="visible_to_role"
                        class="<?= $fc('visible_to_role', 'form-select') ?>"
                        aria-invalid="<?= $fe('visible_to_role') ? 'true' : 'false' ?>"
                        aria-describedby="cal-role-feedback">
                    <option value=""><?= e(t('calendar.form.role_select')) ?></option>
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role['id']) ?>"
                            <?= (int) $visibleRole === (int) $role['id'] ? 'selected' : '' ?>>
                        <?= e($role['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="cal-role-feedback" class="invalid-feedback">
                    <?= e($fe('visible_to_role') ?? t('calendar.form.role_hint')) ?>
                </div>
            </div>

            <!-- Promemoria -->
            <div class="col-md-6">
                <label for="cal-reminder" class="form-label">
                    <i class="fa-regular fa-bell me-1 text-muted"></i><?= e(t('calendar.form.reminder')) ?>
                </label>
                <select id="cal-reminder" name="reminder_minutes" class="form-select">
                    <option value=""><?= e(t('calendar.form.reminder_none')) ?></option>
                    <option value="15"   <?= $reminder == 15   ? 'selected' : '' ?>><?= e(t('calendar.form.reminder_15')) ?></option>
                    <option value="30"   <?= $reminder == 30   ? 'selected' : '' ?>><?= e(t('calendar.form.reminder_30')) ?></option>
                    <option value="60"   <?= $reminder == 60   ? 'selected' : '' ?>><?= e(t('calendar.form.reminder_60')) ?></option>
                    <option value="120"  <?= $reminder == 120  ? 'selected' : '' ?>><?= e(t('calendar.form.reminder_120')) ?></option>
                    <option value="1440" <?= $reminder == 1440 ? 'selected' : '' ?>><?= e(t('calendar.form.reminder_1440')) ?></option>
                </select>
            </div>

            <!-- Recurrence -->
            <div class="col-12">
                <div class="border rounded p-3 bg-body-tertiary">
                    <div class="row g-2 align-items-center">

                        <div class="col-12 col-sm-auto">
                            <label for="cal-recur-freq" class="form-label mb-0">
                                <i class="fa-solid fa-rotate me-1 text-muted"></i>
                                <span class="fw-semibold"><?= e(t('calendar.form.recurrence')) ?></span>
                            </label>
                        </div>
                        <div class="col-12 col-sm">
                            <select id="cal-recur-freq" class="form-select form-select-sm">
                                <option value="none"    <?= $recurrenceFreq === 'none'    ? 'selected' : '' ?>><?= e(t('calendar.form.freq_none')) ?></option>
                                <option value="daily"   <?= $recurrenceFreq === 'daily'   ? 'selected' : '' ?>><?= e(t('calendar.form.freq_daily')) ?></option>
                                <option value="weekly"  <?= $recurrenceFreq === 'weekly'  ? 'selected' : '' ?>><?= e(t('calendar.form.freq_weekly')) ?></option>
                                <option value="monthly" <?= $recurrenceFreq === 'monthly' ? 'selected' : '' ?>><?= e(t('calendar.form.freq_monthly')) ?></option>
                            </select>
                        </div>

                    </div>

                    <div id="cal-recur-options"
                         class="mt-3<?= $recurrenceFreq === 'none' ? ' d-none' : '' ?>">

                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="text-muted small"><?= e(t('calendar.form.every')) ?></span>
                            <input type="number" id="cal-recur-interval" class="form-control form-control-sm cal-recur-interval-input"
                                   min="1" max="365"
                                   value="<?= e((string) $recurrenceInterval) ?>"
                                   aria-label="<?= e(t('calendar.form.interval_aria')) ?>">
                            <span id="cal-recur-unit-label" class="text-muted small">
                                <?php
                                $unitLabels = ['daily' => t('calendar.form.unit_daily'), 'weekly' => t('calendar.form.unit_weekly'), 'monthly' => t('calendar.form.unit_monthly')];
                                echo e($unitLabels[$recurrenceFreq] ?? t('calendar.form.unit_daily'));
                                ?>
                            </span>
                        </div>

                        <div>
                            <div class="fw-semibold small mb-2 text-muted"><?= e(t('calendar.form.ends')) ?></div>

                            <div class="d-flex flex-column gap-2">

                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                           name="recur_end_type" id="cal-recur-end-never"
                                           value="never"
                                           <?= $recurrenceEndType === 'never' ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="cal-recur-end-never">
                                        <?= e(t('calendar.form.end_never')) ?>
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                           name="recur_end_type" id="cal-recur-end-count"
                                           value="count"
                                           <?= $recurrenceEndType === 'count' ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="cal-recur-end-count">
                                        <?= e(t('calendar.form.end_after')) ?>
                                    </label>
                                </div>
                                <div id="cal-recur-count-group"
                                     class="ps-4 d-flex align-items-center gap-2<?= $recurrenceEndType === 'count' ? '' : ' d-none' ?>">
                                    <input type="number" id="cal-recur-count"
                                           class="form-control form-control-sm cal-recur-count-input"
                                           min="1" max="500" placeholder="N"
                                           value="<?= e($recurrenceCount) ?>"
                                           aria-label="<?= e(t('calendar.form.count_aria')) ?>">
                                    <span class="text-muted small"><?= e(t('calendar.form.occurrences')) ?></span>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                           name="recur_end_type" id="cal-recur-end-date"
                                           value="date"
                                           <?= $recurrenceEndType === 'date' ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="cal-recur-end-date">
                                        <?= e(t('calendar.form.end_on')) ?>
                                    </label>
                                </div>
                                <div id="cal-recur-date-group"
                                     class="ps-4<?= $recurrenceEndType === 'date' ? '' : ' d-none' ?>">
                                    <input type="date" id="cal-recurrence-end-picker"
                                           class="form-control form-control-sm"
                                           value="<?= e($recurrenceEndDate) ?>"
                                           aria-label="<?= e(t('calendar.form.recur_end_aria')) ?>">
                                </div>

                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </div><!-- /row -->

        <!-- Hidden inputs per il backend -->
        <input type="hidden" id="cal-recurrence-rule" name="recurrence_rule"
               value="<?= e($recurrenceRule) ?>">
        <?php if ($fe('recurrence_rule')): ?>
        <div class="text-danger small mt-1"><?= e($fe('recurrence_rule')) ?></div>
        <?php endif; ?>

        <input type="hidden" id="cal-recurrence-end" name="recurrence_end"
               value="<?= e($recurrenceEndDate) ?>">
        <?php if ($fe('recurrence_end')): ?>
        <div class="text-danger small mt-1"><?= e($fe('recurrence_end')) ?></div>
        <?php endif; ?>

    </div><!-- /modal-body -->

    <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                data-bs-toggle="tooltip" data-bs-title="<?= e(t('calendar.form.cancel_tooltip')) ?>">
            <?= e(t('common.action.cancel')) ?>
        </button>
        <button type="submit" class="btn btn-primary"
                data-bs-toggle="tooltip"
                data-bs-title="<?= $isEdit ? e(t('calendar.form.save_tooltip_edit')) : e(t('calendar.form.save_tooltip_new')) ?>">
            <i class="fa-solid fa-check me-1"></i><?= $isEdit ? e(t('calendar.form.update')) : e(t('calendar.form.create')) ?>
        </button>
    </div>

</form>
