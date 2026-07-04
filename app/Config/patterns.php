<?php

/**
 * Background pattern registry — singola fonte di verità.
 *
 * Chiave = nome tecnico (usato in CSS, DB, sessione).
 * Valore = label visualizzata (UI).
 */
return [
    'default'    => 'circles',
    'css_prefix' => 'pf-pattern-',

    'items' => [
        'circles'   => 'Boreale',
        'triangles' => 'Onde',
        'hexagons'  => 'Cosmo',
        'diamonds'  => 'Cristallo',
        'pentagons' => 'Fiamma',
        'lines'     => 'Raggi',
        'curves'    => 'Vortice',
        'grid'      => 'Eclissi',
        'chevrons'  => 'Duna',
        'mesh'      => 'Prisma',
    ],
];
