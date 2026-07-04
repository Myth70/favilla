<?php

/**
 * Example (_Template) — module de démonstration (français).
 * Traduction du fichier canonique it/example.php. Lancer : php favilla lang:check
 */

return [
    'title'           => 'Exemple',
    'count_total'     => ':count enregistrements au total',
    'new_page_title'  => 'Nouvel exemple',
    'edit_page_title' => 'Modifier l\'exemple',
    'breadcrumb_new'  => 'Nouveau',
    'breadcrumb_edit' => 'Modifier',

    'status' => [
        'active'   => 'Actif',
        'inactive' => 'Inactif',
        'archived' => 'Archivé',
    ],

    'badges' => [
        'active'   => ':count actifs',
        'inactive' => ':count inactifs',
        'archived' => ':count archivés',
    ],

    'fields' => [
        'id'          => 'ID',
        'name'        => 'Nom',
        'email'       => 'E-mail',
        'description' => 'Description',
        'status'      => 'Statut',
        'author'      => 'Auteur',
        'created_at'  => 'Créé le',
    ],

    'actions' => [
        'new'    => 'Nouveau',
        'edit'   => 'Modifier',
        'create' => 'Créer',
        'update' => 'Mettre à jour',
        'cancel' => 'Annuler',
        'delete' => 'Supprimer l\'enregistrement',
        'back'   => 'Retour à la liste',
        'detail' => 'Détails',
        'reset'  => 'Réinitialiser',
    ],

    'filters' => [
        'search_placeholder' => 'Rechercher...',
        'all_status'         => 'Tous les statuts',
    ],

    'sections' => [
        'main'        => 'Données principales',
        'content'     => 'Contenu et statut',
        'info'        => 'Informations',
        'actions'     => 'Actions',
        'danger_zone' => 'Zone dangereuse',
        'description' => 'Description',
    ],

    'form' => [
        'subtitle_new'   => 'Créer un nouvel enregistrement du module',
        'subtitle_edit'  => 'Mettre à jour l\'enregistrement existant',
        'errors_summary' => 'Veuillez corriger les erreurs indiquées.',
    ],

    'feedback' => [
        'name'        => 'Saisissez le nom de l\'enregistrement.',
        'email'       => 'Saisissez une adresse e-mail valide.',
        'description' => 'Vérifiez le contenu de la description.',
        'status'      => 'Sélectionnez un statut valide.',
    ],

    'list' => [
        'empty'       => 'Aucun enregistrement trouvé.',
        'col_name'    => 'Nom',
        'col_email'   => 'E-mail',
        'col_status'  => 'Statut',
        'col_created' => 'Créé',
        'col_actions' => 'Actions',
        'results'     => ':count résultats — page :page sur :pages',
    ],

    'confirm' => [
        'delete' => 'Voulez-vous vraiment supprimer cet enregistrement ?',
    ],

    'flash' => [
        'created'   => 'Enregistrement créé avec succès.',
        'updated'   => 'Enregistrement mis à jour avec succès.',
        'deleted'   => 'Enregistrement supprimé.',
        'not_found' => 'Enregistrement introuvable.',
    ],

    'detail' => [
        'no_description'    => 'Aucune description.',
        'last_update'       => 'Dernière mise à jour :',
        'subtitle_fallback' => 'Détails de l\'enregistrement',
    ],
];
