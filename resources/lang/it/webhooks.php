<?php

/**
 * Webhook in uscita — stringhe UI (italiano, canonico).
 */

return [
    'title'        => 'Webhook',
    'subtitle'     => 'Notifica sistemi esterni quando succede un evento, con firma HMAC e retry automatici.',
    'form_subtitle' => 'Configura l\'URL di destinazione e gli eventi a cui iscriverlo.',
    'list_title'   => 'Endpoint configurati',
    'empty'        => 'Nessun endpoint webhook configurato.',
    'back'         => 'Indietro',
    'cancel'       => 'Annulla',
    'save'         => 'Salva',

    'create_title' => 'Nuovo webhook',
    'edit_title'   => 'Modifica webhook',
    'test_cta'     => 'Invia di prova',
    'delete'       => 'Elimina',
    'delete_confirm' => 'Eliminare questo endpoint webhook? Le consegne in coda verranno rimosse.',
    'active'       => 'Attivo',
    'inactive'     => 'Disattivo',

    'stat_pending' => 'In coda',
    'stat_sent'    => 'Consegnati',
    'stat_failed'  => 'Falliti',

    'col_url'      => 'URL',
    'col_events'   => 'Eventi',
    'col_status'   => 'Stato',
    'col_event'    => 'Evento',
    'col_attempts' => 'Tentativi',
    'col_response' => 'Risposta',
    'col_created'  => 'Creato',

    'field_url'          => 'URL di destinazione',
    'field_url_hint'     => 'Solo HTTPS. Gli indirizzi privati o di loopback vengono bloccati (anti-SSRF).',
    'field_description'  => 'Descrizione (opzionale)',
    'field_active'       => 'Endpoint attivo',
    'field_events'       => 'Eventi sottoscritti',
    'field_events_hint'  => 'L\'endpoint riceverà una POST firmata a ogni evento selezionato.',
    'no_events'          => 'Nessun evento disponibile.',

    'secret_once_title'  => 'Secret di firma generato',
    'secret_once_hint'   => 'Copialo ora: non verrà più mostrato. Usalo per verificare l\'header X-Favilla-Signature (HMAC-SHA256 del corpo).',
    'secret_section'     => 'Secret di firma',
    'secret_section_hint' => 'Rigenera il secret se sospetti sia compromesso. Il vecchio secret smetterà subito di funzionare.',
    'secret_regenerate'  => 'Rigenera secret',
    'secret_regenerate_confirm' => 'Rigenerare il secret? Le firme calcolate con quello attuale non saranno più valide.',

    'deliveries_title' => 'Registro consegne',
    'deliveries_empty' => 'Nessuna consegna registrata per questo endpoint.',

    'flash_created'   => 'Endpoint webhook creato.',
    'flash_updated'   => 'Endpoint webhook aggiornato.',
    'flash_deleted'   => 'Endpoint webhook eliminato.',
    'flash_not_found' => 'Endpoint non trovato.',
    'flash_secret_regenerated' => 'Secret rigenerato.',
    'flash_test_ok'     => 'Invio di prova completato.',
    'flash_test_failed' => 'Invio di prova fallito:',

    'test_sent'   => 'Invio riuscito (HTTP :status).',
    'test_failed' => 'Invio fallito: :error',

    'error' => [
        'url_missing'        => 'URL mancante o troppo lungo.',
        'url_invalid'        => 'URL non valido.',
        'url_credentials'    => 'Le credenziali nell\'URL non sono ammesse.',
        'https_only'         => 'Sono ammessi solo endpoint HTTPS.',
        'scheme_unsupported' => 'Schema URL non supportato (usa https).',
        'unresolvable'       => 'Impossibile risolvere l\'host di destinazione.',
        'private_ip'         => 'La destinazione risolve a un indirizzo IP privato o riservato.',
        'not_found'          => 'Endpoint non trovato.',
        'no_events'          => 'Seleziona almeno un evento a cui iscrivere l\'endpoint.',
    ],
];
