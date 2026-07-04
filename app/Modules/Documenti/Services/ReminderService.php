<?php

declare(strict_types=1);

namespace App\Modules\Documenti\Services;

use App\Modules\Documenti\Repositories\DocumentoRepository;
use App\Modules\Notifications\Services\NotificationService;

/**
 * Invia reminder di scadenza multi-stadio in modo idempotente.
 * Modellato su CalendarioReminderService.
 */
class ReminderService
{
    private DocumentoRepository       $docRepo;
    private DocumentiRecipientService $recipientSvc;

    public function __construct()
    {
        $this->docRepo      = app(DocumentoRepository::class);
        $this->recipientSvc = app(DocumentiRecipientService::class);
    }

    /**
     * Default stages (giorni di anticipo reminder).
     *
     * Override opzionale via app_settings chiave
     *   `documenti.reminder.default_stages` (JSON array di interi 1..365), es.:
     *   INSERT INTO app_settings (`key`, `value`, `type`, `group`, `label`)
     *   VALUES ('documenti.reminder.default_stages', '[60,30,7,1]', 'json',
     *           'documenti', 'Reminder scadenza: anticipi default');
     *
     * Se l'impostazione non esiste o è invalida, viene usato il fallback
     * [30, 14, 7, 3, 1].
     *
     * @return list<int>
     */
    private static function loadDefaultStages(): array
    {
        try {
            $pdo  = app(\PDO::class);
            $stmt = $pdo->prepare(
                "SELECT `value` FROM app_settings WHERE `key` = 'documenti.reminder.default_stages' LIMIT 1"
            );
            $stmt->execute();
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $decoded = json_decode((string) $raw, true);
                if (is_array($decoded) && !empty($decoded)) {
                    $clean = array_values(array_filter(
                        array_map('intval', $decoded),
                        static fn (int $n) => $n >= 1 && $n <= 365
                    ));
                    if (!empty($clean)) {
                        return $clean;
                    }
                }
            }
        } catch (\Throwable) {
            // app_settings potrebbe non esistere — fallback
        }
        return [30, 14, 7, 3, 1];
    }

    /**
     * Invia i reminder in scadenza. Ritorna il numero di reminder inviati.
     */
    public function sendDueReminders(): int
    {
        $documenti = $this->docRepo->duePendingReminders();
        $sent = 0;

        foreach ($documenti as $doc) {
            try {
                $sent += $this->processDocumento($doc);
            } catch (\Throwable $e) {
                error_log('[ReminderService] Errore documento ' . $doc['id'] . ': ' . $e->getMessage());
            }
        }

        return $sent;
    }

    private function processDocumento(array $doc): int
    {
        $scadeIl = $doc['scade_il'] ? strtotime($doc['scade_il']) : null;
        if (!$scadeIl) {
            return 0;
        }

        $giorniAllaScadenza = (int) ceil(($scadeIl - time()) / 86400);

        // Leggi i giorni di reminder (documento > categoria > default)
        $reminderGiorni = $doc['reminder_giorni']
            ? json_decode($doc['reminder_giorni'], true)
            : null;

        if (!$reminderGiorni && !empty($doc['reminder_giorni_default'])) {
            $reminderGiorni = json_decode($doc['reminder_giorni_default'], true);
        }

        if (!is_array($reminderGiorni) || empty($reminderGiorni)) {
            $reminderGiorni = self::loadDefaultStages();
        }

        rsort($reminderGiorni); // ordine decrescente: 30, 14, 7...

        // Determina lo stadio corrente (indice dell'array)
        $nuovoStadio = -1;
        foreach ($reminderGiorni as $idx => $giorni) {
            if ($giorniAllaScadenza <= $giorni) {
                $nuovoStadio = $idx;
            }
        }

        if ($nuovoStadio < 0) {
            return 0; // Non ancora in range
        }

        // `reminder_stage_inviato` è un sentinel "indice+1" (0 = nessun reminder inviato):
        // sotto viene salvato come $nuovoStadio + 1. Il confronto va quindi fatto sullo stesso
        // spazio +1, altrimenti il primo stadio (indice 0) non parte mai e gli stadi successivi
        // vengono saltati a coppie (off-by-one).
        $stadioInviato = (int) ($doc['reminder_stage_inviato'] ?? 0);

        if ($nuovoStadio + 1 <= $stadioInviato) {
            return 0; // Già inviato per questo stadio (o uno più urgente)
        }

        // Invia
        $context = [
            'documento_id'        => $doc['id'],
            'documento_titolo'    => $doc['titolo'],
            'scade_il'            => date('d/m/Y', $scadeIl),
            'giorni_alla_scadenza' => max(0, $giorniAllaScadenza),
        ];
        $link = route('documenti.show', ['id' => $doc['id']]);

        $destinatari = [(int) $doc['owner_user_id']];
        $extra = $this->recipientSvc->resolveExtra($doc['reminder_destinatari_extra'] ?? null);
        $destinatari = array_unique(array_merge($destinatari, $extra));

        $inviatoCnt = 0;
        foreach ($destinatari as $userId) {
            try {
                NotificationService::dispatchEventToUser('documenti.in_scadenza', 'Documenti', $userId, $context, $link);
                $inviatoCnt++;
            } catch (\Throwable) {
            }
        }

        // Aggiorna stage inviato
        $this->docRepo->update((int) $doc['id'], [
            'reminder_stage_inviato'   => $nuovoStadio + 1,
            'reminder_ultimo_invio_at' => date('Y-m-d H:i:s'),
        ]);

        return $inviatoCnt;
    }
}
