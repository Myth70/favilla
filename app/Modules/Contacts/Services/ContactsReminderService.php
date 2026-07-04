<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Services;

use App\Modules\Contacts\Repositories\RecurrencesRepository;
use App\Modules\Notifications\Services\NotificationService;

class ContactsReminderService
{
    private RecurrencesRepository $ricRepo;
    private RecurrencesService    $ricService;

    public function __construct(RecurrencesRepository $ricRepo, RecurrencesService $ricService)
    {
        $this->ricRepo    = $ricRepo;
        $this->ricService = $ricService;
    }

    /**
     * Processa tutti i reminder per un utente.
     * Ritorna il numero di notifiche inviate.
     */
    public function processForUser(int $userId): int
    {
        $today       = new \DateTime('today');
        $currentYear = (int) $today->format('Y');
        $sent        = 0;

        $ricorrenze = $this->ricRepo->allForUserWithContatto($userId);

        foreach ($ricorrenze as $ric) {
            $prossima = $this->ricService->calcolaProssimaData($ric, $today);
            if ($prossima === null) {
                continue;
            }

            $giorni = (int) $today->diff($prossima)->format('%r%a');

            // Deduplicazione: non notificare due volte nello stesso anno
            $alreadyNotified = (int) ($ric['last_notified_year'] ?? 0) === $currentYear;

            $nomeContatto = trim($ric['nome'] . ' ' . ($ric['cognome'] ?? ''));
            $notified     = false;

            // Notifica anticipo
            if (!$alreadyNotified
                && (int) $ric['promemoria_giorni_prima'] > 0
                && $giorni === (int) $ric['promemoria_giorni_prima']
            ) {
                $this->sendNotifica($userId, $ric, $nomeContatto, $prossima, $giorni);
                $notified = true;
            }

            // Notifica giorno stesso
            if (!$alreadyNotified && $ric['notifica_giorno_stesso'] && $giorni === 0) {
                // Il giorno stesso la inviamo sempre (anche se già inviato l'anticipo)
                $this->sendNotifica($userId, $ric, $nomeContatto, $prossima, 0);
                $notified = true;
            }

            if ($notified) {
                $this->ricRepo->updateLastNotified($ric['id'], $currentYear);
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Prossime ricorrenze entro N giorni (per il banner sull'index).
     */
    public function getProssime(int $userId, int $days = 30): array
    {
        $today      = new \DateTime('today');
        $ricorrenze = $this->ricRepo->allForUserWithContatto($userId);
        $result     = [];

        foreach ($ricorrenze as $ric) {
            $enriched = $this->ricService->enrich($ric);
            $giorni   = $enriched['giorni_mancanti'];
            if ($giorni !== null && $giorni >= 0 && $giorni <= $days) {
                $result[] = $enriched;
            }
        }

        usort($result, fn ($a, $b) => $a['giorni_mancanti'] <=> $b['giorni_mancanti']);
        return $result;
    }

    // ── Integrazione Calendario ──────────────────────────────────────────────

    /**
     * Crea un evento in calendar_events per una ricorrenza.
     * Silenzioso in caso di errore (tabella o schema diverso).
     */
    public function creaEventoCalendario(array $ric, array $contatto, int $userId): ?int
    {
        try {
            $pdo   = app(\PDO::class);
            $today = new \DateTime('today');
            $base  = \DateTime::createFromFormat('Y-m-d', $ric['data_ricorrenza']);
            if (!$base) {
                return null;
            }

            $nomeContatto = trim($contatto['nome'] . ' ' . ($contatto['cognome'] ?? ''));
            $title        = $ric['titolo'] . ' — ' . $nomeContatto;

            $eventDate = clone $today;
            $eventDate->setDate(
                (int) $today->format('Y'),
                (int) $base->format('m'),
                (int) $base->format('d')
            );
            if ($eventDate < $today) {
                $eventDate->modify('+1 year');
            }
            $dateStr = $eventDate->format('Y-m-d');

            $recurrenceRule = $ric['crea_evento_calendario'] === 'annuale'
                ? 'FREQ=MONTHLY;INTERVAL=12'
                : null;

            $icone   = ['compleanno' => '🎂', 'anniversario' => '💍', 'evento' => '📅'];
            $icona   = $icone[$ric['tipo']] ?? '📅';
            $titoloCal = $icona . ' ' . $title;

            $stmt = $pdo->prepare(
                "INSERT INTO calendar_events
                    (title, description, start_datetime, end_datetime, all_day, color, recurrence_rule, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, '#3b82f6', ?, ?, NOW(), NOW())"
            );
            $stmt->execute([$titoloCal, $ric['note'] ?? '', $dateStr, $dateStr, $recurrenceRule, $userId]);
            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function eliminaEventoCalendario(?int $eventId, int $userId): void
    {
        if (!$eventId) {
            return;
        }
        try {
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare('DELETE FROM calendar_events WHERE id = ? AND created_by = ?');
            $stmt->execute([$eventId, $userId]);
        } catch (\Throwable $e) {
            app_log('error', self::class . ': delete calendar event failed: ' . $e->getMessage());
        }
    }

    // ── Privato ──────────────────────────────────────────────────────────────

    private function sendNotifica(
        int       $userId,
        array     $ric,
        string    $nomeContatto,
        \DateTime $data,
        int       $giorni
    ): void {
        $icone = ['compleanno' => '🎂', 'anniversario' => '💍', 'evento' => '📅'];
        $icona = $icone[$ric['tipo']] ?? '📅';
        $dataF = $data->format('d/m/Y');

        try {
            NotificationService::dispatchEventToUser(
                'contacts.reminder_due',
                'Contacts',
                $userId,
                [
                    'contatto_id'   => (int) $ric['contatto_id'],
                    'ricorrenza_id' => (int) $ric['id'],
                    'tipo'          => $ric['tipo'],
                    'icona'         => $icona,
                    'titolo'        => (string) $ric['titolo'],
                    'contatto_nome' => $nomeContatto,
                    'data_label'    => $dataF,
                    'giorni'        => $giorni,
                ],
                route('contacts.show', ['id' => $ric['contatto_id']]),
                null
            );
        } catch (\Throwable $e) {
            app_log('error', self::class . ': dispatch reminder notification failed: ' . $e->getMessage());
        }
    }
}
