<?php

declare(strict_types=1);

namespace App\Contracts;

interface MiddlewareInterface
{
    /**
     * Handle the request and pass to the next layer.
     */
    public function handle(callable $next): void;
}
