<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for modules that provide global search results.
 *
 * Implement this interface and register the FQCN in module.json:
 *   "search_provider": "App\\Modules\\MyModule\\Providers\\MySearchProvider"
 *
 * For core modules without module.json, place the provider in
 *   app/Modules/{Module}/Providers/*SearchProvider.php
 * and it will be auto-discovered.
 */
interface SearchableModule
{
    /**
     * Execute a search and return normalised results.
     *
     * Each result must be an associative array:
     *   [
     *       'title'    => string,   // display title
     *       'subtitle' => string,   // short description / excerpt
     *       'url'      => string,   // direct link
     *       'icon'     => string,   // FA icon class (optional override)
     *       'badge'    => ?string,  // optional badge text ("draft", "archived")
     *   ]
     *
     * @param  string $query   Search terms
     * @param  int    $userId  Authenticated user ID (for permission filtering)
     * @param  int    $limit   Max results to return
     * @return array<int,array>
     */
    public function search(string $query, int $userId, int $limit = 5): array;

    /** Human-readable module label shown in the UI ("Report", "File", …). */
    public function getSearchLabel(): string;

    /** Font Awesome icon class ("fa-newspaper", "fa-file", …). */
    public function getSearchIcon(): string;
}
