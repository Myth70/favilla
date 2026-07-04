<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use RuntimeException;

class Router
{
    private array $routes = [];
    private array $namedRoutes = [];
    private array $groupStack = [];
    private array $patternCache = [];
    private ?string $currentRouteName = null;

    /**
     * Register a GET route.
     */
    public function get(string $uri, array $action): self
    {
        return $this->addRoute('GET', $uri, $action);
    }

    /**
     * Register a POST route.
     */
    public function post(string $uri, array $action): self
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $uri, array $action): self
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $uri, array $action): self
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Name the last registered route.
     */
    public function name(string $name): self
    {
        $idx = array_key_last($this->routes);
        if ($idx !== null) {
            $last = $this->routes[$idx];
            $key = $last['method'] . ':' . $last['uri'];
            $this->namedRoutes[$name] = $key;
            $this->routes[$idx]['name'] = $name;
        }
        return $this;
    }

    /**
     * Define a route group with shared prefix and middleware.
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /**
     * Dispatch the request to the matching route.
     * Returns [controller, method, params, middleware] or throws/sends error response.
     */
    public function dispatch(string $method, string $uri): array
    {
        // Normalize URI
        $uri = '/' . trim($uri, '/');

        // Support PUT/DELETE via POST with _method field
        if ($method === 'POST' && isset($_POST['_method']) && is_string($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }

        // RFC 9110 §9.3.2: HEAD si comporta come GET (PHP/Apache omettono il
        // body automaticamente). Senza questo, ogni probe HEAD riceveva 405.
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $matchedUri = false;

        foreach ($this->routes as $route) {
            $pattern = $this->compilePattern($route['uri']);

            if (preg_match($pattern, $uri, $matches)) {
                $matchedUri = true;

                if ($route['method'] === $method) {
                    $this->currentRouteName = $route['name'] ?? null;

                    // Extract named parameters
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    return [
                        'controller' => $route['action'][0],
                        'method'     => $route['action'][1],
                        'params'     => $params,
                        'middleware' => $route['middleware'] ?? [],
                    ];
                }
            }
        }

        // URI matched but method didn't → 405
        if ($matchedUri) {
            $allowed = [];
            foreach ($this->routes as $route) {
                $pattern = $this->compilePattern($route['uri']);
                if (preg_match($pattern, $uri)) {
                    $allowed[] = $route['method'];
                }
            }
            throw new HttpException(405, ['Allow' => implode(', ', array_unique($allowed))]);
        }

        // No match at all → 404
        throw new HttpException(404);
    }

    /**
     * Generate URL for a named route.
     * Supports both {param} and {param:constraint} placeholders.
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException("Route [{$name}] not defined.");
        }

        $key = $this->namedRoutes[$name];
        // key = METHOD:uri → extract uri part
        $uri = substr($key, strpos($key, ':') + 1);

        // Replace {param} and {param:constraint} placeholders
        $uri = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/',
            function (array $m) use ($params): string {
                return isset($params[$m[1]]) ? (string) $params[$m[1]] : $m[0];
            },
            $uri
        );

        // Detect unresolved placeholders (missing params)
        if (preg_match('/\{[a-zA-Z_][a-zA-Z0-9_]*(?::[^}]+)?\}/', $uri, $unresolved)) {
            throw new RuntimeException(
                "Route [{$name}]: unresolved placeholder '{$unresolved[0]}'. Did you forget to pass params?"
            );
        }

        $basePath = rtrim(config('app.base_path', ''), '/');
        $baseUrl  = rtrim(config('app.url', ''), '/') . $basePath;
        return $baseUrl . $uri;
    }

    /**
     * Get all registered routes (for debugging).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Name of the route matched by the current dispatch (null if none/not yet).
     * Used to attach the precise route to contextual bug reports.
     */
    public function current(): ?string
    {
        return $this->currentRouteName;
    }

    // ------------------------------------------------------------------

    private function addRoute(string $method, string $uri, array $action): self
    {
        $prefix = $this->getCurrentPrefix();
        $middleware = $this->getCurrentMiddleware();

        $fullUri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');

        $this->routes[] = [
            'method'     => $method,
            'uri'        => $fullUri,
            'action'     => $action,
            'middleware'  => $middleware,
            'name'       => null,
        ];

        return $this;
    }

    private function getCurrentPrefix(): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        return $prefix;
    }

    private function getCurrentMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['middleware'])) {
                $mw = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middleware = array_merge($middleware, $mw);
            }
        }
        return $middleware;
    }

    /**
     * Compile a URI pattern into a regex.
     *
     * Supports:
     *   {param}            → (?P<param>[^/]+)   default: any non-slash sequence
     *   {param:constraint} → (?P<param>constraint)  custom regex constraint
     *
     * Results are cached by URI string — compiled once, reused every dispatch.
     */
    private function compilePattern(string $uri): string
    {
        if (!isset($this->patternCache[$uri])) {
            // Extract {param} and {param:constraint} before escaping,
            // replacing them with unique tokens to survive preg_quote.
            $placeholders = [];
            $processed    = preg_replace_callback(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
                function (array $m) use (&$placeholders): string {
                    $name       = $m[1];
                    $constraint = $m[2] ?? '[^/]+';
                    $token      = '__PARAM_' . $name . '__';
                    $placeholders[$token] = '(?P<' . $name . '>' . $constraint . ')';
                    return $token;
                },
                $uri
            );

            // Escape all non-token characters
            $escaped = preg_quote($processed, '#');

            // Restore named capture groups in place of escaped tokens
            foreach ($placeholders as $token => $regex) {
                $escaped = str_replace(preg_quote($token, '#'), $regex, $escaped);
            }

            $this->patternCache[$uri] = '#^' . $escaped . '$#';
        }
        return $this->patternCache[$uri];
    }
}
