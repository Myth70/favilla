<?php

declare(strict_types=1);

namespace App\Core;

class Middleware
{
    /**
     * Run a stack of middleware, then execute the final action.
     *
     * Each middleware must implement:
     *   handle(callable $next): void
     *
     * @param array    $middlewareClasses  Ordered list of middleware class names
     * @param callable $final             The controller action to run at the end
     */
    public static function run(array $middlewareClasses, callable $final): void
    {
        $pipeline = self::buildPipeline($middlewareClasses, $final);
        $pipeline();
    }

    /**
     * Build a nested callable pipeline (onion pattern).
     */
    private static function buildPipeline(array $middlewareClasses, callable $final): callable
    {
        // Start from the innermost layer (the controller action)
        $next = $final;

        // Wrap each middleware around the next layer, from inside out
        foreach (array_reverse($middlewareClasses) as $class) {
            $middleware = is_object($class) ? $class : app($class);
            $next = function () use ($middleware, $next) {
                $middleware->handle($next);
            };
        }

        return $next;
    }
}
