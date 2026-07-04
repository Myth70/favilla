<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Router;

/**
 * Router double for controller tests. The real Router::url() throws when a named
 * route is not registered; controllers call route('module.action') constantly.
 * This fake returns a deterministic, never-throwing URL for ANY name, so tests
 * can assert on redirect/link targets without wiring the full route table.
 */
final class FakeRouter extends Router
{
    /**
     * @param array<string,scalar> $params
     */
    public function url(string $name, array $params = []): string
    {
        $query = $params !== [] ? '?' . http_build_query($params) : '';
        return '/' . $name . $query;
    }
}
