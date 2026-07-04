<?php

return [
    // Whitelist BASE dei comandi consentiti al modulo Scheduler (comandi core).
    // Solo job operativi e periodici: niente scaffolding, introspezione o recursion sul master scheduler.
    // A questa lista vengono UNITI a runtime i comandi dichiarati dai moduli abilitati
    // (module.json → "scheduled_jobs"): un modulo rende schedulabile un proprio comando
    // senza editare questo file. Es.: i comandi "documenti:*" sono dichiarati dal modulo Documenti.
    // I job con command fuori dall'elenco risultante vengono rifiutati sia in validazione sia in esecuzione.
    'allowed_commands' => [
        'cleanup',
        'notifications:process-queue',
        'calendar:send-reminders',
        'contacts:process-reminders',
        'tasks:send-due-reminders',
        'backup:run',
        'logs:rotate',
        'retention:run',
        'reports:cleanup',
        'session:gc',
        'ratelimit:cleanup',
    ],

    // Placeholder per estensioni future (timeout subprocess Scheduler).
    'execution_timeout_seconds' => (int) env('SCHEDULER_EXECUTION_TIMEOUT', 120),

    // Locale usata per i messaggi runtime dello Scheduler (output/eccezioni
    // possedute dal wrapper). Il cron non ha la lingua di un utente: l'output
    // viene reso in questa locale, uguale per tutti gli admin che lo leggono.
    // Vuoto = usa config('localization.default').
    'locale' => env('SCHEDULER_LOCALE', ''),
];
