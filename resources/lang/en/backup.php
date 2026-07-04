<?php

/**
 * Backup module — English.
 */
return [
    'title'           => 'Database Backup',
    'hero_title'      => 'Backup',
    'hero_subtitle'   => 'Full compressed SQL backup, with automatic rotation',
    'restore_keyword' => 'RESTORE',

    'action' => [
        'create'      => 'Create Backup',
        'download'    => 'Download',
        'restore'     => 'Restore',
        'restore_now' => 'Restore now',
        'start'       => 'Start',
    ],
    'start_tooltip' => 'Start backup',
    'start_confirm' => 'Start creating the backup? The operation may take a few minutes.',

    'running_title' => 'Backup in progress.',
    'running_body'  => 'Wait for it to finish before starting another one.',

    'note_label'     => 'Note:',
    'note_body'      => 'Backups are stored on the server in <code>storage/backups/</code>. Download them regularly to a safe external location. The latest :count backups are kept automatically.',
    'excluded_label' => 'Excluded tables:',

    'available'      => 'Available backups',
    'history'        => 'Backup history',
    'history_hint'   => '(last 50)',
    'partial'        => 'Partial',
    'empty'          => 'No backups found. Create the first backup using the button above.',
    'delete_confirm' => 'Permanently delete this backup?',

    'cols' => [
        'file'         => 'File',
        'size'         => 'Size',
        'tables'       => 'Tables',
        'database'     => 'Database',
        'created_by'   => 'Created by',
        'date'         => 'Date',
        'filename'     => 'File name',
        'created_date' => 'Creation date',
    ],

    'restore_modal' => [
        'title'               => 'Confirm backup restore',
        'about'               => 'You are about to restore the backup:',
        'confirm_instruction' => 'Type <strong>:keyword</strong> to confirm',
        'password_label'      => 'Current account password',
        'safety_note'         => 'A safety backup will be created automatically before restoring.',
    ],

    'flash' => [
        'created'          => 'Backup created successfully: :filename',
        'excluded_count'   => '(:count tables excluded)',
        'partial_warning'  => ' — WARNING: one or more module databases were unreachable and were excluded from the backup.',
        'error'            => 'Error during backup: :error',
        'invalid_filename' => 'Invalid file name.',
        'file_not_found'   => 'File not found.',
        'read_error'       => 'Backup read error: :error',
        'deleted'          => 'Backup deleted.',
        'delete_failed'    => 'Unable to delete the backup.',
        'confirm_invalid'  => 'Invalid confirmation. Type :keyword to proceed.',
        'password_invalid' => 'Invalid current password.',
        'restored'         => 'Restore completed: :filename',
        'restore_failed'   => 'Restore failed: :error',
    ],
    'notif' => [
        'completed_title'      => 'Backup completed',
        'restored_title'       => 'Restore completed',
        'restored_body'        => 'The backup :filename was restored successfully.',
        'restore_failed_title' => 'Backup restore failed',
        'restore_failed_body'  => 'The restore of :filename failed: :error',
    ],

    'widget' => [
        'label'   => 'Database backup',
        'running' => 'Backup in progress',
        'none'    => 'No backup performed',
        'last'    => 'Last backup · :size',
    ],
];
