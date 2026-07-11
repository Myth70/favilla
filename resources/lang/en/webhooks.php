<?php

/**
 * Outgoing webhooks — UI strings (English).
 */

return [
    'title'        => 'Webhooks',
    'subtitle'     => 'Notify external systems when an event happens, with HMAC signing and automatic retries.',
    'form_subtitle' => 'Configure the destination URL and the events to subscribe it to.',
    'list_title'   => 'Configured endpoints',
    'empty'        => 'No webhook endpoints configured.',
    'back'         => 'Back',
    'cancel'       => 'Cancel',
    'save'         => 'Save',

    'create_title' => 'New webhook',
    'edit_title'   => 'Edit webhook',
    'test_cta'     => 'Send test',
    'delete'       => 'Delete',
    'delete_confirm' => 'Delete this webhook endpoint? Queued deliveries will be removed.',
    'active'       => 'Active',
    'inactive'     => 'Inactive',

    'stat_pending' => 'Queued',
    'stat_sent'    => 'Delivered',
    'stat_failed'  => 'Failed',

    'col_url'      => 'URL',
    'col_events'   => 'Events',
    'col_status'   => 'Status',
    'col_event'    => 'Event',
    'col_attempts' => 'Attempts',
    'col_response' => 'Response',
    'col_created'  => 'Created',

    'field_url'          => 'Destination URL',
    'field_url_hint'     => 'HTTPS only. Private or loopback addresses are blocked (anti-SSRF).',
    'field_description'  => 'Description (optional)',
    'field_active'       => 'Endpoint active',
    'field_events'       => 'Subscribed events',
    'field_events_hint'  => 'The endpoint will receive a signed POST on every selected event.',
    'no_events'          => 'No events available.',

    'secret_once_title'  => 'Signing secret generated',
    'secret_once_hint'   => 'Copy it now: it will not be shown again. Use it to verify the X-Favilla-Signature header (HMAC-SHA256 of the body).',
    'secret_section'     => 'Signing secret',
    'secret_section_hint' => 'Regenerate the secret if you suspect it is compromised. The old secret stops working immediately.',
    'secret_regenerate'  => 'Regenerate secret',
    'secret_regenerate_confirm' => 'Regenerate the secret? Signatures computed with the current one will no longer be valid.',

    'deliveries_title' => 'Delivery log',
    'deliveries_empty' => 'No deliveries recorded for this endpoint.',

    'flash_created'   => 'Webhook endpoint created.',
    'flash_updated'   => 'Webhook endpoint updated.',
    'flash_deleted'   => 'Webhook endpoint deleted.',
    'flash_not_found' => 'Endpoint not found.',
    'flash_secret_regenerated' => 'Secret regenerated.',
    'flash_test_ok'     => 'Test delivery completed.',
    'flash_test_failed' => 'Test delivery failed:',

    'test_sent'   => 'Test delivery succeeded (HTTP :status).',
    'test_failed' => 'Delivery failed: :error',

    'error' => [
        'url_missing'        => 'Missing or overly long URL.',
        'url_invalid'        => 'Invalid URL.',
        'url_credentials'    => 'Credentials in the URL are not allowed.',
        'https_only'         => 'Only HTTPS endpoints are allowed.',
        'scheme_unsupported' => 'Unsupported URL scheme (use https).',
        'unresolvable'       => 'Unable to resolve the destination host.',
        'private_ip'         => 'The destination resolves to a private or reserved IP address.',
        'not_found'          => 'Endpoint not found.',
        'no_events'          => 'Select at least one event to subscribe the endpoint to.',
    ],
];
