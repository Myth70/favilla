<?php

/**
 * Permessi del modulo Segnalazioni.
 *
 * NOTA DI DESIGN — deroga consapevole alle 4 CRUD-permission:
 * l'INVIO di una segnalazione è universale (rotta `segnalazioni.store`, solo
 * Auth+CSRF, nessun permesso) per garantire che QUALSIASI utente loggato possa
 * segnalare con il minimo attrito. È lo stesso precedente del modulo HelpOnline
 * (un solo permesso `helponline.admin`, endpoint utente auth-only).
 * I due permessi sotto governano solo la console amministrativa.
 */
return [
    ['slug' => 'feedback.view',   'name' => 'Visualizza segnalazioni'],
    ['slug' => 'feedback.manage', 'name' => 'Gestisci segnalazioni (triage)'],
];
