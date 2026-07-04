<?php

declare(strict_types=1);

/**
 * Edition profiles — Favilla ships as Developer / Personal / Team from one codebase.
 * 'default' is rewritten by the release build script (Fase 4); do not rename the key.
 */
return [
    'default' => 'developer',

    'profiles' => [
        'developer' => [
            'label'       => 'Developer',
            'single_user' => false,
            // Moduli le cui voci sidebar vengono nascoste (hidden ≠ disabled).
            'sidebar_hidden_modules' => [],
        ],
        'personal' => [
            'label'       => 'Personal',
            'single_user' => true,
            'sidebar_hidden_modules' => ['Admin', 'Scheduler', 'Feedback'],
        ],
        'team' => [
            'label'       => 'Team',
            'single_user' => false,
            'sidebar_hidden_modules' => [],
            // Moduli opzionali abilitati automaticamente in SetupController::runSetupComplete()
            // quando l'operatore sceglie questa edizione. In personal/developer restano
            // disabilitati di default (seeds/required.sql) e installabili a mano da
            // Admin -> Moduli (percorso di upgrade).
            'default_enabled_modules' => ['Progetti', 'Teams', 'Documenti', 'Blog'],
        ],
    ],
];
