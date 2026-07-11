<?php

/**
 * Public API — UI strings (English).
 * API headers and payloads stay neutral (not localized).
 */

return [
    'tokens' => [
        'title'    => 'API tokens',
        'subtitle' => 'Create and revoke personal access tokens for the public API.',
        'api_docs' => 'API documentation',
        'manage_cta' => 'Manage API tokens',

        'created_once_title' => 'Token created',
        'created_once_hint'  => 'Copy it now: for security it will not be shown again. Use it in the Authorization: Bearer <token> header.',

        'create_title'       => 'New token',
        'field_name'         => 'Name',
        'field_name_ph'      => 'E.g. Mobile app, Backup script…',
        'field_expiry'       => 'Expiry',
        'expiry_never'       => 'No expiry',
        'expiry_30'          => '30 days',
        'expiry_90'          => '90 days',
        'expiry_365'         => '1 year',
        'field_scopes'       => 'Scopes',
        'field_scopes_hint'  => 'Select the permissions the token may use. You must select at least one.',
        'no_scopes'          => 'No permissions available.',
        'create_submit'      => 'Generate token',

        'list_title'    => 'Active tokens',
        'empty'         => 'No active tokens.',
        'col_name'      => 'Name',
        'col_scopes'    => 'Scopes',
        'col_expires'   => 'Expiry',
        'col_last_used' => 'Last used',
        'scope_full'    => 'Full permissions',

        'revoke'         => 'Revoke',
        'revoke_confirm' => 'Revoke this token? Applications using it will lose access immediately.',

        'flash_created'   => 'Token created successfully.',
        'flash_revoked'   => 'Token revoked.',
        'flash_not_found' => 'Token not found.',

        'error_name_required'  => 'The token name is required.',
        'error_scope_required' => 'Select at least one scope for the token.',
        'error_scope_denied'   => 'None of the requested scopes are granted to your user.',
    ],
];
