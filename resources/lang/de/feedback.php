<?php

/**
 * Feedback module — German.
 */
return [
    'admin_title'    => 'Meldungen',
    'admin_subtitle' => 'Von Benutzern eingereichte Fehler und Funktionswünsche, mit technischem Kontext und Triage.',
    'report_title'   => 'Ein Problem melden',

    'tipi' => [
        'bug'          => 'Fehler',
        'funzionalita' => 'Funktion',
        'domanda'      => 'Frage',
    ],
    'severita' => [
        'bassa'   => 'Niedrig',
        'media'   => 'Mittel',
        'alta'    => 'Hoch',
        'critica' => 'Kritisch',
    ],
    'stati' => [
        'nuova'           => 'Neu',
        'in_lavorazione'  => 'In Bearbeitung',
        'risolta'         => 'Gelöst',
        'chiusa'          => 'Geschlossen',
        'non_risolvibile' => 'Nicht lösbar',
    ],

    'form' => [
        'tipo'              => 'Typ',
        'severita'          => 'Schweregrad',
        'titolo'            => 'Titel',
        'optional'          => '(optional)',
        'titolo_placeholder' => 'Kurze Zusammenfassung',
        'titolo_placeholder_long' => 'Kurze Zusammenfassung des Problems',
        'what_happened'     => 'Was ist passiert?',
        'descr_placeholder' => 'Beschreiben Sie das aufgetretene Problem...',
        'descr_placeholder_long' => 'Beschreiben Sie das Problem oder die Funktion, die sich nicht wie erwartet verhält...',
        'descr_invalid'     => 'Geben Sie eine Beschreibung ein.',
        'steps'             => 'Schritte zur Reproduktion',
        'steps_placeholder' => '1) ... 2) ... 3) ...',
        'steps_placeholder_long' => '1) Gehen Sie zu... 2) Klicken Sie auf... 3) Es passiert...',
        'submit'            => 'Meldung senden',
    ],

    'report' => [
        'warning'      => 'Sie melden ein Problem',
        'error_code'   => '(Fehler :code)',
        'on_page'      => 'auf der Seite:',
        'intro'        => 'Beschreiben Sie, was passiert ist. Wir hängen automatisch die Seitenadresse und serverseitige Umgebungsdaten an, um das Problem zu reproduzieren.',
    ],

    'launcher' => [
        'intro'           => 'Beschreiben Sie, was nicht funktioniert: Die technische Umgebung (Seite, Modul, Fehler, Aktionsfolge) wird automatisch angehängt, um das Problem zu reproduzieren.',
        'attached_label'  => 'Was angehängt wird',
    ],

    'filters' => [
        'search'             => 'Suchen',
        'search_placeholder' => 'Titel, Beschreibung, Code...',
        'stato'              => 'Status',
        'tipo'               => 'Typ',
        'severita'           => 'Schweregrad',
        'modulo'             => 'Modul',
        'all_m'              => 'Alle',
        'all_f'              => 'Alle',
    ],

    'table' => [
        'col_code'    => 'Code',
        'col_tipo'    => 'Typ',
        'col_severita' => 'Schweregrad',
        'col_stato'   => 'Status',
        'col_modulo'  => 'Modul',
        'col_titolo'  => 'Titel',
        'col_autore'  => 'Autor',
        'col_data'    => 'Datum',
        'empty'       => 'Keine Meldungen gefunden.',
        'open_detail' => 'Detail öffnen',
        'label'       => 'Meldungen',
    ],

    'detail' => [
        'copy_llm'         => 'Für LLM kopieren',
        'list'             => 'Liste',
        'severity_prefix'  => 'Schweregrad:',
        'subtitle'         => 'Meldung vom Typ <strong>:type</strong> · Status <strong>:status</strong>',
        'description'      => 'Beschreibung',
        'steps'            => 'Schritte zur Reproduktion',
        'captured_errors'  => 'Erfasste Fehler',
        'no_errors'        => 'Kein JS/HTMX-Fehler während der Sitzung erfasst.',
        'action_sequence'  => 'Aktionsfolge (automatischer Verlauf)',
        'no_interactions'  => 'Keine Interaktion aufgezeichnet.',
        'crumb_nav'        => 'Navigation →',
        'crumb_click'      => 'Klick auf',
        'dom_available'    => 'DOM-Snapshot verfügbar',
        'dom_desc'         => 'HTML der Seite zum Zeitpunkt der Meldung (Eingaben maskiert, Skripte entfernt). Herunterladen und lokal öffnen &mdash; es wird nicht im App-Kontext ausgeführt.',
        'download_dom'     => 'DOM herunterladen',
        'dom_deleted'      => 'DOM-Snapshot beim Schließen der Meldung gelöscht (Datenminimierung).',
        'full_context'     => 'Vollständiger Kontext (JSON)',
        'show_hide_json'   => 'Rohes JSON ein-/ausblenden',
        'environment'      => 'Umgebung',
        'management'       => 'Verwaltung',
        'assigned_to'      => 'Zugewiesen an',
        'not_assigned'     => '— Nicht zugewiesen —',
        'admin_notes'      => 'Admin-Notizen',
        'delete'           => 'Löschen',
        'delete_desc'      => 'Das Löschen ist aus der Datenbank reversibel (Soft Delete), aber die Meldung verschwindet aus der Konsole.',
        'delete_confirm'   => 'Meldung :ref löschen?',
        'delete_btn'       => 'Meldung löschen',
    ],
    'env' => [
        'autore'       => 'Autor',
        'ruoli'        => 'Rollen',
        'data'         => 'Datum',
        'app_version'  => 'App-Version',
        'php'          => 'PHP',
        'ip'           => 'IP',
        'modulo'       => 'Modul',
        'route'        => 'Route',
        'viewport'     => 'Viewport',
        'lingua'       => 'Sprache',
        'user_agent'   => 'User Agent',
    ],

    'flash' => [
        'save_error'   => 'Fehler beim Speichern der Meldung.',
        'sent'         => 'Meldung gesendet. Danke! Referenz: :ref',
        'not_found'    => 'Meldung nicht gefunden.',
        'updated'      => 'Meldung aktualisiert.',
        'update_failed' => 'Aktualisierung fehlgeschlagen.',
        'deleted'      => 'Meldung gelöscht.',
        'dom_unavailable' => 'DOM-Snapshot nicht verfügbar.',
    ],

    'widget' => [
        'label'    => 'Offene Meldungen',
        'new_sub'  => ':count neue zu sichten',
        'none_new' => 'Keine neue',
    ],
];
