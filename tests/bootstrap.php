<?php

/**
 * Bootstrap PHPUnit — carica autoload Composer + helper globali.
 * Necessario perché app/Helpers/functions.php non è in composer autoload "files".
 */

define('BASE_PATH', dirname(__DIR__));

// Flag globale: abilita i seam test-only (es. Controller::redirect()/json() lanciano
// HaltResponse invece di header()+exit, così le action dei controller sono testabili).
define('FAVILLA_TESTING', true);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
