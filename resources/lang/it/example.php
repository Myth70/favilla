<?php

/**
 * Example (_Template) — modulo dimostrativo (italiano canonico).
 *
 * Vetrina i18n del template: ogni stringa user-facing passa da e(t('example.<chiave>')).
 * Tradotto in en/fr/de/es. Dopo modifiche alle chiavi: php favilla lang:check
 */

return [
    'title'           => 'Esempio',
    'count_total'     => ':count record totali',
    'new_page_title'  => 'Nuovo Esempio',
    'edit_page_title' => 'Modifica Esempio',
    'breadcrumb_new'  => 'Nuovo',
    'breadcrumb_edit' => 'Modifica',

    'status' => [
        'active'   => 'Attivo',
        'inactive' => 'Inattivo',
        'archived' => 'Archiviato',
    ],

    'badges' => [
        'active'   => ':count attivi',
        'inactive' => ':count inattivi',
        'archived' => ':count archiviati',
    ],

    'fields' => [
        'id'          => 'ID',
        'name'        => 'Nome',
        'email'       => 'Email',
        'description' => 'Descrizione',
        'status'      => 'Stato',
        'author'      => 'Autore',
        'created_at'  => 'Creato il',
    ],

    'actions' => [
        'new'    => 'Nuovo',
        'edit'   => 'Modifica',
        'create' => 'Crea',
        'update' => 'Aggiorna',
        'cancel' => 'Annulla',
        'delete' => 'Elimina record',
        'back'   => 'Torna alla lista',
        'detail' => 'Dettaglio',
        'reset'  => 'Reset',
    ],

    'filters' => [
        'search_placeholder' => 'Cerca...',
        'all_status'         => 'Tutti gli stati',
    ],

    'sections' => [
        'main'        => 'Dati principali',
        'content'     => 'Contenuto e stato',
        'info'        => 'Informazioni',
        'actions'     => 'Azioni',
        'danger_zone' => 'Zona pericolosa',
        'description' => 'Descrizione',
    ],

    'form' => [
        'subtitle_new'   => 'Crea un nuovo record del modulo',
        'subtitle_edit'  => 'Aggiorna il record esistente',
        'errors_summary' => 'Correggi gli errori evidenziati.',
    ],

    'feedback' => [
        'name'        => 'Inserisci il nome del record.',
        'email'       => 'Inserisci un\'email valida.',
        'description' => 'Controlla il contenuto della descrizione.',
        'status'      => 'Seleziona uno stato valido.',
    ],

    'list' => [
        'empty'       => 'Nessun record trovato.',
        'col_name'    => 'Nome',
        'col_email'   => 'Email',
        'col_status'  => 'Stato',
        'col_created' => 'Creato',
        'col_actions' => 'Azioni',
        'results'     => ':count risultati — pagina :page di :pages',
    ],

    'confirm' => [
        'delete' => 'Sei sicuro di voler eliminare questo record?',
    ],

    'flash' => [
        'created'   => 'Record creato con successo.',
        'updated'   => 'Record aggiornato con successo.',
        'deleted'   => 'Record eliminato.',
        'not_found' => 'Record non trovato.',
    ],

    'detail' => [
        'no_description'    => 'Nessuna descrizione.',
        'last_update'       => 'Ultimo aggiornamento:',
        'subtitle_fallback' => 'Dettaglio record',
    ],
];
