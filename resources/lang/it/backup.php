<?php

/**
 * Backup module — Italian (canonical).
 */
return [
    'title'           => 'Backup Database',
    'hero_title'      => 'Backup',
    'hero_subtitle'   => 'Backup SQL completo compresso, con rotazione automatica',
    'restore_keyword' => 'RIPRISTINA',

    'action' => [
        'create'      => 'Crea Backup',
        'download'    => 'Scarica',
        'restore'     => 'Ripristina',
        'restore_now' => 'Ripristina ora',
        'start'       => 'Avvia',
    ],
    'start_tooltip' => 'Avvia backup',
    'start_confirm' => 'Avviare la creazione del backup? L\'operazione potrebbe richiedere alcuni minuti.',

    'running_title' => 'Backup in corso.',
    'running_body'  => 'Attendere il completamento prima di avviarne un altro.',

    'note_label'     => 'Nota:',
    'note_body'      => 'I backup sono archiviati sul server in <code>storage/backups/</code>. Scaricali regolarmente in un luogo sicuro esterno. Vengono mantenuti automaticamente gli ultimi :count backup.',
    'excluded_label' => 'Tabelle escluse:',

    'available'      => 'Backup disponibili',
    'history'        => 'Storico backup',
    'history_hint'   => '(ultimi 50)',
    'partial'        => 'Parziale',
    'empty'          => 'Nessun backup trovato. Crea il primo backup con il pulsante in alto.',
    'delete_confirm' => 'Eliminare definitivamente questo backup?',

    'cols' => [
        'file'         => 'File',
        'size'         => 'Dimensione',
        'tables'       => 'Tabelle',
        'database'     => 'Database',
        'created_by'   => 'Creato da',
        'date'         => 'Data',
        'filename'     => 'Nome file',
        'created_date' => 'Data creazione',
    ],

    'restore_modal' => [
        'title'               => 'Conferma ripristino backup',
        'about'               => 'Stai per ripristinare il backup:',
        'confirm_instruction' => 'Scrivi <strong>:keyword</strong> per confermare',
        'password_label'      => 'Password account corrente',
        'safety_note'         => 'Prima del ripristino verrà creato automaticamente un backup di sicurezza.',
    ],

    'flash' => [
        'created'          => 'Backup creato con successo: :filename',
        'excluded_count'   => '(:count tabelle escluse)',
        'partial_warning'  => ' — ATTENZIONE: uno o più database di modulo non erano raggiungibili e sono stati esclusi dal backup.',
        'error'            => 'Errore durante il backup: :error',
        'invalid_filename' => 'Nome file non valido.',
        'file_not_found'   => 'File non trovato.',
        'read_error'       => 'Errore lettura backup: :error',
        'deleted'          => 'Backup eliminato.',
        'delete_failed'    => 'Impossibile eliminare il backup.',
        'confirm_invalid'  => 'Conferma non valida. Scrivi :keyword per procedere.',
        'password_invalid' => 'Password corrente non valida.',
        'restored'         => 'Ripristino completato: :filename',
        'restore_failed'   => 'Ripristino fallito: :error',
    ],
    'notif' => [
        'completed_title'      => 'Backup completato',
        'restored_title'       => 'Ripristino completato',
        'restored_body'        => 'Il backup :filename è stato ripristinato correttamente.',
        'restore_failed_title' => 'Ripristino backup fallito',
        'restore_failed_body'  => 'Il ripristino di :filename non è riuscito: :error',
    ],

    'widget' => [
        'label'   => 'Backup database',
        'running' => 'Backup in corso',
        'none'    => 'Nessun backup eseguito',
        'last'    => 'Ultimo backup · :size',
    ],
];
