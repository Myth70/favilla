<?php

/**
 * Favilla — Setup Wizard (browser-based)
 *
 * Primo accesso: guida passo-passo alla configurazione dell'applicazione.
 * Dopo il completamento viene creato storage/.setup_complete e
 * questo file restituisce 403 a ogni accesso successivo.
 *
 * Standalone: non dipende dal framework (no vendor, no Dotenv, no Bootstrap).
 * Se vendor/autoload.php non esiste mostra istruzioni per composer install.
 */

define('BASE_PATH', __DIR__);

// ------------------------------------------------------------------
// Protezione anti-riesecuzione
// ------------------------------------------------------------------
if (file_exists(BASE_PATH . '/storage/.setup_complete')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Setup</title>'
       . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;'
       . 'justify-content:center;min-height:100vh;background:#f0f2f5;margin:0}'
       . '.box{background:#fff;border-radius:12px;padding:2rem 2.5rem;max-width:450px;'
       . 'box-shadow:0 2px 16px rgba(0,0,0,.1);text-align:center}'
       . 'h1{color:#1e3a5f;margin-bottom:.75rem}p{color:#495057;margin:.5rem 0}'
       . 'a{color:#1e3a5f}</style></head><body>'
       . '<div class="box"><h1>✓ Setup già completato</h1>'
       . '<p>L\'applicazione è già stata configurata.</p>'
       . '<p><a href="public/">Vai all\'applicazione →</a></p></div></body></html>';
    exit;
}

// ------------------------------------------------------------------
// Verifica vendor (composer install)
// ------------------------------------------------------------------
if (!file_exists(BASE_PATH . '/vendor/autoload.php')) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>Setup</title>'
       . '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;'
       . 'justify-content:center;min-height:100vh;background:#f0f2f5;margin:0}'
       . '.box{background:#fff;border-radius:12px;padding:2rem 2.5rem;max-width:500px;'
       . 'box-shadow:0 2px 16px rgba(0,0,0,.1)}'
       . 'h1{color:#1e3a5f;margin-bottom:.75rem}code{background:#f8f9fa;padding:.15rem .4rem;'
       . 'border-radius:4px;font-size:.9rem}</style></head><body>'
       . '<div class="box"><h1>⚙️ Dipendenze mancanti</h1>'
       . '<p>Prima di procedere esegui nella directory del progetto:</p>'
       . '<p><code>composer install</code></p>'
       . '<p>Poi ricarica questa pagina.</p></div></body></html>';
    exit;
}

// ------------------------------------------------------------------
// Bootstrap minimo
// ------------------------------------------------------------------
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/app/Setup/SetupValidator.php';
require_once BASE_PATH . '/app/Setup/SetupController.php';

// ------------------------------------------------------------------
// Avvia il wizard
// ------------------------------------------------------------------
(new App\Setup\SetupController())->handle();
