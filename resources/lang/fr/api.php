<?php

/**
 * API publique — chaînes UI (français).
 * Les en-têtes et payloads de l'API restent neutres (non localisés).
 */

return [
    'tokens' => [
        'title'    => 'Jetons API',
        'subtitle' => 'Créez et révoquez des jetons d\'accès personnels pour l\'API publique.',
        'api_docs' => 'Documentation API',
        'manage_cta' => 'Gérer les jetons API',

        'created_once_title' => 'Jeton créé',
        'created_once_hint'  => 'Copiez-le maintenant : pour des raisons de sécurité, il ne sera plus affiché. Utilisez-le dans l\'en-tête Authorization: Bearer <token>.',

        'create_title'       => 'Nouveau jeton',
        'field_name'         => 'Nom',
        'field_name_ph'      => 'Ex. Application mobile, Script de sauvegarde…',
        'field_expiry'       => 'Expiration',
        'expiry_never'       => 'Aucune expiration',
        'expiry_30'          => '30 jours',
        'expiry_90'          => '90 jours',
        'expiry_365'         => '1 an',
        'field_scopes'       => 'Portées',
        'field_scopes_hint'  => 'Sélectionnez les permissions que le jeton pourra utiliser. Si vous n\'en sélectionnez aucune, le jeton hérite de toutes vos permissions.',
        'no_scopes'          => 'Aucune permission disponible.',
        'create_submit'      => 'Générer le jeton',

        'list_title'    => 'Jetons actifs',
        'empty'         => 'Aucun jeton actif.',
        'col_name'      => 'Nom',
        'col_scopes'    => 'Portées',
        'col_expires'   => 'Expiration',
        'col_last_used' => 'Dernière utilisation',
        'scope_full'    => 'Permissions complètes',

        'revoke'         => 'Révoquer',
        'revoke_confirm' => 'Révoquer ce jeton ? Les applications qui l\'utilisent perdront l\'accès immédiatement.',

        'flash_created'   => 'Jeton créé avec succès.',
        'flash_revoked'   => 'Jeton révoqué.',
        'flash_not_found' => 'Jeton introuvable.',
    ],
];
