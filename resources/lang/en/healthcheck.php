<?php

/**
 * HealthCheck module — English.
 */
return [
    'title'         => 'Health Check',
    'subtitle'      => 'Monitoring of system and service status',
    'history_title' => 'Health Check history',
    'breadcrumb_history' => 'History',

    'buttons' => [
        'history'       => 'History',
        'export_csv'    => 'Export CSV',
        'deep_scan'     => 'Deep scan',
        'refresh'       => 'Refresh',
        'back_to_check' => 'Back to Check',
    ],
    'tooltip' => [
        'deep_scan' => 'Also runs the in-depth checks (email DNS, .env exposure, dependency vulnerabilities)',
    ],

    'loading' => 'Running checks…',

    'content' => [
        'deep_scan'    => 'Deep scan',
        'quick_checks' => 'Quick checks — use «Deep scan» for email DNS, .env exposure and dependency vulnerabilities',
        'executed_at'  => 'Run on :date',
        'date_at'      => 'at',
        'all_ok'       => 'All checks are fine. No action required.',
    ],

    'card' => [
        'status_critical' => 'Critical issues found',
        'status_warn'     => 'To review',
        'status_ok'       => 'OK',
        'warnings_tip'    => 'Warnings',
        'errors_tip'      => 'Errors',
    ],

    'summary' => [
        'global_state'    => 'Overall status:',
        'global_critical' => 'Critical',
        'global_warning'  => 'Warning',
        'global_stable'   => 'Stable',
        'ok_checks'       => 'checks OK',
        'warnings'        => 'warnings',
        'errors'          => 'errors',
        'total_run'       => 'checks run',
        'focus_fail'      => 'There are errors that require action.',
        'focus_warn'      => 'The system is operational, but some settings need review.',
        'focus_ok'        => 'All main checks are fine.',
        'issues_to_check' => ':count items to review',
    ],

    'history' => [
        'col_data'       => 'Date',
        'col_ok'         => 'OK',
        'col_warn'       => 'Warnings',
        'col_fail'       => 'Errors',
        'col_executed_by' => 'Run by',
        'empty'          => 'No run recorded.',
        'system'         => 'System',
    ],

    'widget' => [
        'label'     => 'System status',
        'never'     => 'Never run',
        'fail_one'  => '1 check failed',
        'fail_many' => ':count checks failed',
        'warn_one'  => '1 warning',
        'warn_many' => ':count warnings',
        'passed'    => ':count checks passed',
    ],
];
