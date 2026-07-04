<?php
/**
 * Attività — Form standalone (create/edit, pagina completa)
 *
 * Variabili: $task, $isEdit, $tags, $statuses, $priorities, $errors, $old
 * $errors formato Validator: ['campo' => ['messaggio', ...]]
 */
$view->layout('main');
$view->pushStyle('css/tasks.css');

$fv = function (string $k, $default = '') use ($old, $task) {
    if (isset($old) && array_key_exists($k, $old))                      return $old[$k];
    if (is_array($task ?? null) && array_key_exists($k, $task ?? []))   return $task[$k];
    return $default;
};
$fe = fn(string $k) => $errors[$k][0] ?? null;
$fc = fn(string $k, string $base = 'form-control') => $base . ($fe($k) ? ' is-invalid' : '');
?>

<?php $view->start('content'); ?>

<div class="container-fluid">

<?php
$moduleButtonsHtml = '<a href="' . e(route('tasks.index')) . '" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="' . e(t('tasks.form_page.back_to_list')) . '"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> ' . e(t('common.action.cancel')) . '</a>';
$view->include('partials/pf-hero-module', [
    'moduleName'     => $isEdit ? t('tasks.edit_page_title') : t('tasks.new_page_title'),
    'moduleIcon'     => 'fa-solid fa-list-check',
    'moduleSubtitle' => $isEdit ? e($task['title'] ?? '') : t('tasks.form_page.subtitle_new'),
    'moduleButtons'  => $moduleButtonsHtml,
]);
?>

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if (!empty($errors['generic'][0])): ?>
                <?php $view->include('partials/app-form-errors', [
                    'summaryText' => (string) $errors['generic'][0],
                    'summaryAriaLive' => 'polite',
                    'summaryBodyClass' => 'flex-grow-1',
                ]); ?>
            <?php elseif (!empty($errors)): ?>
                <?php $view->include('partials/app-form-errors', [
                    'errors' => $errors,
                    'summaryTitle' => t('tasks.form_page.some_invalid'),
                    'summaryAriaLive' => 'polite',
                    'summaryBodyClass' => 'flex-grow-1',
                    'summaryListClass' => 'mb-0 small ps-3',
                ]); ?>
            <?php endif; ?>

            <form method="POST" novalidate data-app-form
                  action="<?= $isEdit
                      ? e(route('tasks.update', ['id' => $task['id']]))
                      : e(route('tasks.store')) ?>">
                <?= csrf_field() ?>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

                <!-- ── SEZIONE 1: Dettagli ─────────────────────────── -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('tasks.form_page.section_details')) ?></legend>
                    <div class="app-form-section-header open" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="att-sec-dettagli">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        <?= e(t('tasks.form_page.section_details')) ?>
                        <i class="fa-solid fa-chevron-down app-chevron" aria-hidden="true"></i>
                    </div>
                    <div class="app-form-section-body" id="att-sec-dettagli">
                        <!-- Titolo -->
                        <div class="mb-3">
                            <label for="title" class="form-label"><?= e(t('tasks.fields.title')) ?> <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="title" name="title"
                                   class="<?= $fc('title') ?>"
                                   value="<?= e($fv('title')) ?>"
                                   placeholder="<?= e(t('tasks.form.title_placeholder')) ?>" maxlength="255"
                                   autocomplete="off"
                                   required aria-required="true"
                                   <?= $fe('title') ? 'aria-invalid="true" aria-describedby="title-feedback"' : '' ?>
                                   autofocus>
                            <div id="title-feedback" class="invalid-feedback"><?= e($fe('title') ?? t('tasks.form_page.title_required_hint')) ?></div>
                        </div>

                        <!-- Descrizione -->
                        <div class="mb-0">
                            <label for="description" class="form-label"><?= e(t('tasks.fields.description')) ?></label>
                            <textarea id="description" name="description"
                                      class="<?= $fc('description') ?>" rows="4"
                                      maxlength="5000"
                                      data-char-counter="att-desc-counter"
                                      placeholder="<?= e(t('tasks.form_page.description_placeholder_full')) ?>"
                                      aria-describedby="att-desc-counter<?= $fe('description') ? ' description-feedback' : '' ?>"
                                      <?= $fe('description') ? 'aria-invalid="true"' : '' ?>><?= e($fv('description')) ?></textarea>
                            <div id="description-feedback" class="invalid-feedback"><?= e($fe('description') ?? t('tasks.form_page.value_invalid')) ?></div>
                            <div class="form-text d-flex justify-content-end">
                                <span id="att-desc-counter" class="app-char-counter">0 / 5000</span>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- ── SEZIONE 2: Pianificazione ───────────────────── -->
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('tasks.form_page.section_planning')) ?></legend>
                    <div class="app-form-section-header open" role="button" tabindex="0"
                         aria-expanded="true" aria-controls="att-sec-plan">
                        <i class="fa-solid fa-calendar-day" aria-hidden="true"></i>
                        <?= e(t('tasks.form_page.section_planning')) ?>
                        <i class="fa-solid fa-chevron-down app-chevron" aria-hidden="true"></i>
                    </div>
                    <div class="app-form-section-body" id="att-sec-plan">
                        <div class="row g-3">
                            <!-- Status -->
                            <div class="col-md-6">
                                <label for="status" class="form-label"><?= e(t('tasks.fields.status')) ?></label>
                                <?php $curStatus = $fv('status', $status ?? 'todo'); ?>
                                <select id="status" name="status" class="form-select<?= $fe('status') ? ' is-invalid' : '' ?>"
                                        <?= $fe('status') ? 'aria-invalid="true" aria-describedby="status-feedback"' : '' ?>>
                                    <?php foreach ($statuses as $key => $s): ?>
                                    <option value="<?= e($key) ?>" <?= $curStatus === $key ? 'selected' : '' ?>>
                                        <?= e($s['label']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="status-feedback" class="invalid-feedback"><?= e($fe('status') ?? t('tasks.validation.status_invalid')) ?></div>
                            </div>

                            <!-- Priorità -->
                            <div class="col-md-6">
                                <label for="priority" class="form-label"><?= e(t('tasks.fields.priority')) ?></label>
                                <?php $curPriority = $fv('priority', 'medium'); ?>
                                <select id="priority" name="priority" class="form-select<?= $fe('priority') ? ' is-invalid' : '' ?>"
                                        <?= $fe('priority') ? 'aria-invalid="true" aria-describedby="priority-feedback"' : '' ?>>
                                    <?php foreach ($priorities as $key => $p): ?>
                                    <option value="<?= e($key) ?>" <?= $curPriority === $key ? 'selected' : '' ?>>
                                        <?= e($p['label']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="priority-feedback" class="invalid-feedback"><?= e($fe('priority') ?? t('tasks.validation.priority_invalid')) ?></div>
                            </div>

                            <!-- Data scadenza -->
                            <div class="col-md-4">
                                <label for="due_date" class="form-label"><?= e(t('tasks.fields.due_date')) ?></label>
                                <input type="date" id="due_date" name="due_date"
                                       class="<?= $fc('due_date') ?>"
                                       value="<?= e($fv('due_date')) ?>"
                                       <?= $fe('due_date') ? 'aria-invalid="true" aria-describedby="due_date-feedback"' : '' ?>>
                                <div id="due_date-feedback" class="invalid-feedback"><?= e($fe('due_date') ?? t('tasks.validation.due_date_invalid')) ?></div>
                            </div>

                            <!-- Ora scadenza -->
                            <div class="col-md-4">
                                <label for="due_time" class="form-label"><?= e(t('tasks.fields.due_time')) ?></label>
                                <input type="time" id="due_time" name="due_time"
                                       class="<?= $fc('due_time') ?>"
                                       value="<?= e(!empty($old['due_time']) ? $old['due_time'] : (isset($task['due_time']) ? substr((string)$task['due_time'], 0, 5) : '')) ?>"
                                       <?= $fe('due_time') ? 'aria-invalid="true" aria-describedby="due_time-feedback"' : '' ?>>
                                <div id="due_time-feedback" class="invalid-feedback"><?= e($fe('due_time') ?? t('tasks.validation.due_time_invalid')) ?></div>
                            </div>

                            <!-- Colore -->
                            <div class="col-md-4">
                                <label for="color" class="form-label"><?= e(t('tasks.fields.color')) ?></label>
                                <input type="color" id="color" name="color"
                                       class="form-control form-control-color w-100<?= $fe('color') ? ' is-invalid' : '' ?>"
                                       value="<?= e($fv('color', '#0d6efd')) ?>"
                                       aria-label="<?= e(t('tasks.form_page.color_aria')) ?>"
                                       <?= $fe('color') ? 'aria-invalid="true" aria-describedby="color-feedback"' : '' ?>>
                                <div id="color-feedback" class="invalid-feedback"><?= e($fe('color') ?? t('tasks.validation.color_invalid')) ?></div>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <!-- ── SEZIONE 3: Tag ─────────────────────────────── -->
                <?php if (!empty($tags)): ?>
                <fieldset class="app-form-section">
                    <legend class="visually-hidden"><?= e(t('tasks.fields.tags')) ?></legend>
                    <div class="app-form-section-header" role="button" tabindex="0"
                         aria-expanded="false" aria-controls="att-sec-tag">
                        <i class="fa-solid fa-tags" aria-hidden="true"></i>
                        <?= e(t('tasks.fields.tags')) ?>
                        <i class="fa-solid fa-chevron-down app-chevron" aria-hidden="true"></i>
                    </div>
                    <div class="app-form-section-body app-form-section-collapsed" id="att-sec-tag">
                        <div class="d-flex flex-wrap gap-2">
                            <?php
                            $selectedTags = $old['tag_ids'] ?? array_column($task['tags'] ?? [], 'id');
                            $selectedTags = array_map('intval', (array) $selectedTags);
                            foreach ($tags as $tag):
                                $isChecked = in_array((int) $tag['id'], $selectedTags, true);
                            ?>
                            <label class="att-tag-check">
                                <input type="checkbox" name="tag_ids[]" value="<?= e((string) $tag['id']) ?>"
                                       <?= $isChecked ? 'checked' : '' ?> class="d-none">
                                <span class="badge att-tag-badge" style="--att-tag-color: <?= e($tag['color']) ?>;">
                                    <?= e($tag['name']) ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </fieldset>
                <?php endif; ?>

                <!-- Bottoni -->
                <div class="d-flex justify-content-end gap-2 mt-3 mb-4">
                    <a href="<?= e(route('tasks.index')) ?>" class="btn btn-outline-secondary"><?= e(t('common.action.cancel')) ?></a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        <?= $isEdit ? e(t('common.action.update')) : e(t('common.action.create')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $view->end(); ?>
