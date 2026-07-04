<?php

/**
 * Fornitori — stringhe del modulo (italiano canonico).
 *
 * Questo file viene generato in resources/lang/it/fornitori.php (+ copie
 * in en/fr/de/es da tradurre). NON lasciare stringhe hardcoded nelle View/Controller:
 * passa sempre da t('fornitori.<chiave>') con e() intorno per l'output HTML.
 *
 * Placeholder di interpolazione in stile :nome → t('fornitori.count_total', ['count' => $n]).
 * Dopo aver aggiunto/rimosso chiavi qui: traduci le altre lingue ed esegui
 *   php favilla lang:check
 */

return [
    'title'           => 'Fornitori',
    'count_total'     => ':count record totali',
    'new_page_title'  => 'Nuovo Fornitori',
    'edit_page_title' => 'Modifica Fornitori',
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
        'info'        => 'Informazioni',
        'actions'     => 'Azioni',
        'danger_zone' => 'Zona pericolosa',
    ],

    'list' => [
        'empty'       => 'Nessun record trovato.',
        'col_name'    => 'Nome',
        'col_status'  => 'Stato',
        'col_created' => 'Creato',
        'col_actions' => 'Azioni',
        'results'     => ':count risultati — pagina :page di :pages',
    ],

    'search' => [
        'empty_query' => 'Digita per cercare.',
        'no_results'  => 'Nessun risultato per ":query".',
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
        'no_description' => 'Nessuna descrizione.',
        'last_update'    => 'Ultimo aggiornamento:',
    ],
];
