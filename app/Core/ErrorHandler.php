<?php

declare(strict_types=1);

namespace App\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class ErrorHandler
{
    private Logger $logger;
    private bool $debug;
    private string $basePath;

    public function __construct()
    {
        $this->debug = (env('APP_ENV', 'production') === 'development')
                    && env('APP_DEBUG', false);

        $this->basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

        $logPath = rtrim(env('LOG_PATH', '/storage/logs'), '/');
        $logFile = $this->basePath . $logPath . '/app.log';

        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $levelStr = strtolower(env('LOG_LEVEL', 'debug'));
        $level = match ($levelStr) {
            'emergency' => Level::Emergency,
            'alert'     => Level::Alert,
            'critical'  => Level::Critical,
            'error'     => Level::Error,
            'warning'   => Level::Warning,
            'notice'    => Level::Notice,
            'info'      => Level::Info,
            default     => Level::Debug,
        };

        $this->logger = new Logger('intranet');
        $this->logger->pushHandler(new StreamHandler($logFile, $level));
    }

    /**
     * Register global error and exception handlers.
     */
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Convert PHP errors to ErrorException.
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle uncaught exceptions — renders HTML error page, debug output, or JSON.
     */
    public function handleException(\Throwable $e): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        if (!headers_sent()) {
            http_response_code(500);
        }

        // API v1 requests get the standard { "error": { code, message } } envelope,
        // never an HTML page (path-based: works even when middleware never ran).
        if ($this->isApiRequest()) {
            $this->renderApiError(500, $this->debug ? $e->getMessage() : '');
            return;
        }

        // JSON response for API requests (Accept: application/json)
        if ($this->expectsJson()) {
            $this->renderJsonError($e);
            return;
        }

        // HTMX: return error snippet with toast trigger
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            $this->renderHtmxError($e);
            return;
        }

        if ($this->debug) {
            $this->renderDebugError($e);
            exit;
        } else {
            $this->renderErrorPage(500);
        }
    }

    /**
     * Handle fatal shutdown errors.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->handleException(
                new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    /**
     * Render an HTML error page by status code.
     * Error pages use inline Bootstrap — NO DB or session dependencies.
     */
    public function renderErrorPage(int $code, string $message = ''): void
    {
        if (!headers_sent()) {
            http_response_code($code);
        }

        // API v1 (e.g. 404 on an unknown /api/v1/* path, or a 405) must return the
        // JSON envelope, not the HTML error page.
        if ($this->isApiRequest()) {
            $this->renderApiError($code, $message);
            return;
        }

        $file = $this->basePath . '/app/Views/errors/' . $code . '.php';
        if (file_exists($file)) {
            $errorMessage = $message;
            include $file;
        } else {
            echo "<h1>{$code}</h1><p>An error occurred.</p>";
        }
        exit;
    }

    /**
     * Render the maintenance page.
     */
    public function renderMaintenancePage(): void
    {
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3600');
        }

        $file = $this->basePath . '/app/Views/errors/maintenance.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<h1>503 — Manutenzione in corso</h1>';
        }
        exit;
    }

    /**
     * Render a debug-friendly error page (development only).
     */
    private function renderDebugError(\Throwable $e): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        $class = e(get_class($e));
        $msg = e($e->getMessage());
        $fileInfo = e($e->getFile()) . ':' . $e->getLine();
        $trace = e($e->getTraceAsString());

        echo <<<HTML
        <!DOCTYPE html>
        <html lang="it"><head><meta charset="UTF-8"><title>500 — Error</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 2rem; background: #1e293b; color: #e2e8f0; }
            .error-box { background: #0f172a; border-radius: 8px; padding: 2rem; max-width: 900px; margin: 2rem auto; }
            h1 { color: #f87171; margin: 0 0 1rem; }
            .meta { color: #94a3b8; font-size: 0.9rem; margin-bottom: 1rem; }
            pre { background: #020617; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.85rem; color: #cbd5e1; }
        </style></head><body>
        <div class="error-box">
            <h1>500 — {$class}</h1>
            <p>{$msg}</p>
            <p class="meta">{$fileInfo}</p>
            <pre>{$trace}</pre>
        </div>
        </body></html>
        HTML;
    }

    /**
     * True if the current request targets the versioned public API (/api/v1/…).
     * Path-based so it works even for 404s where no middleware/context ran.
     */
    private function isApiRequest(): bool
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        return (bool) preg_match('#/api/v\d+(/|$)#', $path);
    }

    /**
     * Render the API v1 error envelope: { "error": { "code", "message" } }.
     * Messages stay neutral (English), consistent with the API contract.
     */
    private function renderApiError(int $status, string $message = ''): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $codes = [
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            415 => 'unsupported_media_type',
            422 => 'validation_failed',
            429 => 'too_many_requests',
            500 => 'server_error',
        ];
        $defaults = [
            400 => 'Bad request.',
            401 => 'Authentication required.',
            403 => 'Forbidden.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            415 => 'Unsupported media type.',
            422 => 'Validation failed.',
            429 => 'Too many requests.',
            500 => 'Internal server error.',
        ];

        $payload = ['error' => [
            'code'    => $codes[$status] ?? 'error',
            'message' => $message !== '' ? $message : ($defaults[$status] ?? 'Request failed.'),
        ]];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Check if the request expects a JSON response.
     */
    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || strtolower($xRequestedWith) === 'xmlhttprequest';
    }

    /**
     * Render a JSON error response.
     */
    private function renderJsonError(\Throwable $e): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $payload = ['error' => true, 'message' => 'Errore interno del server.'];

        if ($this->debug) {
            $payload['exception'] = get_class($e);
            $payload['message'] = $e->getMessage();
            $payload['file'] = $e->getFile() . ':' . $e->getLine();
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Render an error for HTMX requests — returns empty body + HX-Trigger toast.
     */
    private function renderHtmxError(\Throwable $e): void
    {
        $message = $this->debug
            ? e(get_class($e) . ': ' . $e->getMessage())
            : 'Errore interno del server.';

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('HX-Trigger: ' . json_encode([
                'notify' => ['message' => $message, 'type' => 'danger'],
            ], JSON_UNESCAPED_UNICODE));
        }

        // Empty body — HTMX will show toast via trigger
        echo '';
        exit;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }
}
