<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services;

use App\Modules\Contacts\Models\Recurrence;
use App\Modules\Contacts\Repositories\RecurrencesRepository;

class RecurrencesService
{
    private RecurrencesRepository $repo;

    public function __construct(RecurrencesRepository $repo)
    {
        $this->repo = $repo;
    }

    public function allForContatto(int $contattoId): array
    {
        $rows = $this->repo->allForContatto($contattoId);
        return array_map([$this, 'enrich'], $rows);
    }

    public function find(int $id, int $userId): ?array
    {
        $row = $this->repo->findForUser($id, $userId);
        return $row ? $this->enrich($row) : null;
    }

    public function create(array $data, int $contattoId, int $userId): int
    {
        $data['contatto_id'] = $contattoId;
        $data['user_id']     = $userId;
        $data['annuale']     = isset($data['annuale']) ? 1 : 0;
        $data['notifica_giorno_stesso'] = isset($data['notifica_giorno_stesso']) ? 1 : 0;
        $data['promemoria_giorni_prima'] = max(0, (int) ($data['promemoria_giorni_prima'] ?? 7));
        $data['anno_riferimento'] = !empty($data['anno_riferimento']) ? (int) $data['anno_riferimento'] : null;

        return $this->repo->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $data['annuale']     = isset($data['annuale']) ? 1 : 0;
        $data['notifica_giorno_stesso'] = isset($data['notifica_giorno_stesso']) ? 1 : 0;
        $data['promemoria_giorni_prima'] = max(0, (int) ($data['promemoria_giorni_prima'] ?? 7));
        $data['anno_riferimento'] = !empty($data['anno_riferimento']) ? (int) $data['anno_riferimento'] : null;

        return $this->repo->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }

    /**
     * Crea evento calendario per una ricorrenza (se configurato).
     */
    public function sincronizzaCalendario(int $ricId, array $contatto, int $userId): void
    {
        $ric = $this->find($ricId, $userId);
        if (!$ric || ($ric['crea_evento_calendario'] ?? 'no') === 'no') {
            return;
        }

        $reminderService = app(ContactsReminderService::class);
        $eventId = $reminderService->creaEventoCalendario($ric, $contatto, $userId);
        if ($eventId) {
            $this->repo->updateCalendarEventId($ricId, $eventId);
        }
    }

    /**
     * Rimuove evento calendario associato a una ricorrenza.
     */
    public function rimuoviEventoCalendario(array $ric, int $userId): void
    {
        if (!empty($ric['calendario_event_id'])) {
            $reminderService = app(ContactsReminderService::class);
            $reminderService->eliminaEventoCalendario((int) $ric['calendario_event_id'], $userId);
        }
    }

    // ── Calcoli (delegati a Recurrence model) ─────────────────────────────────

    public function enrich(array $ric): array
    {
        return (new Recurrence($ric))->toArray();
    }

    public function calcolaProssimaData(array $ric, \DateTime $today): ?\DateTime
    {
        return (new Recurrence($ric))->calcolaProssimaData($today);
    }
}
