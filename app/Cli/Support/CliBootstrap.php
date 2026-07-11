<?php

declare(strict_types=1);

namespace App\Cli\Support;

use App\Core\Container;
use App\Core\ErrorHandler;
use App\Core\ModuleLoader;
use App\Core\Router;
use PDO;

final class CliBootstrap
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (!defined('BASE_PATH')) {
            throw new \RuntimeException('BASE_PATH non definito per il bootstrap CLI.');
        }

        $container = Container::getInstance();
        Container::setInstance($container);

        if (
            self::$booted
            && $container->has(ErrorHandler::class)
            && $container->has(PDO::class)
            && $container->has(ModuleLoader::class)
            && $container->has(Router::class)
        ) {
            return;
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $_SERVER['REQUEST_METHOD'] ??= 'GET';
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';

        self::ensureLogging();
        self::registerDatabase($container);
        self::registerRouting($container);

        self::$booted = true;
    }

    /**
     * Registra l'ErrorHandler nel container così che app_log() instradi verso
     * Monolog (storage/logs/app.log) anche dai comandi CLI/cron, invece di
     * degradare a error_log(). NON chiama register(): il rendering HTML/JSON
     * dell'ErrorHandler è pensato per il web, non per la console. È cheap (nessuna
     * connessione DB), quindi la Console la invoca per ogni comando.
     */
    public static function ensureLogging(): void
    {
        $container = Container::getInstance();
        Container::setInstance($container);

        if ($container->has(ErrorHandler::class)) {
            return;
        }

        $container->instance(ErrorHandler::class, new ErrorHandler());
    }

    private static function registerDatabase(Container $container): void
    {
        if ($container->has(PDO::class)) {
            return;
        }

        $container->singleton(PDO::class, static function (): PDO {
            $cfg = config('database');
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['name'],
                $cfg['charset']
            );

            return new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options']);
        });

        $container->singleton(\App\Services\ModuleDatabaseResolver::class, static function (): \App\Services\ModuleDatabaseResolver {
            return new \App\Services\ModuleDatabaseResolver(
                app(PDO::class),
                app(ModuleLoader::class),
                config('database')
            );
        });
    }

    private static function registerRouting(Container $container): void
    {
        $pdo = $container->make(PDO::class);

        $moduleLoader = new ModuleLoader(BASE_PATH);
        $moduleLoader->loadConfig();
        $moduleLoader->loadDbOverrides($pdo);
        $container->instance(ModuleLoader::class, $moduleLoader);

        $router = new Router();
        $container->instance(Router::class, $router);

        $moduleLoader->loadRoutes($router);

        $routeFile = BASE_PATH . '/app/Config/routes.php';
        if (file_exists($routeFile)) {
            require $routeFile;
        }
    }
}
