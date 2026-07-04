<?php

/**
 * Feedback module — Spanish.
 */
return [
    'admin_title'    => 'Incidencias',
    'admin_subtitle' => 'Errores y solicitudes de funcionalidades enviados por los usuarios, con contexto técnico y triaje.',
    'report_title'   => 'Informar de un problema',

    'tipi' => [
        'bug'          => 'Error',
        'funzionalita' => 'Funcionalidad',
        'domanda'      => 'Pregunta',
    ],
    'severita' => [
        'bassa'   => 'Baja',
        'media'   => 'Media',
        'alta'    => 'Alta',
        'critica' => 'Crítica',
    ],
    'stati' => [
        'nuova'           => 'Nueva',
        'in_lavorazione'  => 'En curso',
        'risolta'         => 'Resuelta',
        'chiusa'          => 'Cerrada',
        'non_risolvibile' => 'No resoluble',
    ],

    'form' => [
        'tipo'              => 'Tipo',
        'severita'          => 'Gravedad',
        'titolo'            => 'Título',
        'optional'          => '(opcional)',
        'titolo_placeholder' => 'Resumen breve',
        'titolo_placeholder_long' => 'Resumen breve del problema',
        'what_happened'     => '¿Qué ha pasado?',
        'descr_placeholder' => 'Describe el problema encontrado...',
        'descr_placeholder_long' => 'Describe el problema o la funcionalidad que no se comporta como se espera...',
        'descr_invalid'     => 'Introduce una descripción.',
        'steps'             => 'Pasos para reproducir',
        'steps_placeholder' => '1) ... 2) ... 3) ...',
        'steps_placeholder_long' => '1) Ve a... 2) Haz clic en... 3) Ocurre...',
        'submit'            => 'Enviar incidencia',
    ],

    'report' => [
        'warning'      => 'Estás informando de un problema',
        'error_code'   => '(error :code)',
        'on_page'      => 'en la página:',
        'intro'        => 'Describe qué ha pasado. Adjuntamos automáticamente la dirección de la página y los datos de entorno del servidor para ayudar a reproducir el problema.',
    ],

    'launcher' => [
        'intro'           => 'Describe qué falla: el entorno técnico (página, módulo, errores, secuencia de acciones) se adjunta automáticamente para ayudar a reproducir el problema.',
        'attached_label'  => 'Qué se adjunta',
    ],

    'filters' => [
        'search'             => 'Buscar',
        'search_placeholder' => 'Título, descripción, código...',
        'stato'              => 'Estado',
        'tipo'               => 'Tipo',
        'severita'           => 'Gravedad',
        'modulo'             => 'Módulo',
        'all_m'              => 'Todos',
        'all_f'              => 'Todas',
    ],

    'table' => [
        'col_code'    => 'Código',
        'col_tipo'    => 'Tipo',
        'col_severita' => 'Gravedad',
        'col_stato'   => 'Estado',
        'col_modulo'  => 'Módulo',
        'col_titolo'  => 'Título',
        'col_autore'  => 'Autor',
        'col_data'    => 'Fecha',
        'empty'       => 'No se han encontrado incidencias.',
        'open_detail' => 'Abrir detalle',
        'label'       => 'incidencias',
    ],

    'detail' => [
        'copy_llm'         => 'Copiar para LLM',
        'list'             => 'Lista',
        'severity_prefix'  => 'Gravedad:',
        'subtitle'         => 'Incidencia de tipo <strong>:type</strong> · estado <strong>:status</strong>',
        'description'      => 'Descripción',
        'steps'            => 'Pasos para reproducir',
        'captured_errors'  => 'Errores capturados',
        'no_errors'        => 'Ningún error JS/HTMX capturado durante la sesión.',
        'action_sequence'  => 'Secuencia de acciones (rastro automático)',
        'no_interactions'  => 'Ninguna interacción registrada.',
        'crumb_nav'        => 'navegación →',
        'crumb_click'      => 'clic en',
        'dom_available'    => 'Instantánea DOM disponible',
        'dom_desc'         => 'HTML de la página en el momento de la incidencia (entradas enmascaradas, scripts eliminados). Descárgalo y ábrelo en local &mdash; no se ejecuta en el contexto de la aplicación.',
        'download_dom'     => 'Descargar DOM',
        'dom_deleted'      => 'Instantánea DOM eliminada al cerrar la incidencia (minimización de datos).',
        'full_context'     => 'Contexto completo (JSON)',
        'show_hide_json'   => 'Mostrar/ocultar JSON sin procesar',
        'environment'      => 'Entorno',
        'management'       => 'Gestión',
        'assigned_to'      => 'Asignada a',
        'not_assigned'     => '— Sin asignar —',
        'admin_notes'      => 'Notas de admin',
        'delete'           => 'Eliminar',
        'delete_desc'      => 'La eliminación es reversible desde la base de datos (borrado lógico), pero la incidencia desaparece de la consola.',
        'delete_confirm'   => '¿Eliminar la incidencia :ref?',
        'delete_btn'       => 'Eliminar incidencia',
    ],
    'env' => [
        'autore'       => 'Autor',
        'ruoli'        => 'Roles',
        'data'         => 'Fecha',
        'app_version'  => 'Versión de la app',
        'php'          => 'PHP',
        'ip'           => 'IP',
        'modulo'       => 'Módulo',
        'route'        => 'Ruta',
        'viewport'     => 'Viewport',
        'lingua'       => 'Idioma',
        'user_agent'   => 'User agent',
    ],

    'flash' => [
        'save_error'   => 'Error al guardar la incidencia.',
        'sent'         => 'Incidencia enviada. ¡Gracias! Referencia: :ref',
        'not_found'    => 'Incidencia no encontrada.',
        'updated'      => 'Incidencia actualizada.',
        'update_failed' => 'Error en la actualización.',
        'deleted'      => 'Incidencia eliminada.',
        'dom_unavailable' => 'Instantánea DOM no disponible.',
    ],

    'widget' => [
        'label'    => 'Reportes abiertos',
        'new_sub'  => ':count nuevos por clasificar',
        'none_new' => 'Ninguno nuevo',
    ],
];
