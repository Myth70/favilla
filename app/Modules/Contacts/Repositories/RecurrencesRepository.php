<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Repositories;

use App\Repositories\BaseRepository;

class RecurrencesRepository extends BaseRepository
{
    protected string $table = 'contact_recurrences';

    protected array $fillable = [
        'contatto_id', 'user_id', 'tipo', 'titolo', 'data_ricorrenza',
        'annuale', 'anno_riferimento', 'promemoria_giorni_prima',
        'notifica_giorno_stesso', 'crea_evento_calendario',
        'calendario_event_id', 'note',
    ];

    protected bool $timestamps = true;

    public function allForContatto(int $contattoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table}
             WHERE contatto_id = ?
             ORDER BY MONTH(data_ricorrenza), DAY(data_ricorrenza)"
        );
        $stmt->execute([$contattoId]);
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

    public function allForUserWithContatto(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.*, c.nome, c.cognome, c.avatar
             FROM {$this->table} r
             JOIN contacts c ON c.id = r.contatto_id
             WHERE r.user_id = ?
             ORDER BY MONTH(r.data_ricorrenza), DAY(r.data_ricorrenza)"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function updateLastNotified(int $id, int $year): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET last_notified_year = ? WHERE id = ?"
        );
        $stmt->execute([$year, $id]);
    }

    public function updateCalendarEventId(int $id, ?int $eventId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET calendario_event_id = ? WHERE id = ?"
        );
        $stmt->execute([$eventId, $id]);
    }
}
