<?php

/**
 * Error pages — English.
 */
return [
    'back_home'      => 'Back to home',
    'is_error_q'     => 'Think this is a mistake?',
    'report_problem' => 'Report the problem',

    'not_found' => [
        'title'   => 'Page not found',
        'message' => "The page you are looking for does not exist or has been moved.<br>Check the URL or go back home.",
    ],
    'forbidden' => [
        'title'   => 'Access denied',
        'message' => "You do not have the permissions required to access this resource.<br>Contact the administrator if you think this is a mistake.",
    ],
    'method_not_allowed' => [
        'title'   => 'Method not allowed',
        'message' => 'The HTTP method used is not supported for this resource.<br>Make sure you are using the correct method (GET, POST, etc.).',
    ],
    'server_error' => [
        'title'   => 'Internal server error',
        'message' => 'An unexpected error occurred on the server.<br>The problem has been logged and will be investigated as soon as possible.',
        'help'    => 'Help us fix it:',
        'report'  => 'report this error',
    ],
    'maintenance' => [
        'title'   => 'Update in progress',
        'badge'   => 'Under maintenance',
        'message' => 'The system is temporarily unavailable<br>for scheduled maintenance.<br>Please try again in a few minutes.',
        'footer'  => 'We apologize for the inconvenience.',
    ],
];
