<?php

/**
 * Example (_Template) — módulo de demostración (español).
 * Traducción del archivo canónico it/example.php. Ejecutar: php favilla lang:check
 */

return [
    'title'           => 'Ejemplo',
    'count_total'     => ':count registros en total',
    'new_page_title'  => 'Nuevo ejemplo',
    'edit_page_title' => 'Editar ejemplo',
    'breadcrumb_new'  => 'Nuevo',
    'breadcrumb_edit' => 'Editar',

    'status' => [
        'active'   => 'Activo',
        'inactive' => 'Inactivo',
        'archived' => 'Archivado',
    ],

    'badges' => [
        'active'   => ':count activos',
        'inactive' => ':count inactivos',
        'archived' => ':count archivados',
    ],

    'fields' => [
        'id'          => 'ID',
        'name'        => 'Nombre',
        'email'       => 'Correo',
        'description' => 'Descripción',
        'status'      => 'Estado',
        'author'      => 'Autor',
        'created_at'  => 'Creado el',
    ],

    'actions' => [
        'new'    => 'Nuevo',
        'edit'   => 'Editar',
        'create' => 'Crear',
        'update' => 'Actualizar',
        'cancel' => 'Cancelar',
        'delete' => 'Eliminar registro',
        'back'   => 'Volver a la lista',
        'detail' => 'Detalle',
        'reset'  => 'Restablecer',
    ],

    'filters' => [
        'search_placeholder' => 'Buscar...',
        'all_status'         => 'Todos los estados',
    ],

    'sections' => [
        'main'        => 'Datos principales',
        'content'     => 'Contenido y estado',
        'info'        => 'Información',
        'actions'     => 'Acciones',
        'danger_zone' => 'Zona peligrosa',
        'description' => 'Descripción',
    ],

    'form' => [
        'subtitle_new'   => 'Crear un nuevo registro del módulo',
        'subtitle_edit'  => 'Actualizar el registro existente',
        'errors_summary' => 'Corrige los errores indicados.',
    ],

    'feedback' => [
        'name'        => 'Introduce el nombre del registro.',
        'email'       => 'Introduce un correo válido.',
        'description' => 'Revisa el contenido de la descripción.',
        'status'      => 'Selecciona un estado válido.',
    ],

    'list' => [
        'empty'       => 'No se encontraron registros.',
        'col_name'    => 'Nombre',
        'col_email'   => 'Correo',
        'col_status'  => 'Estado',
        'col_created' => 'Creado',
        'col_actions' => 'Acciones',
        'results'     => ':count resultados — página :page de :pages',
    ],

    'confirm' => [
        'delete' => '¿Seguro que quieres eliminar este registro?',
    ],

    'flash' => [
        'created'   => 'Registro creado correctamente.',
        'updated'   => 'Registro actualizado correctamente.',
        'deleted'   => 'Registro eliminado.',
        'not_found' => 'Registro no encontrado.',
    ],

    'detail' => [
        'no_description'    => 'Sin descripción.',
        'last_update'       => 'Última actualización:',
        'subtitle_fallback' => 'Detalles del registro',
    ],
];
