<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for modules that expose contact data importable into the Contatti module.
 *
 * Implement this interface and register the FQCN in module.json:
 *   "contact_source_provider": "App\\Modules\\MyModule\\Providers\\MyContactSourceProvider"
 *
 * For core modules without module.json, place the provider in
 *   app/Modules/{Module}/Providers/*ContactSourceProvider.php
 * and it will be auto-discovered.
 *
 * The provider is responsible for mapping its own domain fields onto the
 * Contatti canonical schema. Fields that don't map 1:1 should go into
 * `tags` (CSV) or `note`.
 */
interface ContactSourceProvider
{
    /**
     * Return all contact sources this module exposes.
     *
     * Each source is an associative array:
     * [
     *     'key'         => 'fornitori',                 // unique within the module
     *     'label'       => 'Fornitori',                 // human-readable Italian label
     *     'icon'        => 'fa-truck',                  // Font Awesome icon class
     *     'permission'  => 'fornitori.read',            // required permission slug (null = any authenticated)
     *     'description' => 'Anagrafica fornitori attivi', // optional, shown on the source card
     * ]
     *
     * @return array<int, array>
     */
    public function getContactSources(): array;

    /**
     * List records of a source, paginated and optionally filtered.
     *
     * Each row in 'rows' is a payload pre-mapped to the Contatti schema:
     *   - source_id    (required, int|string) primary key in the source module
     *   - nome         (required, string)
     *   - cognome, azienda, ruolo                                    (optional)
     *   - email, telefono, telefono_alt, sito_web                    (optional)
     *   - indirizzo, latitude, longitude                             (optional)
     *   - linkedin, instagram, twitter, facebook, whatsapp, telegram (optional)
     *   - tags (CSV string), note                                    (optional)
     *   - preview (string)  compact one-line preview shown in the browse table
     *
     * Filters are free-form — the provider declares which keys it understands
     * (typically at least 'q' for free-text search).
     *
     * @return array{rows: array<int,array>, total: int}
     */
    public function listContacts(string $sourceKey, array $filters = [], int $page = 1, int $perPage = 25): array;

    /**
     * Fetch a single record fully populated for form pre-fill.
     *
     * Same payload shape as listContacts() rows. Return null if not found
     * or the source key is not supported.
     */
    public function getContact(string $sourceKey, int $sourceId): ?array;

    /** Module display name shown in the import UI. */
    public function getContactModuleName(): string;

    /** Font Awesome icon class for the module. */
    public function getContactModuleIcon(): string;
}
