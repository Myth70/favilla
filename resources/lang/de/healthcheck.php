<?php

/**
 * HealthCheck module — German.
 */
return [
    'title'         => 'Systemzustand',
    'subtitle'      => 'Überwachung des System- und Dienststatus',
    'history_title' => 'Systemzustand-Verlauf',
    'breadcrumb_history' => 'Verlauf',

    'buttons' => [
        'history'       => 'Verlauf',
        'export_csv'    => 'CSV exportieren',
        'deep_scan'     => 'Tiefenscan',
        'refresh'       => 'Aktualisieren',
        'back_to_check' => 'Zurück zum Check',
    ],
    'tooltip' => [
        'deep_scan' => 'Führt auch die Tiefenprüfungen aus (E-Mail-DNS, .env-Exposition, Abhängigkeits-Schwachstellen)',
    ],

    'loading' => 'Prüfungen werden ausgeführt…',

    'content' => [
        'deep_scan'    => 'Tiefenscan',
        'quick_checks' => 'Schnellprüfungen — verwenden Sie „Tiefenscan" für E-Mail-DNS, .env-Exposition und Abhängigkeits-Schwachstellen',
        'executed_at'  => 'Ausgeführt am :date',
        'date_at'      => 'um',
        'all_ok'       => 'Alle Prüfungen sind in Ordnung. Keine Aktion erforderlich.',
    ],

    'card' => [
        'status_critical' => 'Kritische Probleme erkannt',
        'status_warn'     => 'Zu prüfen',
        'status_ok'       => 'In Ordnung',
        'warnings_tip'    => 'Warnungen',
        'errors_tip'      => 'Fehler',
    ],

    'summary' => [
        'global_state'    => 'Gesamtstatus:',
        'global_critical' => 'Kritisch',
        'global_warning'  => 'Achtung',
        'global_stable'   => 'Stabil',
        'ok_checks'       => 'Prüfungen OK',
        'warnings'        => 'Warnungen',
        'errors'          => 'Fehler',
        'total_run'       => 'Prüfungen ausgeführt',
        'focus_fail'      => 'Es liegen Fehler vor, die eine Maßnahme erfordern.',
        'focus_warn'      => 'Das System ist betriebsbereit, aber einige Konfigurationen müssen überprüft werden.',
        'focus_ok'        => 'Alle Hauptprüfungen sind in Ordnung.',
        'issues_to_check' => ':count zu prüfende Elemente',
    ],

    'history' => [
        'col_data'       => 'Datum',
        'col_ok'         => 'OK',
        'col_warn'       => 'Warnungen',
        'col_fail'       => 'Fehler',
        'col_executed_by' => 'Ausgeführt von',
        'empty'          => 'Keine Ausführung erfasst.',
        'system'         => 'System',
    ],

    'widget' => [
        'label'     => 'Systemstatus',
        'never'     => 'Nie ausgeführt',
        'fail_one'  => '1 Prüfung fehlgeschlagen',
        'fail_many' => ':count Prüfungen fehlgeschlagen',
        'warn_one'  => '1 Warnung',
        'warn_many' => ':count Warnungen',
        'passed'    => ':count Prüfungen bestanden',
    ],
];
