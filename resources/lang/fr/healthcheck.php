<?php

/**
 * HealthCheck module — French.
 */
return [
    'title'         => 'État de santé',
    'subtitle'      => 'Surveillance de l\'état du système et des services',
    'history_title' => 'Historique de l\'état de santé',
    'breadcrumb_history' => 'Historique',

    'buttons' => [
        'history'       => 'Historique',
        'export_csv'    => 'Exporter CSV',
        'deep_scan'     => 'Analyse approfondie',
        'refresh'       => 'Actualiser',
        'back_to_check' => 'Retour au contrôle',
    ],
    'tooltip' => [
        'deep_scan' => 'Exécute aussi les contrôles approfondis (DNS e-mail, exposition .env, vulnérabilités des dépendances)',
    ],

    'loading' => 'Exécution des contrôles…',

    'content' => [
        'deep_scan'    => 'Analyse approfondie',
        'quick_checks' => 'Contrôles rapides — utilisez « Analyse approfondie » pour le DNS e-mail, l\'exposition .env et les vulnérabilités des dépendances',
        'executed_at'  => 'Exécuté le :date',
        'date_at'      => 'à',
        'all_ok'       => 'Tous les contrôles sont corrects. Aucune action requise.',
    ],

    'card' => [
        'status_critical' => 'Problèmes critiques détectés',
        'status_warn'     => 'À vérifier',
        'status_ok'       => 'Correct',
        'warnings_tip'    => 'Avertissements',
        'errors_tip'      => 'Erreurs',
    ],

    'summary' => [
        'global_state'    => 'État général :',
        'global_critical' => 'Critique',
        'global_warning'  => 'Attention',
        'global_stable'   => 'Stable',
        'ok_checks'       => 'contrôles OK',
        'warnings'        => 'avertissements',
        'errors'          => 'erreurs',
        'total_run'       => 'contrôles exécutés',
        'focus_fail'      => 'Des erreurs nécessitent une intervention.',
        'focus_warn'      => 'Le système est opérationnel, mais certaines configurations sont à revoir.',
        'focus_ok'        => 'Tous les contrôles principaux sont corrects.',
        'issues_to_check' => ':count éléments à vérifier',
    ],

    'history' => [
        'col_data'       => 'Date',
        'col_ok'         => 'OK',
        'col_warn'       => 'Avertissements',
        'col_fail'       => 'Erreurs',
        'col_executed_by' => 'Exécuté par',
        'empty'          => 'Aucune exécution enregistrée.',
        'system'         => 'Système',
    ],

    'widget' => [
        'label'     => 'État du système',
        'never'     => 'Jamais exécuté',
        'fail_one'  => '1 contrôle échoué',
        'fail_many' => ':count contrôles échoués',
        'warn_one'  => '1 avertissement',
        'warn_many' => ':count avertissements',
        'passed'    => ':count contrôles réussis',
    ],
];
