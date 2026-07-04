<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared cache for config() helper.
 * Using a public static property allows config_flush() to reset the cache
 * without relying on inaccessible static locals of a global function.
 */
final class ConfigCache
{
    /** @var array<string, array<string, mixed>> */
    public static array $data = [];
}
