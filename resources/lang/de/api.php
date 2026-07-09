<?php

/**
 * Öffentliche API — UI-Strings (Deutsch).
 * API-Header und Payloads bleiben neutral (nicht lokalisiert).
 */

return [
    'tokens' => [
        'title'    => 'API-Token',
        'subtitle' => 'Persönliche Zugriffstoken für die öffentliche API erstellen und widerrufen.',
        'api_docs' => 'API-Dokumentation',
        'manage_cta' => 'API-Token verwalten',

        'created_once_title' => 'Token erstellt',
        'created_once_hint'  => 'Kopiere es jetzt: Aus Sicherheitsgründen wird es nicht erneut angezeigt. Verwende es im Header Authorization: Bearer <token>.',

        'create_title'       => 'Neues Token',
        'field_name'         => 'Name',
        'field_name_ph'      => 'z. B. Mobile App, Backup-Skript…',
        'field_expiry'       => 'Ablauf',
        'expiry_never'       => 'Kein Ablauf',
        'expiry_30'          => '30 Tage',
        'expiry_90'          => '90 Tage',
        'expiry_365'         => '1 Jahr',
        'field_scopes'       => 'Scopes',
        'field_scopes_hint'  => 'Wähle die Berechtigungen, die das Token verwenden darf. Wählst du keine, erbt das Token alle deine Berechtigungen.',
        'no_scopes'          => 'Keine Berechtigungen verfügbar.',
        'create_submit'      => 'Token generieren',

        'list_title'    => 'Aktive Token',
        'empty'         => 'Keine aktiven Token.',
        'col_name'      => 'Name',
        'col_scopes'    => 'Scopes',
        'col_expires'   => 'Ablauf',
        'col_last_used' => 'Zuletzt verwendet',
        'scope_full'    => 'Volle Berechtigungen',

        'revoke'         => 'Widerrufen',
        'revoke_confirm' => 'Dieses Token widerrufen? Anwendungen, die es verwenden, verlieren sofort den Zugriff.',

        'flash_created'   => 'Token erfolgreich erstellt.',
        'flash_revoked'   => 'Token widerrufen.',
        'flash_not_found' => 'Token nicht gefunden.',
    ],
];
