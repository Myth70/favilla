<?php

/**
 * Feedback module — English.
 */
return [
    'admin_title'    => 'Feedback',
    'admin_subtitle' => 'Bugs and feature requests submitted by users, with technical context and triage.',
    'report_title'   => 'Report a problem',

    'tipi' => [
        'bug'          => 'Bug',
        'funzionalita' => 'Feature',
        'domanda'      => 'Question',
    ],
    'severita' => [
        'bassa'   => 'Low',
        'media'   => 'Medium',
        'alta'    => 'High',
        'critica' => 'Critical',
    ],
    'stati' => [
        'nuova'           => 'New',
        'in_lavorazione'  => 'In progress',
        'risolta'         => 'Resolved',
        'chiusa'          => 'Closed',
        'non_risolvibile' => 'Not solvable',
    ],

    'form' => [
        'tipo'              => 'Type',
        'severita'          => 'Severity',
        'titolo'            => 'Title',
        'optional'          => '(optional)',
        'titolo_placeholder' => 'Short summary',
        'titolo_placeholder_long' => 'Short summary of the problem',
        'what_happened'     => 'What happened?',
        'descr_placeholder' => 'Describe the problem you encountered...',
        'descr_placeholder_long' => 'Describe the problem or feature that does not behave as expected...',
        'descr_invalid'     => 'Enter a description.',
        'steps'             => 'Steps to reproduce',
        'steps_placeholder' => '1) ... 2) ... 3) ...',
        'steps_placeholder_long' => '1) Go to... 2) Click on... 3) It happens...',
        'submit'            => 'Send feedback',
    ],

    'report' => [
        'warning'      => 'You are reporting a problem',
        'error_code'   => '(error :code)',
        'on_page'      => 'on the page:',
        'intro'        => 'Describe what happened. We automatically attach the page address and server-side environment data to help reproduce the problem.',
    ],

    'launcher' => [
        'intro'           => 'Describe what is wrong: the technical environment (page, module, errors, action sequence) is attached automatically to help reproduce the problem.',
        'attached_label'  => 'What gets attached',
    ],

    'filters' => [
        'search'             => 'Search',
        'search_placeholder' => 'Title, description, code...',
        'stato'              => 'Status',
        'tipo'               => 'Type',
        'severita'           => 'Severity',
        'modulo'             => 'Module',
        'all_m'              => 'All',
        'all_f'              => 'All',
    ],

    'table' => [
        'col_code'    => 'Code',
        'col_tipo'    => 'Type',
        'col_severita' => 'Severity',
        'col_stato'   => 'Status',
        'col_modulo'  => 'Module',
        'col_titolo'  => 'Title',
        'col_autore'  => 'Author',
        'col_data'    => 'Date',
        'empty'       => 'No feedback found.',
        'open_detail' => 'Open detail',
        'label'       => 'feedback items',
    ],

    'detail' => [
        'copy_llm'         => 'Copy for LLM',
        'list'             => 'List',
        'severity_prefix'  => 'Severity:',
        'subtitle'         => 'Feedback of type <strong>:type</strong> · status <strong>:status</strong>',
        'description'      => 'Description',
        'steps'            => 'Steps to reproduce',
        'captured_errors'  => 'Captured errors',
        'no_errors'        => 'No JS/HTMX error captured during the session.',
        'action_sequence'  => 'Action sequence (automatic breadcrumb)',
        'no_interactions'  => 'No interaction recorded.',
        'crumb_nav'        => 'navigation →',
        'crumb_click'      => 'click on',
        'dom_available'    => 'DOM snapshot available',
        'dom_desc'         => 'Page HTML at the time of the report (inputs masked, scripts removed). Download and open it locally &mdash; it is not executed in the app context.',
        'download_dom'     => 'Download DOM',
        'dom_deleted'      => 'DOM snapshot deleted when the feedback was closed (data minimization).',
        'full_context'     => 'Full context (JSON)',
        'show_hide_json'   => 'Show/hide raw JSON',
        'environment'      => 'Environment',
        'management'       => 'Management',
        'assigned_to'      => 'Assigned to',
        'not_assigned'     => '— Not assigned —',
        'admin_notes'      => 'Admin notes',
        'delete'           => 'Delete',
        'delete_desc'      => 'Deletion is reversible from the database (soft delete), but the feedback disappears from the console.',
        'delete_confirm'   => 'Delete feedback :ref?',
        'delete_btn'       => 'Delete feedback',
    ],
    'env' => [
        'autore'       => 'Author',
        'ruoli'        => 'Roles',
        'data'         => 'Date',
        'app_version'  => 'App version',
        'php'          => 'PHP',
        'ip'           => 'IP',
        'modulo'       => 'Module',
        'route'        => 'Route',
        'viewport'     => 'Viewport',
        'lingua'       => 'Language',
        'user_agent'   => 'User agent',
    ],

    'flash' => [
        'save_error'   => 'Error while saving the feedback.',
        'sent'         => 'Feedback sent. Thank you! Reference: :ref',
        'not_found'    => 'Feedback not found.',
        'updated'      => 'Feedback updated.',
        'update_failed' => 'Update failed.',
        'deleted'      => 'Feedback deleted.',
        'dom_unavailable' => 'DOM snapshot not available.',
    ],

    'widget' => [
        'label'    => 'Open reports',
        'new_sub'  => ':count new to triage',
        'none_new' => 'None new',
    ],
];
