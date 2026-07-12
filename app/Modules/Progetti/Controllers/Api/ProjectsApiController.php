<?php

declare(strict_types=1);

namespace App\Modules\Progetti\Controllers\Api;

use App\Modules\Api\Http\ApiController;
use App\Modules\Progetti\Services\ProgettiService;

/**
 * API v1 — Progetti (sola lettura). Riusa ProgettiService: lo scoping è lo
 * stesso della UI web (owner o membro; progetti.view_all/manage_all vedono
 * tutto), ma risolto dai permessi del token invece che dalla sessione.
 */
class ProjectsApiController extends ApiController
{
    private const ALLOWED_STATUS = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];

    private ProgettiService $projects;

    public function __construct()
    {
        $this->projects = app(ProgettiService::class);
    }

    public function index(): void
    {
        $this->requireScope('progetti.view');

        $filters = ['page' => $this->queryInt('page', 1, 1, 100000)];
        if (isset($_GET['q']) && $_GET['q'] !== '') {
            $filters['q'] = (string) $_GET['q'];
        }
        if (isset($_GET['status'])) {
            if (!in_array($_GET['status'], self::ALLOWED_STATUS, true)) {
                $this->fail('validation_failed', 'Validation failed.', 422, ['status' => ['invalid']]);
                return;
            }
            $filters['status'] = (string) $_GET['status'];
        }

        $result = $this->projects->listForUser($this->userId(), $filters, $this->canViewAll());

        $items = array_map([$this, 'serialize'], $result['items'] ?? []);
        $this->paginated(
            $items,
            (int) ($result['page'] ?? 1),
            (int) ($result['per_page'] ?? 20),
            (int) ($result['total'] ?? count($items))
        );
    }

    public function show(string $id): void
    {
        $this->requireScope('progetti.view');

        $project = $this->projects->findForUser((int) $id, $this->userId(), $this->canViewAll());
        if ($project === null) {
            $this->fail('not_found', 'Project not found.', 404);
            return;
        }
        $this->ok($this->serialize($project));
    }

    /**
     * Stessa semantica di ProgettiService::canViewAll(), ma sui permessi del
     * token (gate = min(permessi utente, scope)).
     */
    private function canViewAll(): bool
    {
        return $this->context()->can('progetti.view_all') || $this->context()->can('progetti.manage_all');
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    private function serialize(array $project): array
    {
        return [
            'id'              => (int) $project['id'],
            'name'            => $project['name'] ?? '',
            'code'            => $project['code'] ?? null,
            'description'     => $project['description'] ?? null,
            'client_name'     => $project['client_name'] ?? null,
            'status'          => $project['status'] ?? 'planning',
            'start_date'      => $project['start_date'] ?? null,
            'end_date'        => $project['end_date'] ?? null,
            'estimated_hours' => (float) ($project['estimated_hours'] ?? 0),
            'budget_planned'  => (float) ($project['budget_planned'] ?? 0),
            'progress'        => (float) ($project['progress_cached'] ?? 0),
            'owner_user_id'   => (int) ($project['owner_user_id'] ?? 0),
            'created_at'      => $project['created_at'] ?? null,
            'updated_at'      => $project['updated_at'] ?? null,
        ];
    }
}
