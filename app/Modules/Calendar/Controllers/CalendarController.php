<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Controllers;

use App\Core\Controller;
use App\Modules\Calendar\Services\CalendarService;
use App\Traits\ControllerHelpers;

class CalendarController extends Controller
{
    use ControllerHelpers;

    private CalendarService $service;

    public function __construct()
    {
        $this->service = app(CalendarService::class);
    }

    // ── index ────────────────────────────────────────────────────────────

    public function index(): void
    {
        $filters = $this->cleanGet(['edit']);
        $initialEditId = null;
        $user = auth();
        $userId = (int) ($user['id'] ?? 0);
        if (!empty($filters['edit']) && ctype_digit((string) $filters['edit'])) {
            $initialEditId = (int) $filters['edit'];
        }

        $roles = $this->getRolesList();

        $this->render('Calendar/Views/index', [
            'pageTitle'   => t('calendar.title'),
            'breadcrumbs' => [['label' => t('calendar.title')]],
            'roles'       => $roles,
            'initialEditId' => $initialEditId,
            'heroStats'   => $this->service->getHeroStats($userId),
            'upcomingEvents' => $this->service->getUpcomingEvents($userId, 8),
            'currentUserId' => $userId,
            'canCreate'   => has_permission('calendar.create'),
            'canEdit'     => has_permission('calendar.edit'),
            'canDelete'   => has_permission('calendar.delete'),
        ]);
    }

    public function agenda(): void
    {
        $userId = (int) (auth()['id'] ?? 0);

        $this->renderPartial('Calendar/Views/partials/agenda_panel', [
            'upcomingEvents' => $this->service->getUpcomingEvents($userId, 8),
            'currentUserId'  => $userId,
        ]);
    }

    // ── events (JSON feed per FullCalendar) ──────────────────────────────

    public function events(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];
        $clean  = $this->cleanGet(['start', 'end']);

        $start = $clean['start'] ?: date('Y-m-01');
        $end   = $clean['end'] ?: date('Y-m-t');

        // Validazione formato data
        if (!strtotime($start) || !strtotime($end)) {
            $this->json(['error' => t('calendar.validation.dates_invalid')], 400);
            return;
        }

        $events = $this->service->getEventsForUser($userId, $start, $end);
        $this->json($events);
    }

    // ── create (modal partial via HTMX) ──────────────────────────────────

    public function create(): void
    {
        $clean   = $this->cleanGet(['date', 'endDate', 'allDay']);
        $date    = $clean['date'] ?? '';
        $endDate = $clean['endDate'] ?? '';
        $allDay  = $clean['allDay'] ?: '0';

        $roles = $this->getRolesList();

        $this->renderPartial('Calendar/Views/partials/modal_form', [
            'event'   => null,
            'isEdit'  => false,
            'roles'   => $roles,
            'date'    => $date,
            'endDate' => $endDate,
            'allDay'  => $allDay === 'true' || $allDay === '1',
            'errors'  => $_SESSION['_errors'] ?? [],
            'old'     => $_SESSION['_old'] ?? [],
        ]);

        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    // ── store ────────────────────────────────────────────────────────────

    public function store(): void
    {
        $user   = auth();
        $userId = (int) $user['id'];

        $data = $this->cleanPost(['title', 'description', 'location', 'color', 'category', 'visibility']);
        $data['start_datetime']   = $_POST['start_datetime'] ?? '';
        $data['end_datetime']     = $_POST['end_datetime'] ?? '';
        $data['recurrence_rule']  = trim((string) ($_POST['recurrence_rule'] ?? ''));
        $data['recurrence_end']   = $_POST['recurrence_end'] ?? '';
        $data['all_day']          = isset($_POST['all_day']) ? 1 : 0;
        $data['visible_to_role']  = !empty($_POST['visible_to_role']) ? (int) $_POST['visible_to_role'] : null;
        $data['reminder_minutes'] = !empty($_POST['reminder_minutes']) ? (int) $_POST['reminder_minutes'] : null;

        // Validazione
        $errors = $this->validateEvent($data);
        if (!empty($errors)) {
            $this->renderPartial('Calendar/Views/partials/modal_form', [
                'event'  => null,
                'isEdit' => false,
                'roles'  => $this->getRolesList(),
                'date'   => '',
                'allDay' => (bool) $data['all_day'],
                'errors' => $errors,
                'old'    => $data,
            ]);
            return;
        }

        try {
            $this->service->createEvent($data, $userId);

            header('HX-Trigger: ' . json_encode([
                'closeModal'    => true,
                'refetchEvents' => true,
                'notify'        => ['message' => t('calendar.flash.created'), 'type' => 'success'],
            ]));
            echo '';
        } catch (\Throwable $e) {
            $this->renderPartial('Calendar/Views/partials/modal_form', [
                'event'  => null,
                'isEdit' => false,
                'roles'  => $this->getRolesList(),
                'date'   => '',
                'allDay' => (bool) $data['all_day'],
                'errors' => ['general' => t('calendar.flash.create_error')],
                'old'    => $data,
            ]);
            app_log('error', '[Calendario] store error: ' . $e->getMessage());
        }
    }

    // ── show (modal detail o full page) ──────────────────────────────────

    public function show(string $id): void
    {
        $id = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];
        $event  = $this->service->getEvent($id, $userId);

        if (!$event) {
            if ($this->isHtmxRequest()) {
                http_response_code(404);
                echo '<div class="p-3 text-center text-muted">' . e(t('calendar.detail.not_found')) . '</div>';
                return;
            }
            flash_error(t('calendar.flash.not_found'));
            $this->redirect(route('calendar.index'));
            return;
        }

        $canEdit   = $this->service->canEdit($event, $userId) && has_permission('calendar.edit');
        $canDelete = $this->service->canEdit($event, $userId) && has_permission('calendar.delete');
        $linkedContext = $this->service->getLinkedContexts($id, $userId);

        if ($this->isHtmxRequest()) {
            $this->renderPartial('Calendar/Views/partials/modal_detail', [
                'event'     => $event,
                'canEdit'   => $canEdit,
                'canDelete' => $canDelete,
                'linkedContext' => $linkedContext,
            ]);
            return;
        }

        $this->render('Calendar/Views/show', [
            'pageTitle'   => $event['title'],
            'breadcrumbs' => [
                ['label' => t('calendar.title'), 'route' => 'calendar.index'],
                ['label' => $event['title']],
            ],
            'event'     => $event,
            'canEdit'   => $canEdit,
            'canDelete' => $canDelete,
            'linkedContext' => $linkedContext,
        ]);
    }

    // ── edit (modal partial via HTMX) ────────────────────────────────────

    public function edit(string $id): void
    {
        $id = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];
        $event  = $this->service->getEvent($id, $userId);

        if (!$event) {
            http_response_code(404);
            echo '<div class="p-3 text-center text-muted">' . e(t('calendar.detail.not_found')) . '</div>';
            return;
        }

        if (!$this->service->canEdit($event, $userId)) {
            http_response_code(403);
            echo '<div class="p-3 text-center text-danger">' . e(t('calendar.detail.unauthorized')) . '</div>';
            return;
        }

        $this->renderPartial('Calendar/Views/partials/modal_form', [
            'event'   => $event,
            'isEdit'  => true,
            'roles'   => $this->getRolesList(),
            'date'    => '',
            'allDay'  => (bool) $event['all_day'],
            'errors'  => [],
            'old'     => [],
        ]);
    }

    // ── update ───────────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $id = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        $data = $this->cleanPost(['title', 'description', 'location', 'color', 'category', 'visibility']);
        $data['start_datetime']   = $_POST['start_datetime'] ?? '';
        $data['end_datetime']     = $_POST['end_datetime'] ?? '';
        $data['recurrence_rule']  = trim((string) ($_POST['recurrence_rule'] ?? ''));
        $data['recurrence_end']   = $_POST['recurrence_end'] ?? '';
        $data['all_day']          = isset($_POST['all_day']) ? 1 : 0;
        $data['visible_to_role']  = !empty($_POST['visible_to_role']) ? (int) $_POST['visible_to_role'] : null;
        $data['reminder_minutes'] = !empty($_POST['reminder_minutes']) ? (int) $_POST['reminder_minutes'] : null;

        $errors = $this->validateEvent($data);
        if (!empty($errors)) {
            $event = $this->service->getEvent($id, $userId);
            $this->renderPartial('Calendar/Views/partials/modal_form', [
                'event'  => $event,
                'isEdit' => true,
                'roles'  => $this->getRolesList(),
                'date'   => '',
                'allDay' => (bool) $data['all_day'],
                'errors' => $errors,
                'old'    => $data,
            ]);
            return;
        }

        try {
            $this->service->updateEvent($id, $data, $userId);

            header('HX-Trigger: ' . json_encode([
                'closeModal'    => true,
                'refetchEvents' => true,
                'notify'        => ['message' => t('calendar.flash.updated'), 'type' => 'success'],
            ]));
            echo '';
        } catch (\Throwable $e) {
            $event = $this->service->getEvent($id, $userId);
            $this->renderPartial('Calendar/Views/partials/modal_form', [
                'event'  => $event,
                'isEdit' => true,
                'roles'  => $this->getRolesList(),
                'date'   => '',
                'allDay' => (bool) $data['all_day'],
                'errors' => ['general' => t('calendar.flash.update_error')],
                'old'    => $data,
            ]);
            app_log('error', '[Calendario] update error: ' . $e->getMessage());
        }
    }

    // ── move (drag & drop / resize — JSON endpoint) ───────────────────

    public function move(string $id): void
    {
        $id = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        $start  = $_POST['start'] ?? '';
        $end    = $_POST['end'] ?? null;
        $allDay = isset($_POST['allDay']) ? ($_POST['allDay'] === '1') : null;

        if (empty($start) || !strtotime($start)) {
            $this->json(['error' => t('calendar.validation.date_invalid')], 400);
            return;
        }

        if (!empty($end) && !strtotime((string) $end)) {
            $this->json(['error' => t('calendar.validation.move_end_invalid')], 400);
            return;
        }

        if (!empty($end) && strtotime((string) $end) <= strtotime($start)) {
            $this->json(['error' => t('calendar.validation.move_end_after')], 400);
            return;
        }

        try {
            $this->service->moveEvent($id, $start, $end, $allDay, $userId);
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            app_log('error', '[Calendario] move error: ' . $e->getMessage());
            $this->json(['error' => t('calendar.validation.move_failed')], 403);
        }
    }

    // ── destroy ──────────────────────────────────────────────────────────

    public function destroy(string $id): void
    {
        $id = (int) $id;
        $user   = auth();
        $userId = (int) $user['id'];

        try {
            $this->service->deleteEvent($id, $userId);

            header('HX-Trigger: ' . json_encode([
                'closeModal'    => true,
                'refetchEvents' => true,
                'notify'        => ['message' => t('calendar.flash.deleted'), 'type' => 'success'],
            ]));
            echo '';
        } catch (\Throwable $e) {
            http_response_code(403);
            header('HX-Trigger: ' . json_encode([
                'notify' => ['message' => t('calendar.flash.delete_failed'), 'type' => 'danger'],
            ]));
            echo '';
        }
    }

    public function exportIcs(): void
    {
        $user = auth();
        $userId = (int) $user['id'];
        $clean = $this->cleanGet(['start', 'end']);

        $start = $clean['start'] ?: date('Y-m-01');
        $end = $clean['end'] ?: date('Y-m-t');

        if (!strtotime($start) || !strtotime($end)) {
            flash_error(t('calendar.flash.ics_range_invalid'));
            $this->redirect(route('calendar.index'));
            return;
        }

        $ics = $this->service->exportEventsAsIcs($userId, $start, $end);
        $safeName = 'calendario_' . date('Ymd_His') . '.ics';

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $ics;
    }

    public function importIcs(): void
    {
        $this->cleanPost([]);

        if (empty($_FILES['ics_file'])) {
            flash_error(t('calendar.flash.select_ics'));
            $this->redirect(route('calendar.index'));
            return;
        }

        $file = $_FILES['ics_file'];
        $name = strtolower((string) ($file['name'] ?? ''));
        $mime = strtolower((string) ($file['type'] ?? ''));

        if (!str_ends_with($name, '.ics')) {
            flash_error(t('calendar.flash.ics_extension'));
            $this->redirect(route('calendar.index'));
            return;
        }

        if ($mime !== '' && !str_contains($mime, 'text/calendar')) {
            flash_error(t('calendar.flash.ics_type'));
            $this->redirect(route('calendar.index'));
            return;
        }

        $user = auth();
        $result = $this->service->importEventsFromIcs($file, (int) $user['id']);

        if ($result['imported'] > 0) {
            flash_success(t('calendar.flash.import_done', ['imported' => $result['imported'], 'skipped' => $result['skipped']]));
        } else {
            flash_error(t('calendar.flash.import_none'));
        }

        if (!empty($result['errors'])) {
            flash_error(implode(' | ', array_slice($result['errors'], 0, 3)));
        }

        $this->redirect(route('calendar.index'));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function validateEvent(array $data): array
    {
        $errors = [];
        $startTs = null;

        if (empty(trim($data['title'] ?? ''))) {
            $errors['title'] = [t('calendar.validation.title_required')];
        } elseif (mb_strlen((string) $data['title']) > 255) {
            $errors['title'] = [t('calendar.validation.title_max')];
        }

        if (empty($data['start_datetime'])) {
            $errors['start_datetime'] = [t('calendar.validation.start_required')];
        } elseif (!strtotime($data['start_datetime'])) {
            $errors['start_datetime'] = [t('calendar.validation.start_invalid')];
        } else {
            $startTs = strtotime($data['start_datetime']);
        }

        if (!empty($data['end_datetime']) && !strtotime($data['end_datetime'])) {
            $errors['end_datetime'] = [t('calendar.validation.end_invalid')];
        } elseif (!empty($data['end_datetime']) && $startTs !== null) {
            if (strtotime($data['end_datetime']) <= $startTs) {
                $errors['end_datetime'] = [t('calendar.validation.end_after_start')];
            }
        }

        if (($data['visibility'] ?? '') === 'role' && empty($data['visible_to_role'])) {
            $errors['visible_to_role'] = [t('calendar.validation.role_required')];
        }

        if (!empty($data['recurrence_rule']) && !$this->service->isSupportedRecurrenceRule($data['recurrence_rule'])) {
            $errors['recurrence_rule'] = [t('calendar.validation.recurrence_invalid')];
        }

        if (!empty($data['recurrence_end']) && !strtotime((string) $data['recurrence_end'])) {
            $errors['recurrence_end'] = [t('calendar.validation.recurrence_end_invalid')];
        }

        if (!empty($data['recurrence_end']) && $startTs !== null) {
            if (strtotime((string) $data['recurrence_end']) <= $startTs) {
                $errors['recurrence_end'] = [t('calendar.validation.recurrence_end_after')];
            }
        }

        return $errors;
    }

    private function getRolesList(): array
    {
        return $this->service->getRolesList();
    }
}
