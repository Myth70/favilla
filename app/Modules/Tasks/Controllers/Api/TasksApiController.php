<?php

declare(strict_types=1);

namespace App\Modules\Tasks\Controllers\Api;

use App\Modules\Api\Http\ApiController;
use App\Modules\Tasks\Services\TasksService;

/**
 * API v1 — Attività personali. Serializzatore JSON sopra TasksService (stessa
 * logica di dominio della UI web). Ogni azione richiede lo scope/permesso
 * corrispondente; la proprietà è già garantita dal Service (scoping per user_id).
 */
class TasksApiController extends ApiController
{
    private TasksService $tasks;

    public function __construct()
    {
        $this->tasks = app(TasksService::class);
    }

    public function index(): void
    {
        $this->requireScope('tasks.view');

        $page = $this->queryInt('page', 1, 1, 100000);
        $filters = [
            'page'   => $page,
            'q'      => isset($_GET['q']) ? (string) $_GET['q'] : null,
            'status' => isset($_GET['status']) ? (string) $_GET['status'] : null,
            'sort'   => isset($_GET['sort']) ? (string) $_GET['sort'] : null,
            'dir'    => isset($_GET['dir']) ? (string) $_GET['dir'] : null,
        ];

        $result = $this->tasks->list($this->userId(), array_filter($filters, static fn ($v) => $v !== null));

        $items = array_map([$this, 'serialize'], $result['data'] ?? []);
        $total = (int) ($result['total'] ?? count($items));
        $perPage = (int) ($result['per_page'] ?? 15);
        $this->paginated($items, (int) ($result['page'] ?? $page), $perPage, $total);
    }

    public function show(string $id): void
    {
        $this->requireScope('tasks.view');

        $task = $this->tasks->find((int) $id, $this->userId());
        if ($task === null) {
            $this->fail('not_found', 'Task not found.', 404);
            return;
        }
        $this->ok($this->serialize($task));
    }

    public function store(): void
    {
        $this->requireScope('tasks.create');

        $input = $this->input();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $this->fail('validation_failed', 'Validation failed.', 422, ['title' => ['required']]);
            return;
        }
        $invalid = $this->invalidEnums($input);
        if ($invalid !== []) {
            $this->fail('validation_failed', 'Validation failed.', 422, $invalid);
            return;
        }

        $id = $this->tasks->create($this->cleanTaskData($input, true), $this->userId());
        $task = $this->tasks->find($id, $this->userId());
        $this->ok($task !== null ? $this->serialize($task) : ['id' => $id], null, 201);
    }

    public function update(string $id): void
    {
        $this->requireScope('tasks.edit');

        // Verifica esistenza/proprietà PRIMA: distingue il 404 dagli errori interni
        // e non rimanda al client i messaggi di eccezione del Service.
        $existing = $this->tasks->find((int) $id, $this->userId());
        if ($existing === null) {
            $this->fail('not_found', 'Task not found.', 404);
            return;
        }

        $input = $this->input();
        $invalid = $this->invalidEnums($input);
        if ($invalid !== []) {
            $this->fail('validation_failed', 'Validation failed.', 422, $invalid);
            return;
        }

        try {
            $ok = $this->tasks->update((int) $id, $this->cleanTaskData($input, false), $this->userId());
        } catch (\RuntimeException) {
            $this->fail('update_failed', 'Update failed.', 400);
            return;
        }

        if (!$ok) {
            $this->fail('update_failed', 'Update failed.', 400);
            return;
        }
        $task = $this->tasks->find((int) $id, $this->userId());
        $this->ok($task !== null ? $this->serialize($task) : ['id' => (int) $id]);
    }

    public function destroy(string $id): void
    {
        $this->requireScope('tasks.delete');

        if ($this->tasks->find((int) $id, $this->userId()) === null) {
            $this->fail('not_found', 'Task not found.', 404);
            return;
        }

        try {
            $this->tasks->delete((int) $id, $this->userId());
        } catch (\RuntimeException) {
            $this->fail('delete_failed', 'Delete failed.', 400);
            return;
        }
        $this->ok(['deleted' => true]);
    }

    private const ALLOWED_STATUS = ['backlog', 'todo', 'in_progress', 'review', 'done'];
    private const ALLOWED_PRIORITY = ['low', 'medium', 'high', 'urgent'];

    /**
     * Valida gli enum in ingresso: un valore fuori whitelist è un errore 422,
     * non va scartato silenziosamente (altrimenti il client crede sia stato
     * accettato e trova un default).
     *
     * @param array<string, mixed> $input
     * @return array<string, string[]> details per l'envelope (vuoto = ok)
     */
    private function invalidEnums(array $input): array
    {
        $details = [];
        if (isset($input['status']) && !in_array($input['status'], self::ALLOWED_STATUS, true)) {
            $details['status'] = ['invalid'];
        }
        if (isset($input['priority']) && !in_array($input['priority'], self::ALLOWED_PRIORITY, true)) {
            $details['priority'] = ['invalid'];
        }
        return $details;
    }

    /**
     * Whitelist dei campi accettati dall'API (evita mass-assignment di colonne
     * interne come position/completed_at). Gli enum sono già validati a monte da
     * invalidEnums(); qui restano solo i valori consentiti.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function cleanTaskData(array $input, bool $isCreate): array
    {
        $allowedStatus = self::ALLOWED_STATUS;
        $allowedPriority = self::ALLOWED_PRIORITY;
        $data = [];

        if (array_key_exists('title', $input)) {
            $data['title'] = mb_substr(trim((string) $input['title']), 0, 255);
        }
        if (array_key_exists('description', $input)) {
            $data['description'] = (string) $input['description'];
        }
        if (isset($input['status']) && in_array($input['status'], $allowedStatus, true)) {
            $data['status'] = (string) $input['status'];
        } elseif ($isCreate) {
            $data['status'] = 'todo';
        }
        if (isset($input['priority']) && in_array($input['priority'], $allowedPriority, true)) {
            $data['priority'] = (string) $input['priority'];
        }
        if (array_key_exists('due_date', $input)) {
            $data['due_date'] = $input['due_date'] !== '' ? (string) $input['due_date'] : null;
        }
        return $data;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function serialize(array $task): array
    {
        return [
            'id'          => (int) $task['id'],
            'title'       => $task['title'] ?? '',
            'description' => $task['description'] ?? null,
            'status'      => $task['status'] ?? 'todo',
            'priority'    => $task['priority'] ?? 'medium',
            'due_date'    => $task['due_date'] ?? null,
            'completed_at' => $task['completed_at'] ?? null,
            'created_at'  => $task['created_at'] ?? null,
            'updated_at'  => $task['updated_at'] ?? null,
        ];
    }
}
