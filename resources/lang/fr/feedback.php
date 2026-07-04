<?php

/**
 * Feedback module — French.
 */
return [
    'admin_title'    => 'Signalements',
    'admin_subtitle' => 'Bugs et demandes de fonctionnalités envoyés par les utilisateurs, avec contexte technique et triage.',
    'report_title'   => 'Signaler un problème',

    'tipi' => [
        'bug'          => 'Bug',
        'funzionalita' => 'Fonctionnalité',
        'domanda'      => 'Question',
    ],
    'severita' => [
        'bassa'   => 'Basse',
        'media'   => 'Moyenne',
        'alta'    => 'Haute',
        'critica' => 'Critique',
    ],
    'stati' => [
        'nuova'           => 'Nouveau',
        'in_lavorazione'  => 'En cours',
        'risolta'         => 'Résolu',
        'chiusa'          => 'Fermé',
        'non_risolvibile' => 'Non résoluble',
    ],

    'form' => [
        'tipo'              => 'Type',
        'severita'          => 'Gravité',
        'titolo'            => 'Titre',
        'optional'          => '(facultatif)',
        'titolo_placeholder' => 'Bref résumé',
        'titolo_placeholder_long' => 'Bref résumé du problème',
        'what_happened'     => 'Que s\'est-il passé ?',
        'descr_placeholder' => 'Décrivez le problème rencontré...',
        'descr_placeholder_long' => 'Décrivez le problème ou la fonctionnalité qui ne se comporte pas comme prévu...',
        'descr_invalid'     => 'Saisissez une description.',
        'steps'             => 'Étapes pour reproduire',
        'steps_placeholder' => '1) ... 2) ... 3) ...',
        'steps_placeholder_long' => '1) Allez à... 2) Cliquez sur... 3) Cela se produit...',
        'submit'            => 'Envoyer le signalement',
    ],

    'report' => [
        'warning'      => 'Vous signalez un problème',
        'error_code'   => '(erreur :code)',
        'on_page'      => 'sur la page :',
        'intro'        => 'Décrivez ce qui s\'est passé. Nous joignons automatiquement l\'adresse de la page et les données d\'environnement côté serveur pour aider à reproduire le problème.',
    ],

    'launcher' => [
        'intro'           => 'Décrivez ce qui ne va pas : l\'environnement technique (page, module, erreurs, séquence d\'actions) est joint automatiquement pour aider à reproduire le problème.',
        'attached_label'  => 'Ce qui est joint',
    ],

    'filters' => [
        'search'             => 'Rechercher',
        'search_placeholder' => 'Titre, description, code...',
        'stato'              => 'Statut',
        'tipo'               => 'Type',
        'severita'           => 'Gravité',
        'modulo'             => 'Module',
        'all_m'              => 'Tous',
        'all_f'              => 'Toutes',
    ],

    'table' => [
        'col_code'    => 'Code',
        'col_tipo'    => 'Type',
        'col_severita' => 'Gravité',
        'col_stato'   => 'Statut',
        'col_modulo'  => 'Module',
        'col_titolo'  => 'Titre',
        'col_autore'  => 'Auteur',
        'col_data'    => 'Date',
        'empty'       => 'Aucun signalement trouvé.',
        'open_detail' => 'Ouvrir le détail',
        'label'       => 'signalements',
    ],

    'detail' => [
        'copy_llm'         => 'Copier pour LLM',
        'list'             => 'Liste',
        'severity_prefix'  => 'Gravité :',
        'subtitle'         => 'Signalement de type <strong>:type</strong> · statut <strong>:status</strong>',
        'description'      => 'Description',
        'steps'            => 'Étapes pour reproduire',
        'captured_errors'  => 'Erreurs capturées',
        'no_errors'        => 'Aucune erreur JS/HTMX capturée pendant la session.',
        'action_sequence'  => 'Séquence d\'actions (fil d\'Ariane automatique)',
        'no_interactions'  => 'Aucune interaction enregistrée.',
        'crumb_nav'        => 'navigation →',
        'crumb_click'      => 'clic sur',
        'dom_available'    => 'Instantané DOM disponible',
        'dom_desc'         => 'HTML de la page au moment du signalement (champs masqués, scripts supprimés). Téléchargez-le et ouvrez-le en local &mdash; il n\'est pas exécuté dans le contexte de l\'application.',
        'download_dom'     => 'Télécharger le DOM',
        'dom_deleted'      => 'Instantané DOM supprimé à la clôture du signalement (minimisation des données).',
        'full_context'     => 'Contexte complet (JSON)',
        'show_hide_json'   => 'Afficher/masquer le JSON brut',
        'environment'      => 'Environnement',
        'management'       => 'Gestion',
        'assigned_to'      => 'Assigné à',
        'not_assigned'     => '— Non assigné —',
        'admin_notes'      => 'Notes admin',
        'delete'           => 'Supprimer',
        'delete_desc'      => 'La suppression est réversible depuis la base de données (suppression douce), mais le signalement disparaît de la console.',
        'delete_confirm'   => 'Supprimer le signalement :ref ?',
        'delete_btn'       => 'Supprimer le signalement',
    ],
    'env' => [
        'autore'       => 'Auteur',
        'ruoli'        => 'Rôles',
        'data'         => 'Date',
        'app_version'  => 'Version de l\'application',
        'php'          => 'PHP',
        'ip'           => 'IP',
        'modulo'       => 'Module',
        'route'        => 'Route',
        'viewport'     => 'Viewport',
        'lingua'       => 'Langue',
        'user_agent'   => 'User agent',
    ],

    'flash' => [
        'save_error'   => 'Erreur lors de l\'enregistrement du signalement.',
        'sent'         => 'Signalement envoyé. Merci ! Référence : :ref',
        'not_found'    => 'Signalement introuvable.',
        'updated'      => 'Signalement mis à jour.',
        'update_failed' => 'Échec de la mise à jour.',
        'deleted'      => 'Signalement supprimé.',
        'dom_unavailable' => 'Instantané DOM non disponible.',
    ],

    'widget' => [
        'label'    => 'Signalements ouverts',
        'new_sub'  => ':count à trier',
        'none_new' => 'Aucun nouveau',
    ],
];
