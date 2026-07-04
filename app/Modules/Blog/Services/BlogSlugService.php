<?php

declare(strict_types=1);

namespace App\Modules\Blog\Services;

use App\Modules\Blog\Repositories\BlogArticleRepository;
use App\Modules\Blog\Repositories\BlogCategoryRepository;
use App\Modules\Blog\Repositories\BlogTagRepository;

class BlogSlugService
{
    private const RESERVED_SLUGS = [
        'my', 'create', 'category', 'tag', 'search', 'admin', 'feed', 'rss',
        'saved', 'pdf', 'like', 'bookmark', 'comments', 'store',
    ];

    /**
     * Generate a unique article slug from a title.
     */
    public static function articleSlug(string $title, ?int $excludeId = null): string
    {
        $slug = self::toSlug($title);
        $repo = app(BlogArticleRepository::class);

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            $slug .= '-articolo';
        }

        $base = $slug;
        $i = 1;
        while ($repo->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Generate a unique category slug.
     */
    public static function categorySlug(string $name, ?int $excludeId = null): string
    {
        $slug = self::toSlug($name);
        $repo = app(BlogCategoryRepository::class);

        $base = $slug;
        $i = 1;
        while ($repo->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Generate a unique tag slug.
     */
    public static function tagSlug(string $name, ?int $excludeId = null): string
    {
        $slug = self::toSlug($name);
        $repo = app(BlogTagRepository::class);

        $base = $slug;
        $i = 1;
        while ($repo->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /**
     * Convert a string to a URL-safe slug.
     */
    private static function toSlug(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Transliterate common accented chars
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
