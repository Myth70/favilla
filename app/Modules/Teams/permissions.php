<?php

/**
 * Permessi modulo Teams.
 *
 * Mappa sintetica route → permesso (vedi `routes.php`):
 *
 *   teams.view    Lettura: index, conversation list, poll messaggi, ricerca,
 *                 typing/presence/heartbeat, mute/hide/unhide (modifica del
 *                 proprio stato utente, non del contenuto della conversazione).
 *
 *   teams.create  Scrittura: store conversazione, invio messaggi, reazioni,
 *                 pin/unpin (autorizzazione admin di conversazione verificata
 *                 nel service in difesa profonda), rinomina/archive gruppo,
 *                 aggiunta/rimozione membri, upload avatar.
 *
 *   teams.delete  Manutenzione propri contenuti: modifica ed eliminazione dei
 *                 propri messaggi, leave gruppo. Nota: lo slug è "delete" per
 *                 backward-compat, ma copre anche l'edit (entrambi i verbi
 *                 operano sui propri messaggi non ancora letti da altri).
 *
 *   teams.admin   Pannello admin, cleanup, archive/destroy globale, bypass
 *                 delle autorizzazioni di conversazione (pin, edit/delete su
 *                 messaggi altrui, gestione membri).
 */
return [
    ['slug' => 'teams.view',   'name' => 'Accesso Teams (lettura, presence, mute/hide)'],
    ['slug' => 'teams.create', 'name' => 'Invia messaggi, gestisci gruppi e membri'],
    ['slug' => 'teams.delete', 'name' => 'Modifica/elimina i propri messaggi, esci dai gruppi'],
    ['slug' => 'teams.admin',  'name' => 'Amministra Teams (pannello, cleanup, override)'],
];
