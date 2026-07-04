<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use App\Exceptions\HttpRedirectException;
use App\Services\DatabaseSessionHandler;
use App\Services\NonceService;
use App\Services\SessionManager;
use PDO;

class Application
{
    private Container $container;
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        $this->container = Container::getInstance();
        Container::setInstance($this->container);

        $this->container->instance(self::class, $this);
        $this->container->instance(Container::class, $this->container);
    }

    /**
     * Boot the application: load env, configure session, register services.
     */
    public function boot(): void
    {
        if (!file_exists($this->basePath . '/storage/.setup_complete') && PHP_SAPI !== 'cli') {
            // L'env non è ancora caricato, quindi il base path va derivato dalla
            // richiesta: senza, sotto sottocartella (es. /favilla) il redirect
            // assoluto a /setup.php finiva in 404.
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            if (str_ends_with($scriptDir, '/public')) {
                $scriptDir = substr($scriptDir, 0, -strlen('/public'));
            }
            header('Location: ' . rtrim($scriptDir, '/') . '/setup.php');
            exit;
        }

        $this->loadEnvironment();

        // Timezone coerente tra dev/prod — evita discrepanze nei timestamp dei log
        $tz = env('APP_TIMEZONE', 'Europe/Rome');
        date_default_timezone_set($tz);

        $this->registerErrorHandler();
        $this->registerNonceService();
        $this->registerDatabase();
        $this->configureSession();
        $this->registerEventListeners();
    }

    /**
     * Handle the incoming HTTP request.
     * Uses Router to dispatch to the correct controller through the middleware pipeline.
     */
    public function handleRequest(): void
    {
        // Maintenance mode: block everyone except admin
        if (config('app.maintenance')) {
            $roles = $_SESSION['user_roles'] ?? [];
            $isAdmin = is_array($roles) && in_array('admin', $roles, true);
            if (!$isAdmin) {
                $this->container->make(ErrorHandler::class)->renderMaintenancePage();
            }
        }

        // Boot View engine
        $view = new View($this->basePath);
        $this->container->instance(View::class, $view);

        // Share CSP nonce with all views
        $view->share('cspNonce', $this->container->make(NonceService::class)->getNonce());

        // Boot ModuleLoader and load config
        $moduleLoader = new ModuleLoader($this->basePath);
        $moduleLoader->loadConfig();
        $moduleLoader->loadDbOverrides(app(\PDO::class));  // override DB dopo config
        $this->container->instance(ModuleLoader::class, $moduleLoader);

        // NavigationRegistry: fonte unica di voci per le 4 superfici UI (sidebar, user_menu, radial, quick_access).
        // Singleton per request: cache interna delle entry valida per tutti i partial.
        $this->container->instance(
            \App\Services\NavigationRegistry::class,
            new \App\Services\NavigationRegistry($moduleLoader)
        );

        // Create Router
        $router = new Router();
        $this->container->instance(Router::class, $router);

        // Load module routes via ModuleLoader
        $moduleLoader->loadRoutes($router);

        // Load application routes (non-module routes)
        $routeFile = $this->basePath . '/app/Config/routes.php';
        if (file_exists($routeFile)) {
            require $routeFile;
        }

        $uri = $this->getRequestUri();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Dispatch + middleware pipeline. Middleware and Router signal early exits
        // (auth redirects, 403/404/405/429) by throwing HttpRedirectException /
        // HttpException instead of calling exit — so the flow stays catchable here
        // and unit-testable. Other throwables bubble to the global exception handler.
        try {
            // Dispatch: find matching route (throws HttpException on 404/405)
            $match = $router->dispatch($method, $uri);

            // Resolve middleware aliases to class names
            $middlewareClasses = $this->resolveMiddleware($match['middleware']);
            $middlewareClasses = array_values(array_unique(array_merge([
                \App\Middleware\SecurityHeadersMiddleware::class,
            ], $middlewareClasses), SORT_REGULAR));

            // Build the final action: instantiate controller and call method
            $final = function () use ($match, $view) {
                $controller = $this->container->make($match['controller']);

                // Inject View into controllers that extend our base Controller
                if ($controller instanceof Controller) {
                    $controller->setView($view);
                }

                $params = array_values($match['params']);
                call_user_func_array([$controller, $match['method']], $params);
            };

            // Run middleware pipeline → controller
            Middleware::run($middlewareClasses, $final);
        } catch (HttpRedirectException $e) {
            $this->sendRedirect($e);
        } catch (HttpException $e) {
            $this->sendHttpError($e);
        }
    }

    /**
     * Emit an HTTP redirect (302 Location, or HX-Redirect for HTMX requests).
     */
    private function sendRedirect(HttpRedirectException $e): void
    {
        if (headers_sent()) {
            return;
        }
        if ($e->isHtmx()) {
            header('HX-Redirect: ' . $e->getUrl());
            http_response_code(200);
        } else {
            header('Location: ' . $e->getUrl());
        }
    }

    /**
     * Emit an HTTP error response: status + headers, then either a pre-rendered
     * body (e.g. the rate limiter's JSON) or the matching error page.
     */
    private function sendHttpError(HttpException $e): void
    {
        if (!headers_sent()) {
            http_response_code($e->getStatusCode());
            foreach ($e->getHeaders() as $name => $value) {
                header($name . ': ' . $value);
            }
            if ($e->getContentType() !== null) {
                header('Content-Type: ' . $e->getContentType());
            }
        }

        $body = $e->getBody();
        if ($body !== null) {
            echo $body;
            return;
        }

        if ($this->container->has(ErrorHandler::class)) {
            $this->container->make(ErrorHandler::class)->renderErrorPage($e->getStatusCode());
        } else {
            echo '<h1>' . $e->getStatusCode() . '</h1>';
        }
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the Router instance (available after handleRequest boots it).
     */
    public function getRouter(): ?Router
    {
        return $this->container->has(Router::class)
            ? $this->container->make(Router::class)
            : null;
    }

    // ------------------------------------------------------------------
    // Private bootstrap methods
    // ------------------------------------------------------------------

    /**
     * Resolve middleware names to class instances.
     * Supports both FQCN strings and alias mapping.
     */
    private function resolveMiddleware(array $middleware): array
    {
        $aliases = config('middleware', [
            'csrf' => \App\Middleware\CsrfMiddleware::class,
            'auth' => \App\Middleware\AuthMiddleware::class,
            'role' => \App\Middleware\RoleMiddleware::class,
        ]);

        $resolved = [];
        foreach ($middleware as $mw) {
            if (is_object($mw)) {
                // Already instantiated (e.g., RoleMiddleware::withPermission())
                $resolved[] = $mw;
            } else {
                $resolved[] = $aliases[$mw] ?? $mw;
            }
        }
        return $resolved;
    }

    private function loadEnvironment(): void
    {
        // safeLoad (not load) so the app also runs with config supplied purely
        // through the process environment (Docker/12-factor), where no .env file
        // exists. The env() helper falls back to getenv()/$_SERVER in that case.
        $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath);
        $dotenv->safeLoad();

        // Define APP constants used throughout the app
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $this->basePath);
        }
    }

    private function registerErrorHandler(): void
    {
        $handler = new ErrorHandler();
        $handler->register();
        $this->container->instance(ErrorHandler::class, $handler);
    }

    private function registerNonceService(): void
    {
        $this->container->singleton(NonceService::class, NonceService::class);
    }

    private function configureSession(): void
    {
        $lifetime = (int) config('app.session.lifetime', 480) * 60; // minutes → seconds
        // Secure-cookie guidato dal transport reale (HTTPS diretto o via reverse proxy
        // fidato), non da APP_ENV: così sotto TLS i cookie prendono il flag Secure
        // automaticamente — anche con proxy che termina TLS davanti a XAMPP.
        $isSecure = \App\Support\RequestContext::isSecure();

        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_set_cookie_params([
            'lifetime' => 0,          // session cookie (expires when browser closes)
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite'  => 'Strict',
        ]);

        $driver = config('app.session.driver', 'file');
        $pdo = $this->container->make(PDO::class);
        $handler = new DatabaseSessionHandler($pdo);
        $manager = new SessionManager($handler, $driver);

        $this->container->instance(DatabaseSessionHandler::class, $handler);
        $this->container->instance(SessionManager::class, $manager);

        if ($driver === 'database') {
            $manager->start();
        } else {
            $sessionPath = $this->basePath . config('app.session.path', '/storage/sessions');
            if (!is_dir($sessionPath)) {
                mkdir($sessionPath, 0700, true);
            }
            ini_set('session.save_path', $sessionPath);
            $manager->start();
        }
    }

    private function registerEventListeners(): void
    {
        $dispatcher = EventDispatcher::getInstance();
        $this->container->instance(EventDispatcher::class, $dispatcher);

        // Pass Monolog logger so listener errors go to storage/logs/app.log
        if ($this->container->has(ErrorHandler::class)) {
            $dispatcher->setLogger(
                $this->container->make(ErrorHandler::class)->getLogger()
            );
        }

        // Register listeners
        $dispatcher->listen(\App\Events\UserLoggedIn::class, \App\Listeners\LogUserLogin::class);
        $dispatcher->listen(\App\Events\UserCreated::class, \App\Listeners\CopyAdminWidgetLayout::class);
    }

    private function registerDatabase(): void
    {
        $this->container->singleton(PDO::class, function () {
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

        $this->container->singleton(\App\Services\ModuleDatabaseResolver::class, function () {
            return new \App\Services\ModuleDatabaseResolver(
                app(PDO::class),
                app(ModuleLoader::class),
                config('database')
            );
        });
    }

    /**
     * Extract the request URI relative to the application base path.
     */
    private function getRequestUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $basePath = parse_url(config('app.url', ''), PHP_URL_PATH) ?? '';

        // Strip the base path prefix for clean URLs
        $prefix = rtrim($basePath, '/') . rtrim(config('app.base_path', ''), '/');

        if (str_starts_with($uri, $prefix)) {
            $uri = substr($uri, strlen($prefix));
        }

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        return $uri === '' ? '/' : $uri;
    }
}
