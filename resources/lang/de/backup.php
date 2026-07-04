<?php

/**
 * Backup module — German.
 */
return [
    'title'           => 'Datenbank-Backup',
    'hero_title'      => 'Backup',
    'hero_subtitle'   => 'Vollständiges komprimiertes SQL-Backup mit automatischer Rotation',
    'restore_keyword' => 'WIEDERHERSTELLEN',

    'action' => [
        'create'      => 'Backup erstellen',
        'download'    => 'Herunterladen',
        'restore'     => 'Wiederherstellen',
        'restore_now' => 'Jetzt wiederherstellen',
        'start'       => 'Starten',
    ],
    'start_tooltip' => 'Backup starten',
    'start_confirm' => 'Erstellung des Backups starten? Der Vorgang kann einige Minuten dauern.',

    'running_title' => 'Backup läuft.',
    'running_body'  => 'Warten Sie auf den Abschluss, bevor Sie ein weiteres starten.',

    'note_label'     => 'Hinweis:',
    'note_body'      => 'Backups werden auf dem Server in <code>storage/backups/</code> gespeichert. Laden Sie sie regelmäßig an einen sicheren externen Ort herunter. Die letzten :count Backups werden automatisch aufbewahrt.',
    'excluded_label' => 'Ausgeschlossene Tabellen:',

    'available'      => 'Verfügbare Backups',
    'history'        => 'Backup-Verlauf',
    'history_hint'   => '(letzte 50)',
    'partial'        => 'Teilweise',
    'empty'          => 'Keine Backups gefunden. Erstellen Sie das erste Backup über die Schaltfläche oben.',
    'delete_confirm' => 'Dieses Backup endgültig löschen?',

    'cols' => [
        'file'         => 'Datei',
        'size'         => 'Größe',
        'tables'       => 'Tabellen',
        'database'     => 'Datenbank',
        'created_by'   => 'Erstellt von',
        'date'         => 'Datum',
        'filename'     => 'Dateiname',
        'created_date' => 'Erstellungsdatum',
    ],

    'restore_modal' => [
        'title'               => 'Backup-Wiederherstellung bestätigen',
        'about'               => 'Sie sind dabei, das Backup wiederherzustellen:',
        'confirm_instruction' => 'Geben Sie <strong>:keyword</strong> ein, um zu bestätigen',
        'password_label'      => 'Aktuelles Kontopasswort',
        'safety_note'         => 'Vor der Wiederherstellung wird automatisch ein Sicherheits-Backup erstellt.',
    ],

    'flash' => [
        'created'          => 'Backup erfolgreich erstellt: :filename',
        'excluded_count'   => '(:count Tabellen ausgeschlossen)',
        'partial_warning'  => ' — WARNUNG: Eine oder mehrere Moduldatenbanken waren nicht erreichbar und wurden vom Backup ausgeschlossen.',
        'error'            => 'Fehler beim Backup: :error',
        'invalid_filename' => 'Ungültiger Dateiname.',
        'file_not_found'   => 'Datei nicht gefunden.',
        'read_error'       => 'Fehler beim Lesen des Backups: :error',
        'deleted'          => 'Backup gelöscht.',
        'delete_failed'    => 'Backup konnte nicht gelöscht werden.',
        'confirm_invalid'  => 'Ungültige Bestätigung. Geben Sie :keyword ein, um fortzufahren.',
        'password_invalid' => 'Ungültiges aktuelles Passwort.',
        'restored'         => 'Wiederherstellung abgeschlossen: :filename',
        'restore_failed'   => 'Wiederherstellung fehlgeschlagen: :error',
    ],
    'notif' => [
        'completed_title'      => 'Backup abgeschlossen',
        'restored_title'       => 'Wiederherstellung abgeschlossen',
        'restored_body'        => 'Das Backup :filename wurde erfolgreich wiederhergestellt.',
        'restore_failed_title' => 'Backup-Wiederherstellung fehlgeschlagen',
        'restore_failed_body'  => 'Die Wiederherstellung von :filename ist fehlgeschlagen: :error',
    ],

    'widget' => [
        'label'   => 'Datenbank-Backup',
        'running' => 'Backup läuft',
        'none'    => 'Kein Backup durchgeführt',
        'last'    => 'Letztes Backup · :size',
    ],
];
