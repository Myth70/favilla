<?php

/**
 * Front Controller — entry point per tutte le richieste HTTP.
 */

$app = require_once dirname(__DIR__) . '/bootstrap/app.php';

$app->handleRequest();
