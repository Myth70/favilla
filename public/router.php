<?php

/**
 * Router per PHP built-in development server.
 *
 * Uso: php -S localhost:8000 -t public/ public/router.php
 *
 * Serve i file statici direttamente (assets, uploads, favicon).
 * Tutte le altre richieste vengono instradate al front controller.
 */

$uri = $_SERVER['REQUEST_URI'];

// Rimuovi la query string dal path per il file check
$path = strtok($uri, '?');

// Se il file fisico esiste nella document root (public/), servilo direttamente
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Il built-in server serve il file direttamente
}

// Altrimenti, instradata al front controller
require_once __DIR__ . '/index.php';
