<?php

/**
 * Il modulo API non introduce permessi propri: gli scope dei token sono un
 * sottoinsieme dei permessi già esistenti degli altri moduli, e la gestione
 * self-service dei token richiede solo l'autenticazione (nessun permesso
 * dedicato — vedi TokensController).
 */

return [];
