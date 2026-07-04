<?php

/**
 * HealthCheck module — Spanish.
 */
return [
    'title'         => 'Estado del sistema',
    'subtitle'      => 'Monitorización del estado del sistema y los servicios',
    'history_title' => 'Historial del estado del sistema',
    'breadcrumb_history' => 'Historial',

    'buttons' => [
        'history'       => 'Historial',
        'export_csv'    => 'Exportar CSV',
        'deep_scan'     => 'Análisis profundo',
        'refresh'       => 'Actualizar',
        'back_to_check' => 'Volver al control',
    ],
    'tooltip' => [
        'deep_scan' => 'Ejecuta también los controles en profundidad (DNS de correo, exposición .env, vulnerabilidades de dependencias)',
    ],

    'loading' => 'Ejecutando controles…',

    'content' => [
        'deep_scan'    => 'Análisis profundo',
        'quick_checks' => 'Controles rápidos — usa «Análisis profundo» para DNS de correo, exposición .env y vulnerabilidades de dependencias',
        'executed_at'  => 'Ejecutado el :date',
        'date_at'      => 'a las',
        'all_ok'       => 'Todos los controles son correctos. No se requiere ninguna acción.',
    ],

    'card' => [
        'status_critical' => 'Problemas críticos detectados',
        'status_warn'     => 'Por revisar',
        'status_ok'       => 'Correcto',
        'warnings_tip'    => 'Avisos',
        'errors_tip'      => 'Errores',
    ],

    'summary' => [
        'global_state'    => 'Estado general:',
        'global_critical' => 'Crítico',
        'global_warning'  => 'Atención',
        'global_stable'   => 'Estable',
        'ok_checks'       => 'controles OK',
        'warnings'        => 'avisos',
        'errors'          => 'errores',
        'total_run'       => 'controles ejecutados',
        'focus_fail'      => 'Hay errores que requieren intervención.',
        'focus_warn'      => 'El sistema está operativo, pero hay configuraciones que revisar.',
        'focus_ok'        => 'Todos los controles principales son correctos.',
        'issues_to_check' => ':count elementos por revisar',
    ],

    'history' => [
        'col_data'       => 'Fecha',
        'col_ok'         => 'OK',
        'col_warn'       => 'Avisos',
        'col_fail'       => 'Errores',
        'col_executed_by' => 'Ejecutado por',
        'empty'          => 'No hay ejecuciones registradas.',
        'system'         => 'Sistema',
    ],

    'widget' => [
        'label'     => 'Estado del sistema',
        'never'     => 'Nunca ejecutado',
        'fail_one'  => '1 control fallido',
        'fail_many' => ':count controles fallidos',
        'warn_one'  => '1 aviso',
        'warn_many' => ':count avisos',
        'passed'    => ':count controles superados',
    ],
];
