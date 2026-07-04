<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared cache for the Translator's lang-file lookups.
 *
 * Mirrors {@see ConfigCache}: a public static property so lang_flush() can
 * reset the cache between tests without touching inaccessible static locals.
 *
 *  - $data    keyed "locale:namespace" -> decoded array from resources/lang.
 *  - $missing keyed "locale:full.key"  -> true, populated on every miss so the
 *             `php favilla lang:check` command can surface untranslated keys
 *             encountered at runtime (dev only).
 */
final class LangCache
{
    /** @var array<string, array<string, mixed>> */
    public static array $data = [];

    /** @var array<string, true> */
    public static array $missing = [];
}
