<?php

/**
 * PHPStan analysis bootstrap.
 *
 * BASE_PATH is defined at runtime by every entry point (public/index.php,
 * the `favilla` CLI, setup.php, database/migrate.php). Define it here so
 * static analysis does not report it as an unknown constant.
 */

require_once __DIR__ . '/vendor/autoload.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
