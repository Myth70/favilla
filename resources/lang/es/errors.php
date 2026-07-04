<?php

/**
 * Error pages — Spanish.
 */
return [
    'back_home'      => 'Volver al inicio',
    'is_error_q'     => '¿Crees que es un error?',
    'report_problem' => 'Informar del problema',

    'not_found' => [
        'title'   => 'Página no encontrada',
        'message' => 'La página que buscas no existe o se ha movido.<br>Comprueba la URL o vuelve al inicio.',
    ],
    'forbidden' => [
        'title'   => 'Acceso denegado',
        'message' => 'No tienes los permisos necesarios para acceder a este recurso.<br>Contacta con el administrador si crees que es un error.',
    ],
    'method_not_allowed' => [
        'title'   => 'Método no permitido',
        'message' => 'El método HTTP utilizado no es compatible con este recurso.<br>Asegúrate de usar el método correcto (GET, POST, etc.).',
    ],
    'server_error' => [
        'title'   => 'Error interno del servidor',
        'message' => 'Se ha producido un error inesperado en el servidor.<br>El problema se ha registrado y se analizará lo antes posible.',
        'help'    => 'Ayúdanos a resolverlo:',
        'report'  => 'informar de este error',
    ],
    'maintenance' => [
        'title'   => 'Actualización en curso',
        'badge'   => 'En mantenimiento',
        'message' => 'El sistema no está disponible temporalmente<br>por mantenimiento programado.<br>Vuelve a intentarlo en unos minutos.',
        'footer'  => 'Disculpa las molestias.',
    ],
];
