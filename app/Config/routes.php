<?php

/**
 * Application routes — non-module routes only.
 * Module routes are loaded automatically by ModuleLoader (from modules.php).
 *
 * $router is an instance of App\Core\Router, available from Application::handleRequest().
 */

use App\Middleware\AuthMiddleware;
use App\Modules\Home\Controllers\ModuleFallbackController;

// Currently all routes are defined in their respective module route files:
// - app/Modules/Auth/routes.php       → login, logout, password change
// - app/Modules/Home/routes.php       → dashboard, preferences
//
// GET / è gestito dal modulo Home (dashboard, richiede AuthMiddleware).

$fallbackRoutes = [
    'Files'    => ['uri' => '/files',    'name' => 'files.index'],
    'Contacts' => ['uri' => '/contacts', 'name' => 'contacts.index'],
    'Tasks' => ['uri' => '/tasks', 'name' => 'tasks.index'],
];

foreach ($fallbackRoutes as $moduleName => $fallback) {
    if (!isModuleEnabled($moduleName)) {
        $router->group(['middleware' => [AuthMiddleware::class]], function ($router) use ($fallback) {
            $router->get($fallback['uri'], [ModuleFallbackController::class, 'redirectToHome'])
                ->name($fallback['name']);
        });
    }
}
