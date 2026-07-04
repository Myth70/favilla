<?php $view->layout('main'); ?>

<?php $view->pushStyle('css/calendar.css'); ?>
<?php $view->pushScript('vendor/fullcalendar/index.global.min.js'); ?>
<?php if (locale() === 'it'): // FullCalendar ships only the Italian locale bundle; other locales fall back to its built-in English ?>
<?php $view->pushScript('vendor/fullcalendar/locales/it.global.min.js'); ?>
<?php endif; ?>
<?php $view->pushScript('js/calendar.js'); ?>

<?php
use App\Modules\Auth\Helpers\AvatarHelper;

$calProfileName = $user['name'] ?? t('common.user.fallback_name');
$calAvatarUrl   = AvatarHelper::url($_SESSION['user_avatar'] ?? null);
$calInitials    = AvatarHelper::initials($calProfileName);
$calCurrentUserId = (int) ($currentUserId ?? ($user['id'] ?? 0));
?>

<?php $view->start('content'); ?>

<div class="container-fluid">

    <?php
    $calButtons = '';
    if ($canCreate) {
        $calButtons = '<button type="button" class="btn btn-primary btn-sm" id="cal-btn-new" data-bs-toggle="tooltip" data-bs-title="' . e(t('calendar.new_event_tooltip')) . '">' .
                      '<i class="fa-solid fa-plus me-1"></i>' . e(t('calendar.new_event')) . '</button>';
    }
    $view->include('partials/pf-hero-user', [
        'userName'     => t('calendar.title'),
        'userSubtitle' => $calProfileName . ' - ' . t('calendar.subtitle'),
        'userAvatar'   => $calAvatarUrl ?? null,
        'userInitials' => $calInitials,
        'userStats'    => $heroStats ?? [],
        'userButtons'  => $calButtons,
    ]);
    ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="cal-command-bar" role="group" aria-label="<?= e(t('calendar.command.search_aria')) ?>">
                <section class="cal-command-bar__group cal-command-bar__group--search">
                    <div class="cal-command-bar__title">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span><?= e(t('calendar.command.find_events')) ?></span>
                    </div>
                    <label for="cal-filter-query" class="visually-hidden"><?= e(t('calendar.command.find_events_label')) ?></label>
                    <div class="input-group input-group-sm flex-grow-1">
                        <span class="input-group-text app-input-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="search"
                               id="cal-filter-query"
                               class="form-control"
                               placeholder="<?= e(t('calendar.command.search_placeholder')) ?>"
                               autocomplete="off">
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="cal-filter-clear"
                                data-bs-toggle="tooltip"
                                data-bs-title="<?= e(t('calendar.command.clear_search')) ?>"
                                aria-label="<?= e(t('calendar.command.clear_search')) ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </section>

                <div class="cal-command-bar__divider" aria-hidden="true"></div>

                <section class="cal-command-bar__group cal-command-bar__group--scope">
                    <div class="cal-command-bar__title">
                        <i class="fa-solid fa-sliders"></i>
                        <span><?= e(t('calendar.command.quick_focus')) ?></span>
                    </div>
                    <div class="cal-command-bar__scopes" role="group" aria-label="<?= e(t('calendar.command.filters_aria')) ?>">
                        <button type="button" class="btn btn-sm btn-outline-secondary active" data-cal-scope="all">
                            <i class="fa-solid fa-layer-group"></i>
                            <span><?= e(t('calendar.command.scope_all')) ?></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cal-scope="mine">
                            <i class="fa-solid fa-user"></i>
                            <span><?= e(t('calendar.command.scope_mine')) ?></span>
                        </button>
                        <?php if (!is_single_user()): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cal-scope="shared">
                            <i class="fa-solid fa-users"></i>
                            <span><?= e(t('calendar.command.scope_shared')) ?></span>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-cal-scope="all-day">
                            <i class="fa-solid fa-sun"></i>
                            <span><?= e(t('calendar.command.scope_allday')) ?></span>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card shadow-sm h-100">
                <div class="card-body p-2 p-md-3">
                    <div id="cal-calendar"
                         data-events-url="<?= e(route('calendar.events')) ?>"
                         data-agenda-url="<?= e(route('calendar.agenda')) ?>"
                         data-create-url="<?= e(route('calendar.create')) ?>"
                         data-show-url="<?= e(route('calendar.show', ['id' => '__ID__'])) ?>"
                         data-edit-url="<?= e(route('calendar.edit', ['id' => '__ID__'])) ?>"
                         data-move-url="<?= e(route('calendar.move', ['id' => '__ID__'])) ?>"
                         data-current-user-id="<?= e((string) $calCurrentUserId) ?>"
                         data-csrf="<?= e(csrf_token()) ?>"
                         data-initial-edit-id="<?= e((string) ($initialEditId ?? '')) ?>"
                         data-can-create="<?= $canCreate ? '1' : '0' ?>"
                         data-can-edit="<?= $canEdit ? '1' : '0' ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between gap-2">
                    <span class="d-flex align-items-center gap-2">
                        <span class="app-card-icon"><i class="fa-solid fa-list-check"></i></span>
                        <span class="fw-semibold"><?= e(t('calendar.agenda.compact')) ?></span>
                    </span>
                    <small class="text-muted"><?= e(t('calendar.agenda.next_8')) ?></small>
                </div>
                <div class="card-body p-0" id="cal-agenda-panel">
                    <?php $view->include('Calendar/Views/partials/agenda_panel', [
                        'upcomingEvents' => $upcomingEvents ?? [],
                        'currentUserId'  => $calCurrentUserId,
                    ]); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-3 cal-tools-card">
        <div class="card-body">
            <div class="app-section-grid">

                <section class="app-section">
                    <header class="app-section-subhead">
                        <i class="fa-solid fa-file-export"></i>
                        <span><?= e(t('calendar.tools.export')) ?></span>
                        <small class="app-section-subhead-hint"><?= e(t('calendar.tools.export_hint')) ?></small>
                    </header>
                    <a href="<?= e(route('calendar.export_ics')) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-file-export me-1"></i><?= e(t('calendar.tools.export_ics')) ?>
                    </a>
                </section>

                <?php if ($canCreate): ?>
                <section class="app-section">
                    <header class="app-section-subhead">
                        <i class="fa-solid fa-file-import"></i>
                        <span><?= e(t('calendar.tools.import')) ?></span>
                        <small class="app-section-subhead-hint"><?= e(t('calendar.tools.import_hint')) ?></small>
                    </header>
                    <form method="POST" action="<?= e(route('calendar.import_ics')) ?>" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-center">
                        <?= csrf_field() ?>
                        <label for="cal-ics-file" class="visually-hidden"><?= e(t('calendar.tools.file_to_import')) ?></label>
                        <div class="input-group input-group-sm flex-grow-1">
                            <input type="text"
                                   id="cal-ics-file-name"
                                   class="form-control"
                                   value="<?= e(t('calendar.tools.no_file')) ?>"
                                   readonly
                                   aria-label="<?= e(t('calendar.tools.file_selected')) ?>">
                            <label class="btn btn-outline-secondary text-nowrap" for="cal-ics-file">
                                <i class="fa-solid fa-paperclip me-1"></i><?= e(t('calendar.tools.choose_file')) ?>
                            </label>
                            <input type="file"
                                   id="cal-ics-file"
                                   name="ics_file"
                                   class="visually-hidden"
                                   accept=".ics,text/calendar"
                                   required
                                   data-app-file-target="cal-ics-file-name"
                                   data-app-file-placeholder="<?= e(t('calendar.tools.no_file')) ?>">
                        </div>
                        <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">
                            <i class="fa-solid fa-file-import me-1"></i><?= e(t('calendar.tools.import_ics')) ?>
                        </button>
                    </form>
                </section>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div>

<!-- Modal shell (contenuto caricato via HTMX) -->
<div class="modal fade" id="cal-modal" tabindex="-1" aria-labelledby="cal-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" id="cal-modal-content">
            <!-- Caricato dinamicamente -->
        </div>
    </div>
</div>

<?php $view->end(); ?>
