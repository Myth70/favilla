<?php

/**
 * API pública — cadenas de UI (español).
 * Las cabeceras y payloads de la API permanecen neutros (sin localizar).
 */

return [
    'tokens' => [
        'title'    => 'Tokens de API',
        'subtitle' => 'Crea y revoca tokens de acceso personal para la API pública.',
        'api_docs' => 'Documentación de la API',
        'manage_cta' => 'Gestionar tokens de API',

        'created_once_title' => 'Token creado',
        'created_once_hint'  => 'Cópialo ahora: por seguridad no se volverá a mostrar. Úsalo en la cabecera Authorization: Bearer <token>.',

        'create_title'       => 'Nuevo token',
        'field_name'         => 'Nombre',
        'field_name_ph'      => 'Ej. App móvil, Script de copia…',
        'field_expiry'       => 'Caducidad',
        'expiry_never'       => 'Sin caducidad',
        'expiry_30'          => '30 días',
        'expiry_90'          => '90 días',
        'expiry_365'         => '1 año',
        'field_scopes'       => 'Ámbitos',
        'field_scopes_hint'  => 'Selecciona los permisos que el token podrá usar. Debes seleccionar al menos uno.',
        'no_scopes'          => 'No hay permisos disponibles.',
        'create_submit'      => 'Generar token',

        'list_title'    => 'Tokens activos',
        'empty'         => 'No hay tokens activos.',
        'col_name'      => 'Nombre',
        'col_scopes'    => 'Ámbitos',
        'col_expires'   => 'Caducidad',
        'col_last_used' => 'Último uso',
        'scope_full'    => 'Permisos completos',

        'revoke'         => 'Revocar',
        'revoke_confirm' => '¿Revocar este token? Las aplicaciones que lo usan perderán el acceso de inmediato.',

        'flash_created'   => 'Token creado correctamente.',
        'flash_revoked'   => 'Token revocado.',
        'flash_not_found' => 'Token no encontrado.',

        'error_name_required'  => 'El nombre del token es obligatorio.',
        'error_scope_required' => 'Selecciona al menos un ámbito para el token.',
        'error_scope_denied'   => 'Ninguno de los ámbitos solicitados está concedido a tu usuario.',
    ],
];
