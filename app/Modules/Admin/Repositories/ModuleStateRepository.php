<?php

declare(strict_types=1);

namespace App\Modules\Admin\Repositories;

use PDO;

/**
 * Gestisce la tabella module_states (PK = name, senza id auto-increment).
 * Non estende BaseRepository per incompatibilità con la PK composita.
 */
class ModuleStateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = app(PDO::class);
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_states WHERE name = ?');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Aggiorna se il record esiste, altrimenti lo inserisce.
     */
    public function upsert(string $name, int $enabled, int $testing, ?int $updatedBy): void
    {
        if ($this->findByName($name)) {
            $this->pdo->prepare(
                'UPDATE module_states SET enabled = ?, testing = ?, updated_by = ? WHERE name = ?'
            )->execute([$enabled, $testing, $updatedBy, $name]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO module_states (name, enabled, testing, updated_by) VALUES (?, ?, ?, ?)'
            )->execute([$name, $enabled, $testing, $updatedBy]);
        }
    }
}
