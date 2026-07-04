<?php

declare(strict_types=1);

namespace App\Modules\Calendar\Services;

use App\Modules\Notifications\Services\NotificationService;
use PDO;

/**
 * Processa i promemoria degli eventi calendario.
 * Viene chiamato dallo scheduler (calendar:send-reminders).
 */
class CalendarReminderService
{
    /**
     * Trova tutti gli eventi con reminder scaduto e invia la notifica.
     * Segna reminder_sent_at per evitare invii doppi.
     *
     * @return int Numero di notifiche inviate
     */
    public function sendDueReminders(): int
    {
        $pdo  = app(PDO::class);
        $sent = 0;

        // Eventi il cui promemoria è scaduto ma non ancora inviato
        $stmt = $pdo->prepare('
            SELECT e.id, e.title, e.start_datetime, e.location,
                   e.visibility, e.visible_to_role, e.reminder_minutes,
                   e.created_by,
                   r.slug AS role_slug
            FROM calendar_events e
            LEFT JOIN roles r ON r.id = e.visible_to_role
            WHERE e.reminder_minutes IS NOT NULL
              AND e.deleted_at IS NULL
              AND e.reminder_sent_at IS NULL
              AND e.start_datetime > NOW()
              AND DATE_SUB(e.start_datetime, INTERVAL e.reminder_minutes MINUTE) <= NOW()
        ');
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as $event) {
            $context = [
                'event_id'      => (int) $event['id'],
                'event_title'   => (string) $event['title'],
                'start_label'   => format_date_it($event['start_datetime'], 'compact'),
                'location'      => (string) ($event['location'] ?? ''),
                'minutes_before' => (int) $event['reminder_minutes'],
            ];
            try {
                $link = route('calendar.show', ['id' => $event['id']]);
            } catch (\Throwable) {
                $link = null;
            }

            try {
                if ($event['visibility'] === 'role' && $event['role_slug']) {
                    NotificationService::dispatchEventToRole(
                        'calendar.event_reminder',
                        'Calendar',
                        $event['role_slug'],
                        $context,
                        $link,
                        $event['created_by']
                    );
                } else {
                    // personal o public: notifica al creatore
                    if ($event['created_by']) {
                        NotificationService::dispatchEventToUser(
                            'calendar.event_reminder',
                            'Calendar',
                            (int) $event['created_by'],
                            $context,
                            $link,
                            null
                        );
                    }
                }

                // Segna come inviato
                $upd = $pdo->prepare(
                    'UPDATE calendar_events SET reminder_sent_at = NOW() WHERE id = ?'
                );
                $upd->execute([$event['id']]);
                $sent++;
            } catch (\Throwable $e) {
                // Non bloccare gli altri eventi
            }
        }

        return $sent;
    }
}
