<?php

/**
 * HealthCheck module — Italian (canonical).
 * NB: the individual check names/details are produced by HealthCheckService
 * (technical diagnostics) and are not part of this UI-shell namespace.
 */
return [
    'title'         => 'Health Check',
    'subtitle'      => 'Monitoraggio stato del sistema e dei servizi',
    'history_title' => 'Storico Health Check',
    'breadcrumb_history' => 'Storico',

    'buttons' => [
        'history'       => 'Storico',
        'export_csv'    => 'Esporta CSV',
        'deep_scan'     => 'Scansione approfondita',
        'refresh'       => 'Aggiorna',
        'back_to_check' => 'Torna al Check',
    ],
    'tooltip' => [
        'deep_scan' => 'Esegue anche i controlli approfonditi (DNS email, esposizione .env, vulnerabilità dipendenze)',
    ],

    'loading' => 'Esecuzione controlli in corso…',

    'content' => [
        'deep_scan'    => 'Scansione approfondita',
        'quick_checks' => 'Controlli rapidi — usa «Scansione approfondita» per DNS email, esposizione .env e vulnerabilità dipendenze',
        'executed_at'  => 'Eseguito il :date',
        'date_at'      => 'alle',
        'all_ok'       => 'Tutti i controlli sono regolari. Nessuna azione richiesta.',
    ],

    'card' => [
        'status_critical' => 'Criticità rilevate',
        'status_warn'     => 'Da verificare',
        'status_ok'       => 'Regolare',
        'warnings_tip'    => 'Avvisi',
        'errors_tip'      => 'Errori',
    ],

    'summary' => [
        'global_state'    => 'Stato generale:',
        'global_critical' => 'Critico',
        'global_warning'  => 'Attenzione',
        'global_stable'   => 'Stabile',
        'ok_checks'       => 'controlli OK',
        'warnings'        => 'avvisi',
        'errors'          => 'errori',
        'total_run'       => 'controlli eseguiti',
        'focus_fail'      => 'Sono presenti errori che richiedono intervento.',
        'focus_warn'      => 'Il sistema e operativo, ma ci sono configurazioni da rivedere.',
        'focus_ok'        => 'Tutti i controlli principali risultano regolari.',
        'issues_to_check' => ':count elementi da verificare',
    ],

    'history' => [
        'col_data'       => 'Data',
        'col_ok'         => 'OK',
        'col_warn'       => 'Avvisi',
        'col_fail'       => 'Errori',
        'col_executed_by' => 'Eseguito da',
        'empty'          => 'Nessun run registrato.',
        'system'         => 'Sistema',
    ],

    'widget' => [
        'label'     => 'Stato sistema',
        'never'     => 'Mai eseguito',
        'fail_one'  => '1 controllo fallito',
        'fail_many' => ':count controlli falliti',
        'warn_one'  => '1 avviso',
        'warn_many' => ':count avvisi',
        'passed'    => ':count controlli superati',
    ],
];
