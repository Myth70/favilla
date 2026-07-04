<?php

/**
 * Error pages — French.
 */
return [
    'back_home'      => "Retour à l'accueil",
    'is_error_q'     => "Vous pensez que c'est une erreur ?",
    'report_problem' => 'Signaler le problème',

    'not_found' => [
        'title'   => 'Page introuvable',
        'message' => "La page que vous recherchez n'existe pas ou a été déplacée.<br>Vérifiez l'URL ou revenez à l'accueil.",
    ],
    'forbidden' => [
        'title'   => 'Accès refusé',
        'message' => "Vous n'avez pas les permissions nécessaires pour accéder à cette ressource.<br>Contactez l'administrateur si vous pensez qu'il s'agit d'une erreur.",
    ],
    'method_not_allowed' => [
        'title'   => 'Méthode non autorisée',
        'message' => "La méthode HTTP utilisée n'est pas prise en charge pour cette ressource.<br>Assurez-vous d'utiliser la bonne méthode (GET, POST, etc.).",
    ],
    'server_error' => [
        'title'   => 'Erreur interne du serveur',
        'message' => "Une erreur inattendue s'est produite sur le serveur.<br>Le problème a été enregistré et sera analysé dans les meilleurs délais.",
        'help'    => 'Aidez-nous à le résoudre :',
        'report'  => 'signaler cette erreur',
    ],
    'maintenance' => [
        'title'   => 'Mise à jour en cours',
        'badge'   => 'En maintenance',
        'message' => "Le système est temporairement indisponible<br>pour une maintenance planifiée.<br>Veuillez réessayer dans quelques minutes.",
        'footer'  => 'Nous nous excusons pour la gêne occasionnée.',
    ],
];
