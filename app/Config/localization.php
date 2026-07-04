<?php

/**
 * Localization / i18n configuration.
 *
 * Italian (`it`) is the canonical source locale: every key is authored in
 * `resources/lang/it/*` first and the other locales are translations of it.
 * Fallback chain when a key is missing: active locale -> fallback -> the key.
 *
 * Adding a locale: append its code to `supported`, add it to `names`,
 * `intl_locale`, `currency`, and create `resources/lang/<code>/*.php`,
 * then run `php favilla lang:check --locale=<code>`.
 */
return [
    // Default locale for anonymous visitors with no preference / cookie / header.
    'default'  => env('APP_LOCALE', 'it'),

    // Locale used when a key is missing in the active locale.
    'fallback' => env('APP_FALLBACK_LOCALE', 'it'),

    // Whitelist of enabled locales (2-letter codes). Order = switcher order.
    'supported' => ['it', 'en', 'fr', 'de', 'es'],

    // Endonyms shown in the language switcher.
    'names' => [
        'it' => 'Italiano',
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'es' => 'Español',
    ],

    // Optional flag emoji for the switcher UI.
    'flags' => [
        'it' => '🇮🇹',
        'en' => '🇬🇧',
        'fr' => '🇫🇷',
        'de' => '🇩🇪',
        'es' => '🇪🇸',
    ],

    // Persistence + detection knobs.
    'cookie_name' => 'favilla_lang',
    'query_param' => 'lang',
    'cookie_days'  => 365,

    // Use the intl extension for number/currency formatting when available.
    'intl' => extension_loaded('intl'),

    // Map app locale -> ICU locale for IntlDateFormatter / NumberFormatter.
    'intl_locale' => [
        'it' => 'it_IT',
        'en' => 'en_GB',
        'fr' => 'fr_FR',
        'de' => 'de_DE',
        'es' => 'es_ES',
    ],

    // Default currency per locale (the app is euro-area by default).
    'currency' => [
        'it' => 'EUR',
        'en' => 'EUR',
        'fr' => 'EUR',
        'de' => 'EUR',
        'es' => 'EUR',
    ],
];
