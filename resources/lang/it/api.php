<?php

/**
 * API pubblica — stringhe UI (italiano, canonico).
 * Header e payload dell'API restano neutri (non localizzati).
 */

return [
    'tokens' => [
        'title'    => 'Token API',
        'subtitle' => 'Crea e revoca i token di accesso personale per l\'API pubblica.',
        'api_docs' => 'Documentazione API',
        'manage_cta' => 'Gestisci token API',

        'created_once_title' => 'Token creato',
        'created_once_hint'  => 'Copialo ora: per sicurezza non verrà più mostrato. Usalo nell\'header Authorization: Bearer <token>.',

        'create_title'       => 'Nuovo token',
        'field_name'         => 'Nome',
        'field_name_ph'      => 'Es. App mobile, Script di backup…',
        'field_expiry'       => 'Scadenza',
        'expiry_never'       => 'Nessuna scadenza',
        'expiry_30'          => '30 giorni',
        'expiry_90'          => '90 giorni',
        'expiry_365'         => '1 anno',
        'field_scopes'       => 'Scope',
        'field_scopes_hint'  => 'Seleziona i permessi che il token potrà usare. Se non selezioni nulla, il token eredita tutti i tuoi permessi.',
        'no_scopes'          => 'Nessun permesso disponibile.',
        'create_submit'      => 'Genera token',

        'list_title'    => 'Token attivi',
        'empty'         => 'Nessun token attivo.',
        'col_name'      => 'Nome',
        'col_scopes'    => 'Scope',
        'col_expires'   => 'Scadenza',
        'col_last_used' => 'Ultimo uso',
        'scope_full'    => 'Permessi pieni',

        'revoke'         => 'Revoca',
        'revoke_confirm' => 'Revocare questo token? Le applicazioni che lo usano perderanno subito l\'accesso.',

        'flash_created'   => 'Token creato con successo.',
        'flash_revoked'   => 'Token revocato.',
        'flash_not_found' => 'Token non trovato.',
    ],
];
