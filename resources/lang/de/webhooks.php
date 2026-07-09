<?php

/**
 * Ausgehende Webhooks — UI-Strings (Deutsch).
 */

return [
    'title'        => 'Webhooks',
    'subtitle'     => 'Benachrichtige externe Systeme bei einem Ereignis, mit HMAC-Signatur und automatischen Wiederholungen.',
    'form_subtitle' => 'Konfiguriere die Ziel-URL und die zu abonnierenden Ereignisse.',
    'list_title'   => 'Konfigurierte Endpunkte',
    'empty'        => 'Keine Webhook-Endpunkte konfiguriert.',
    'back'         => 'Zurück',
    'cancel'       => 'Abbrechen',
    'save'         => 'Speichern',

    'create_title' => 'Neuer Webhook',
    'edit_title'   => 'Webhook bearbeiten',
    'test_cta'     => 'Test senden',
    'delete'       => 'Löschen',
    'delete_confirm' => 'Diesen Webhook-Endpunkt löschen? Wartende Zustellungen werden entfernt.',
    'active'       => 'Aktiv',
    'inactive'     => 'Inaktiv',

    'stat_pending' => 'In Warteschlange',
    'stat_sent'    => 'Zugestellt',
    'stat_failed'  => 'Fehlgeschlagen',

    'col_url'      => 'URL',
    'col_events'   => 'Ereignisse',
    'col_status'   => 'Status',
    'col_event'    => 'Ereignis',
    'col_attempts' => 'Versuche',
    'col_response' => 'Antwort',
    'col_created'  => 'Erstellt',

    'field_url'          => 'Ziel-URL',
    'field_url_hint'     => 'Nur HTTPS. Private oder Loopback-Adressen werden blockiert (Anti-SSRF).',
    'field_description'  => 'Beschreibung (optional)',
    'field_active'       => 'Endpunkt aktiv',
    'field_events'       => 'Abonnierte Ereignisse',
    'field_events_hint'  => 'Der Endpunkt erhält bei jedem ausgewählten Ereignis eine signierte POST-Anfrage.',
    'no_events'          => 'Keine Ereignisse verfügbar.',

    'secret_once_title'  => 'Signatur-Secret generiert',
    'secret_once_hint'   => 'Kopiere es jetzt: Es wird nicht erneut angezeigt. Verwende es zur Prüfung des X-Favilla-Signature-Headers (HMAC-SHA256 des Bodys).',
    'secret_section'     => 'Signatur-Secret',
    'secret_section_hint' => 'Regeneriere das Secret, wenn du es für kompromittiert hältst. Das alte Secret funktioniert sofort nicht mehr.',
    'secret_regenerate'  => 'Secret neu generieren',
    'secret_regenerate_confirm' => 'Secret neu generieren? Mit dem aktuellen berechnete Signaturen sind dann ungültig.',

    'deliveries_title' => 'Zustellprotokoll',
    'deliveries_empty' => 'Keine Zustellungen für diesen Endpunkt erfasst.',

    'flash_created'   => 'Webhook-Endpunkt erstellt.',
    'flash_updated'   => 'Webhook-Endpunkt aktualisiert.',
    'flash_deleted'   => 'Webhook-Endpunkt gelöscht.',
    'flash_not_found' => 'Endpunkt nicht gefunden.',
    'flash_secret_regenerated' => 'Secret neu generiert.',
    'flash_test_ok'     => 'Testzustellung abgeschlossen.',
    'flash_test_failed' => 'Testzustellung fehlgeschlagen:',
];
