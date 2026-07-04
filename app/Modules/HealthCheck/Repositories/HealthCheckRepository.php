<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Repositories;

use App\Repositories\BaseRepository;

class HealthCheckRepository extends BaseRepository
{
    protected string $table = 'healthcheck_runs';
    protected array $fillable = ['total_ok', 'total_warn', 'total_fail', 'data', 'created_by'];
    protected bool $timestamps = false; // created_at gestito dal DB

    /**
     * Ultimi N run ordinati per data decrescente.
     */
    public function getHistory(int $limit = 20, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;

        $total = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM {$this->table}"
        )->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT hr.*, u.name AS user_name
             FROM {$this->table} hr
             LEFT JOIN users u ON u.id = hr.created_by
             ORDER BY hr.created_at DESC, hr.id DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'lastPage' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Ultimo run eseguito.
     */
    public function getLastRun(): ?array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC, id DESC LIMIT 1"
        );
        return $stmt->fetch() ?: null;
    }
}
