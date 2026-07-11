<?php

/**
 * Webhooks salientes — cadenas de UI (español).
 */

return [
    'title'        => 'Webhooks',
    'subtitle'     => 'Notifica a sistemas externos cuando ocurre un evento, con firma HMAC y reintentos automáticos.',
    'form_subtitle' => 'Configura la URL de destino y los eventos a los que suscribirlo.',
    'list_title'   => 'Endpoints configurados',
    'empty'        => 'No hay endpoints de webhook configurados.',
    'back'         => 'Atrás',
    'cancel'       => 'Cancelar',
    'save'         => 'Guardar',

    'create_title' => 'Nuevo webhook',
    'edit_title'   => 'Editar webhook',
    'test_cta'     => 'Enviar prueba',
    'delete'       => 'Eliminar',
    'delete_confirm' => '¿Eliminar este endpoint de webhook? Las entregas en cola se eliminarán.',
    'active'       => 'Activo',
    'inactive'     => 'Inactivo',

    'stat_pending' => 'En cola',
    'stat_sent'    => 'Entregados',
    'stat_failed'  => 'Fallidos',

    'col_url'      => 'URL',
    'col_events'   => 'Eventos',
    'col_status'   => 'Estado',
    'col_event'    => 'Evento',
    'col_attempts' => 'Intentos',
    'col_response' => 'Respuesta',
    'col_created'  => 'Creado',

    'field_url'          => 'URL de destino',
    'field_url_hint'     => 'Solo HTTPS. Las direcciones privadas o de loopback se bloquean (anti-SSRF).',
    'field_description'  => 'Descripción (opcional)',
    'field_active'       => 'Endpoint activo',
    'field_events'       => 'Eventos suscritos',
    'field_events_hint'  => 'El endpoint recibirá un POST firmado en cada evento seleccionado.',
    'no_events'          => 'No hay eventos disponibles.',

    'secret_once_title'  => 'Secret de firma generado',
    'secret_once_hint'   => 'Cópialo ahora: no se volverá a mostrar. Úsalo para verificar la cabecera X-Favilla-Signature (HMAC-SHA256 del cuerpo).',
    'secret_section'     => 'Secret de firma',
    'secret_section_hint' => 'Regenera el secret si sospechas que está comprometido. El secret anterior dejará de funcionar de inmediato.',
    'secret_regenerate'  => 'Regenerar secret',
    'secret_regenerate_confirm' => '¿Regenerar el secret? Las firmas calculadas con el actual dejarán de ser válidas.',

    'deliveries_title' => 'Registro de entregas',
    'deliveries_empty' => 'No hay entregas registradas para este endpoint.',

    'flash_created'   => 'Endpoint de webhook creado.',
    'flash_updated'   => 'Endpoint de webhook actualizado.',
    'flash_deleted'   => 'Endpoint de webhook eliminado.',
    'flash_not_found' => 'Endpoint no encontrado.',
    'flash_secret_regenerated' => 'Secret regenerado.',
    'flash_test_ok'     => 'Entrega de prueba completada.',
    'flash_test_failed' => 'Entrega de prueba fallida:',

    'test_sent'   => 'Entrega de prueba correcta (HTTP :status).',
    'test_failed' => 'Error en la entrega: :error',

    'error' => [
        'url_missing'        => 'URL ausente o demasiado larga.',
        'url_invalid'        => 'URL no válida.',
        'url_credentials'    => 'No se permiten credenciales en la URL.',
        'https_only'         => 'Solo se permiten endpoints HTTPS.',
        'scheme_unsupported' => 'Esquema de URL no admitido (usa https).',
        'unresolvable'       => 'No se puede resolver el host de destino.',
        'private_ip'         => 'El destino resuelve a una dirección IP privada o reservada.',
        'not_found'          => 'Endpoint no encontrado.',
        'no_events'          => 'Selecciona al menos un evento para suscribir el endpoint.',
    ],
];
