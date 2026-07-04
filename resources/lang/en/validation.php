<?php

/**
 * Validation messages — English.
 * Placeholders: :field (label), :min, :max, :date.
 */
return [
    'required'            => 'The :field field is required.',
    'email'               => 'The :field field must be a valid email address.',
    'min'                 => 'The :field field must be at least :min characters.',
    'max'                 => 'The :field field may not exceed :max characters.',
    'confirmed'           => 'The :field confirmation does not match.',
    'unique'              => 'The :field value is already in use.',
    'unique_check_failed' => 'Unable to verify the uniqueness of :field.',
    'in'                  => 'The :field value is invalid.',
    'regex'               => 'The :field format is invalid.',
    'regex_invalid'       => 'Invalid validation pattern for :field.',
    'config_invalid'      => 'Invalid validation configuration for :field.',
    'numeric'             => 'The :field field must be a number.',
    'integer'             => 'The :field field must be an integer.',
    'url'                 => 'The :field field must be a valid URL.',
    'date'                => 'The :field field must be a valid date.',
    'before'              => 'The :field field must be a date before :date.',
    'after'               => 'The :field field must be a date after :date.',
];
