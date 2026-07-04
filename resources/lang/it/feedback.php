<?php

/**
 * Feedback module — Italian (canonical).
 */
return [
    'admin_title'    => 'Segnalazioni',
    'admin_subtitle' => 'Bug e richieste di funzionalità inviate dagli utenti, con contesto tecnico e triage.',
    'report_title'   => 'Segnala un problema',

    'tipi' => [
        'bug'          => 'Bug',
        'funzionalita' => 'Funzionalità',
        'domanda'      => 'Domanda',
    ],
    'severita' => [
        'bassa'   => 'Bassa',
        'media'   => 'Media',
        'alta'    => 'Alta',
        'critica' => 'Critica',
    ],
    'stati' => [
        'nuova'           => 'Nuova',
        'in_lavorazione'  => 'In lavorazione',
        'risolta'         => 'Risolta',
        'chiusa'          => 'Chiusa',
        'non_risolvibile' => 'Non risolvibile',
    ],

    'form' => [
        'tipo'              => 'Tipo',
        'severita'          => 'Severità',
        'titolo'            => 'Titolo',
        'optional'          => '(opzionale)',
        'titolo_placeholder' => 'Riassunto breve',
        'titolo_placeholder_long' => 'Riassunto breve del problema',
        'what_happened'     => 'Cosa è successo?',
        'descr_placeholder' => 'Descrivi il problema riscontrato...',
        'descr_placeholder_long' => 'Descrivi il problema o la funzionalità che non si comporta come previsto...',
        'descr_invalid'     => 'Inserisci una descrizione.',
        'steps'             => 'Passi per riprodurre',
        'steps_placeholder' => '1) ... 2) ... 3) ...',
        'steps_placeholder_long' => '1) Vai a... 2) Clicca su... 3) Si verifica...',
        'submit'            => 'Invia segnalazione',
    ],

    'report' => [
        'warning'      => 'Stai segnalando un problema',
        'error_code'   => '(errore :code)',
        'on_page'      => 'sulla pagina:',
        'intro'        => 'Descrivi cosa è successo. Alleghiamo automaticamente l\'indirizzo della pagina e i dati di ambiente lato server per aiutare a riprodurre il problema.',
    ],

    'launcher' => [
        'intro'           => 'Descrivi cosa non va: l\'ambiente tecnico (pagina, modulo, errori, sequenza di azioni) viene allegato automaticamente per aiutare a riprodurre il problema.',
        'attached_label'  => 'Cosa viene allegato',
    ],

    'filters' => [
        'search'             => 'Cerca',
        'search_placeholder' => 'Titolo, descrizione, codice...',
        'stato'              => 'Stato',
        'tipo'               => 'Tipo',
        'severita'           => 'Severità',
        'modulo'             => 'Modulo',
        'all_m'              => 'Tutti',
        'all_f'              => 'Tutte',
    ],

    'table' => [
        'col_code'    => 'Codice',
        'col_tipo'    => 'Tipo',
        'col_severita' => 'Severità',
        'col_stato'   => 'Stato',
        'col_modulo'  => 'Modulo',
        'col_titolo'  => 'Titolo',
        'col_autore'  => 'Autore',
        'col_data'    => 'Data',
        'empty'       => 'Nessuna segnalazione trovata.',
        'open_detail' => 'Apri dettaglio',
        'label'       => 'segnalazioni',
    ],

    'detail' => [
        'copy_llm'         => 'Copia per LLM',
        'list'             => 'Elenco',
        'severity_prefix'  => 'Severità:',
        'subtitle'         => 'Segnalazione di tipo <strong>:type</strong> · stato <strong>:status</strong>',
        'description'      => 'Descrizione',
        'steps'            => 'Passi per riprodurre',
        'captured_errors'  => 'Errori catturati',
        'no_errors'        => 'Nessun errore JS/HTMX catturato durante la sessione.',
        'action_sequence'  => 'Sequenza azioni (breadcrumb automatico)',
        'no_interactions'  => 'Nessuna interazione registrata.',
        'crumb_nav'        => 'navigazione →',
        'crumb_click'      => 'click su',
        'dom_available'    => 'Snapshot DOM disponibile',
        'dom_desc'         => 'HTML della pagina al momento della segnalazione (input mascherati, script rimossi). Scaricalo e aprilo in locale &mdash; non viene eseguito nel contesto dell\'app.',
        'download_dom'     => 'Scarica DOM',
        'dom_deleted'      => 'Snapshot DOM eliminato alla chiusura della segnalazione (riduzione dei dati).',
        'full_context'     => 'Contesto completo (JSON)',
        'show_hide_json'   => 'Mostra/nascondi JSON grezzo',
        'environment'      => 'Ambiente',
        'management'       => 'Gestione',
        'assigned_to'      => 'Assegnata a',
        'not_assigned'     => '— Non assegnata —',
        'admin_notes'      => 'Note admin',
        'delete'           => 'Elimina',
        'delete_desc'      => 'L\'eliminazione è reversibile dal database (soft delete), ma la segnalazione sparisce dalla console.',
        'delete_confirm'   => 'Eliminare la segnalazione :ref?',
        'delete_btn'       => 'Elimina segnalazione',
    ],
    'env' => [
        'autore'       => 'Autore',
        'ruoli'        => 'Ruoli',
        'data'         => 'Data',
        'app_version'  => 'Versione app',
        'php'          => 'PHP',
        'ip'           => 'IP',
        'modulo'       => 'Modulo',
        'route'        => 'Route',
        'viewport'     => 'Viewport',
        'lingua'       => 'Lingua',
        'user_agent'   => 'User agent',
    ],

    'flash' => [
        'save_error'   => 'Errore durante il salvataggio della segnalazione.',
        'sent'         => 'Segnalazione inviata. Grazie! Riferimento: :ref',
        'not_found'    => 'Segnalazione non trovata.',
        'updated'      => 'Segnalazione aggiornata.',
        'update_failed' => 'Aggiornamento non riuscito.',
        'deleted'      => 'Segnalazione eliminata.',
        'dom_unavailable' => 'Snapshot DOM non disponibile.',
    ],

    'widget' => [
        'label'    => 'Segnalazioni aperte',
        'new_sub'  => ':count nuove da triage',
        'none_new' => 'Nessuna nuova',
    ],
];
