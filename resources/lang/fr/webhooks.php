<?php

/**
 * Webhooks sortants — chaînes UI (français).
 */

return [
    'title'        => 'Webhooks',
    'subtitle'     => 'Notifiez des systèmes externes lorsqu\'un événement se produit, avec signature HMAC et relances automatiques.',
    'form_subtitle' => 'Configurez l\'URL de destination et les événements à lui associer.',
    'list_title'   => 'Endpoints configurés',
    'empty'        => 'Aucun endpoint webhook configuré.',
    'back'         => 'Retour',
    'cancel'       => 'Annuler',
    'save'         => 'Enregistrer',

    'create_title' => 'Nouveau webhook',
    'edit_title'   => 'Modifier le webhook',
    'test_cta'     => 'Envoyer un test',
    'delete'       => 'Supprimer',
    'delete_confirm' => 'Supprimer cet endpoint webhook ? Les livraisons en file seront supprimées.',
    'active'       => 'Actif',
    'inactive'     => 'Inactif',

    'stat_pending' => 'En file',
    'stat_sent'    => 'Livrés',
    'stat_failed'  => 'Échoués',

    'col_url'      => 'URL',
    'col_events'   => 'Événements',
    'col_status'   => 'Statut',
    'col_event'    => 'Événement',
    'col_attempts' => 'Tentatives',
    'col_response' => 'Réponse',
    'col_created'  => 'Créé',

    'field_url'          => 'URL de destination',
    'field_url_hint'     => 'HTTPS uniquement. Les adresses privées ou de loopback sont bloquées (anti-SSRF).',
    'field_description'  => 'Description (facultatif)',
    'field_active'       => 'Endpoint actif',
    'field_events'       => 'Événements souscrits',
    'field_events_hint'  => 'L\'endpoint recevra une requête POST signée à chaque événement sélectionné.',
    'no_events'          => 'Aucun événement disponible.',

    'secret_once_title'  => 'Secret de signature généré',
    'secret_once_hint'   => 'Copiez-le maintenant : il ne sera plus affiché. Utilisez-le pour vérifier l\'en-tête X-Favilla-Signature (HMAC-SHA256 du corps).',
    'secret_section'     => 'Secret de signature',
    'secret_section_hint' => 'Régénérez le secret si vous le pensez compromis. L\'ancien secret cesse immédiatement de fonctionner.',
    'secret_regenerate'  => 'Régénérer le secret',
    'secret_regenerate_confirm' => 'Régénérer le secret ? Les signatures calculées avec l\'actuel ne seront plus valides.',

    'deliveries_title' => 'Journal des livraisons',
    'deliveries_empty' => 'Aucune livraison enregistrée pour cet endpoint.',

    'flash_created'   => 'Endpoint webhook créé.',
    'flash_updated'   => 'Endpoint webhook mis à jour.',
    'flash_deleted'   => 'Endpoint webhook supprimé.',
    'flash_not_found' => 'Endpoint introuvable.',
    'flash_secret_regenerated' => 'Secret régénéré.',
    'flash_test_ok'     => 'Livraison de test réussie.',
    'flash_test_failed' => 'Livraison de test échouée :',
];
