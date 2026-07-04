<?php

/**
 * Example (_Template) — Demo-Modul (Deutsch).
 * Übersetzung der kanonischen it/example.php. Ausführen: php favilla lang:check
 */

return [
    'title'           => 'Beispiel',
    'count_total'     => ':count Datensätze insgesamt',
    'new_page_title'  => 'Neues Beispiel',
    'edit_page_title' => 'Beispiel bearbeiten',
    'breadcrumb_new'  => 'Neu',
    'breadcrumb_edit' => 'Bearbeiten',

    'status' => [
        'active'   => 'Aktiv',
        'inactive' => 'Inaktiv',
        'archived' => 'Archiviert',
    ],

    'badges' => [
        'active'   => ':count aktiv',
        'inactive' => ':count inaktiv',
        'archived' => ':count archiviert',
    ],

    'fields' => [
        'id'          => 'ID',
        'name'        => 'Name',
        'email'       => 'E-Mail',
        'description' => 'Beschreibung',
        'status'      => 'Status',
        'author'      => 'Autor',
        'created_at'  => 'Erstellt am',
    ],

    'actions' => [
        'new'    => 'Neu',
        'edit'   => 'Bearbeiten',
        'create' => 'Erstellen',
        'update' => 'Aktualisieren',
        'cancel' => 'Abbrechen',
        'delete' => 'Datensatz löschen',
        'back'   => 'Zurück zur Liste',
        'detail' => 'Details',
        'reset'  => 'Zurücksetzen',
    ],

    'filters' => [
        'search_placeholder' => 'Suchen...',
        'all_status'         => 'Alle Status',
    ],

    'sections' => [
        'main'        => 'Hauptdaten',
        'content'     => 'Inhalt und Status',
        'info'        => 'Informationen',
        'actions'     => 'Aktionen',
        'danger_zone' => 'Gefahrenzone',
        'description' => 'Beschreibung',
    ],

    'form' => [
        'subtitle_new'   => 'Einen neuen Moduldatensatz erstellen',
        'subtitle_edit'  => 'Den vorhandenen Datensatz aktualisieren',
        'errors_summary' => 'Bitte korrigieren Sie die markierten Fehler.',
    ],

    'feedback' => [
        'name'        => 'Geben Sie den Namen des Datensatzes ein.',
        'email'       => 'Geben Sie eine gültige E-Mail-Adresse ein.',
        'description' => 'Überprüfen Sie den Inhalt der Beschreibung.',
        'status'      => 'Wählen Sie einen gültigen Status.',
    ],

    'list' => [
        'empty'       => 'Keine Datensätze gefunden.',
        'col_name'    => 'Name',
        'col_email'   => 'E-Mail',
        'col_status'  => 'Status',
        'col_created' => 'Erstellt',
        'col_actions' => 'Aktionen',
        'results'     => ':count Ergebnisse — Seite :page von :pages',
    ],

    'confirm' => [
        'delete' => 'Möchten Sie diesen Datensatz wirklich löschen?',
    ],

    'flash' => [
        'created'   => 'Datensatz erfolgreich erstellt.',
        'updated'   => 'Datensatz erfolgreich aktualisiert.',
        'deleted'   => 'Datensatz gelöscht.',
        'not_found' => 'Datensatz nicht gefunden.',
    ],

    'detail' => [
        'no_description'    => 'Keine Beschreibung.',
        'last_update'       => 'Letzte Aktualisierung:',
        'subtitle_fallback' => 'Datensatzdetails',
    ],
];
