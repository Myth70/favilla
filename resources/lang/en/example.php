<?php

/**
 * Example (_Template) — demo module (English).
 * Translation of the canonical it/example.php. Run: php favilla lang:check
 */

return [
    'title'           => 'Example',
    'count_total'     => ':count total records',
    'new_page_title'  => 'New Example',
    'edit_page_title' => 'Edit Example',
    'breadcrumb_new'  => 'New',
    'breadcrumb_edit' => 'Edit',

    'status' => [
        'active'   => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ],

    'badges' => [
        'active'   => ':count active',
        'inactive' => ':count inactive',
        'archived' => ':count archived',
    ],

    'fields' => [
        'id'          => 'ID',
        'name'        => 'Name',
        'email'       => 'Email',
        'description' => 'Description',
        'status'      => 'Status',
        'author'      => 'Author',
        'created_at'  => 'Created on',
    ],

    'actions' => [
        'new'    => 'New',
        'edit'   => 'Edit',
        'create' => 'Create',
        'update' => 'Update',
        'cancel' => 'Cancel',
        'delete' => 'Delete record',
        'back'   => 'Back to list',
        'detail' => 'Details',
        'reset'  => 'Reset',
    ],

    'filters' => [
        'search_placeholder' => 'Search...',
        'all_status'         => 'All statuses',
    ],

    'sections' => [
        'main'        => 'Main data',
        'content'     => 'Content and status',
        'info'        => 'Information',
        'actions'     => 'Actions',
        'danger_zone' => 'Danger zone',
        'description' => 'Description',
    ],

    'form' => [
        'subtitle_new'   => 'Create a new module record',
        'subtitle_edit'  => 'Update the existing record',
        'errors_summary' => 'Please correct the highlighted errors.',
    ],

    'feedback' => [
        'name'        => 'Enter the record name.',
        'email'       => 'Enter a valid email.',
        'description' => 'Check the description content.',
        'status'      => 'Select a valid status.',
    ],

    'list' => [
        'empty'       => 'No records found.',
        'col_name'    => 'Name',
        'col_email'   => 'Email',
        'col_status'  => 'Status',
        'col_created' => 'Created',
        'col_actions' => 'Actions',
        'results'     => ':count results — page :page of :pages',
    ],

    'confirm' => [
        'delete' => 'Are you sure you want to delete this record?',
    ],

    'flash' => [
        'created'   => 'Record created successfully.',
        'updated'   => 'Record updated successfully.',
        'deleted'   => 'Record deleted.',
        'not_found' => 'Record not found.',
    ],

    'detail' => [
        'no_description'    => 'No description.',
        'last_update'       => 'Last update:',
        'subtitle_fallback' => 'Record details',
    ],
];
