<?php

/**
 * Backup module — Spanish.
 */
return [
    'title'           => 'Copia de seguridad',
    'hero_title'      => 'Copia de seguridad',
    'hero_subtitle'   => 'Copia completa de la base de datos y de los archivos subidos, con rotación automática',
    'restore_keyword' => 'RESTAURAR',

    'action' => [
        'create'      => 'Crear copia',
        'download'    => 'Descargar',
        'restore'     => 'Restaurar',
        'restore_now' => 'Restaurar ahora',
        'start'       => 'Iniciar',
    ],
    'start_tooltip' => 'Iniciar copia de seguridad',
    'start_confirm' => '¿Iniciar la creación de la copia de seguridad? La operación puede tardar unos minutos.',

    'running_title' => 'Copia de seguridad en curso.',
    'running_body'  => 'Espera a que termine antes de iniciar otra.',

    'note_label'     => 'Nota:',
    'note_body'      => 'Las copias se almacenan en el servidor en <code>storage/backups/</code>. Descárgalas con regularidad a un lugar externo seguro. Las últimas :count copias se conservan automáticamente.',
    'note_files_on'  => 'La copia también incluye los archivos subidos (:paths).',
    'note_files_off' => 'Copia solo de base de datos: los archivos subidos NO están incluidos (BACKUP_INCLUDE_FILES=false).',
    'excluded_label' => 'Tablas excluidas:',

    'available'      => 'Copias disponibles',
    'history'        => 'Historial de copias',
    'history_hint'   => '(últimas 50)',
    'partial'        => 'Parcial',
    'empty'          => 'No se han encontrado copias. Crea la primera copia con el botón de arriba.',
    'delete_confirm' => '¿Eliminar definitivamente esta copia?',

    'cols' => [
        'file'         => 'Archivo',
        'size'         => 'Tamaño',
        'tables'       => 'Tablas',
        'database'     => 'Base de datos',
        'files'        => 'Archivos de usuario',
        'created_by'   => 'Creado por',
        'date'         => 'Fecha',
        'filename'     => 'Nombre de archivo',
        'created_date' => 'Fecha de creación',
    ],

    'files_summary' => ':count archivos · :size MB',
    'files_tooltip' => 'Archivos subidos incluidos en el archivo comprimido',
    'files_db_only' => 'Solo BD',

    'restore_modal' => [
        'title'               => 'Confirmar restauración de la copia',
        'about'               => 'Estás a punto de restaurar la copia:',
        'confirm_instruction' => 'Escribe <strong>:keyword</strong> para confirmar',
        'password_label'      => 'Contraseña de la cuenta actual',
        'safety_note'         => 'Antes de restaurar se creará automáticamente una copia de seguridad.',
    ],

    'flash' => [
        'created'          => 'Copia creada correctamente: :filename',
        'files_included'   => 'Se incluyeron :count archivos (:size MB).',
        'restored_files'   => 'También se restauraron :count archivos.',
        'excluded_count'   => '(:count tablas excluidas)',
        'partial_warning'  => ' — ATENCIÓN: una o más bases de datos de módulo no eran accesibles y se han excluido de la copia.',
        'error'            => 'Error durante la copia: :error',
        'invalid_filename' => 'Nombre de archivo no válido.',
        'file_not_found'   => 'Archivo no encontrado.',
        'read_error'       => 'Error al leer la copia: :error',
        'deleted'          => 'Copia eliminada.',
        'delete_failed'    => 'No se ha podido eliminar la copia.',
        'confirm_invalid'  => 'Confirmación no válida. Escribe :keyword para continuar.',
        'password_invalid' => 'Contraseña actual no válida.',
        'restored'         => 'Restauración completada: :filename',
        'restore_failed'   => 'Error en la restauración: :error',
    ],
    'notif' => [
        'completed_title'      => 'Copia completada',
        'restored_title'       => 'Restauración completada',
        'restored_body'        => 'La copia :filename se ha restaurado correctamente.',
        'restore_failed_title' => 'Error en la restauración de la copia',
        'restore_failed_body'  => 'La restauración de :filename ha fallado: :error',
    ],

    'widget' => [
        'label'   => 'Copia de seguridad de la BD',
        'running' => 'Copia en curso',
        'none'    => 'Ninguna copia realizada',
        'last'    => 'Última copia · :size',
    ],
];
