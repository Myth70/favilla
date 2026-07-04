<?php

/**
 * Backup module — French.
 */
return [
    'title'           => 'Sauvegarde de la base de données',
    'hero_title'      => 'Sauvegarde',
    'hero_subtitle'   => 'Sauvegarde SQL complète compressée, avec rotation automatique',
    'restore_keyword' => 'RESTAURER',

    'action' => [
        'create'      => 'Créer une sauvegarde',
        'download'    => 'Télécharger',
        'restore'     => 'Restaurer',
        'restore_now' => 'Restaurer maintenant',
        'start'       => 'Démarrer',
    ],
    'start_tooltip' => 'Démarrer la sauvegarde',
    'start_confirm' => 'Démarrer la création de la sauvegarde ? L\'opération peut prendre quelques minutes.',

    'running_title' => 'Sauvegarde en cours.',
    'running_body'  => 'Attendez la fin avant d\'en démarrer une autre.',

    'note_label'     => 'Note :',
    'note_body'      => 'Les sauvegardes sont stockées sur le serveur dans <code>storage/backups/</code>. Téléchargez-les régulièrement vers un emplacement externe sûr. Les :count dernières sauvegardes sont conservées automatiquement.',
    'excluded_label' => 'Tables exclues :',

    'available'      => 'Sauvegardes disponibles',
    'history'        => 'Historique des sauvegardes',
    'history_hint'   => '(50 dernières)',
    'partial'        => 'Partielle',
    'empty'          => 'Aucune sauvegarde trouvée. Créez la première sauvegarde avec le bouton ci-dessus.',
    'delete_confirm' => 'Supprimer définitivement cette sauvegarde ?',

    'cols' => [
        'file'         => 'Fichier',
        'size'         => 'Taille',
        'tables'       => 'Tables',
        'database'     => 'Base de données',
        'created_by'   => 'Créé par',
        'date'         => 'Date',
        'filename'     => 'Nom du fichier',
        'created_date' => 'Date de création',
    ],

    'restore_modal' => [
        'title'               => 'Confirmer la restauration de la sauvegarde',
        'about'               => 'Vous êtes sur le point de restaurer la sauvegarde :',
        'confirm_instruction' => 'Saisissez <strong>:keyword</strong> pour confirmer',
        'password_label'      => 'Mot de passe du compte actuel',
        'safety_note'         => 'Une sauvegarde de sécurité sera créée automatiquement avant la restauration.',
    ],

    'flash' => [
        'created'          => 'Sauvegarde créée avec succès : :filename',
        'excluded_count'   => '(:count tables exclues)',
        'partial_warning'  => ' — ATTENTION : une ou plusieurs bases de données de module étaient inaccessibles et ont été exclues de la sauvegarde.',
        'error'            => 'Erreur lors de la sauvegarde : :error',
        'invalid_filename' => 'Nom de fichier non valide.',
        'file_not_found'   => 'Fichier introuvable.',
        'read_error'       => 'Erreur de lecture de la sauvegarde : :error',
        'deleted'          => 'Sauvegarde supprimée.',
        'delete_failed'    => 'Impossible de supprimer la sauvegarde.',
        'confirm_invalid'  => 'Confirmation non valide. Saisissez :keyword pour continuer.',
        'password_invalid' => 'Mot de passe actuel non valide.',
        'restored'         => 'Restauration terminée : :filename',
        'restore_failed'   => 'Échec de la restauration : :error',
    ],
    'notif' => [
        'completed_title'      => 'Sauvegarde terminée',
        'restored_title'       => 'Restauration terminée',
        'restored_body'        => 'La sauvegarde :filename a été restaurée avec succès.',
        'restore_failed_title' => 'Échec de la restauration de la sauvegarde',
        'restore_failed_body'  => 'La restauration de :filename a échoué : :error',
    ],

    'widget' => [
        'label'   => 'Sauvegarde base de données',
        'running' => 'Sauvegarde en cours',
        'none'    => 'Aucune sauvegarde effectuée',
        'last'    => 'Dernière sauvegarde · :size',
    ],
];
