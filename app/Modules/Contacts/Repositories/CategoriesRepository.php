<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Repositories;

use App\Repositories\BaseRepository;

class CategoriesRepository extends BaseRepository
{
    protected string $table = 'contact_categories';
    protected array  $fillable  = ['user_id', 'nome', 'colore'];
    protected bool   $timestamps = true;

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cat.*,
                    COUNT(c.id) AS totale_contatti
             FROM {$this->table} cat
             LEFT JOIN contacts c ON c.categoria_id = cat.id AND c.user_id = ?
             WHERE cat.user_id = ?
             GROUP BY cat.id
             ORDER BY cat.nome ASC"
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public function findForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }
}
