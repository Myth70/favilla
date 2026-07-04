<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for modules that expose exportable data sources.
 *
 * Implement this interface and register the FQCN in module.json:
 *   "export_provider": "App\\Modules\\MyModule\\Providers\\MyExportProvider"
 *
 * For core modules without module.json, place the provider in
 *   app/Modules/{Module}/Providers/*ExportProvider.php
 * and it will be auto-discovered.
 */
interface ExportableModule
{
    /**
     * Return all data sources this module exposes for export.
     *
     * Each source is an associative array:
     * [
     *     'key'        => 'users',               // unique within module
     *     'label'      => 'Utenti',              // human-readable Italian label
     *     'icon'       => 'fa-users',            // Font Awesome icon
     *     'permission' => 'admin.users.view',    // required permission slug (null = any authenticated)
     *     'fields'     => [
     *         [
     *             'name'        => 'name',       // DB column or alias
     *             'label'       => 'Nome',       // Italian display label
     *             'type'        => 'string',     // string|integer|decimal|date|datetime|boolean|enum
     *             'sortable'    => true,
     *             'filterable'  => true,
     *             'format'      => null,         // currency|percentage|bytes|null
     *             'enum_values' => null,         // for enum type: ['draft','active','archived']
     *         ],
     *     ],
     * ]
     *
     * @return array<int, array>
     */
    public function getDataSources(): array;

    /**
     * Fetch data for a specific source.
     *
     * @param  string $sourceKey  The source key from getDataSources()
     * @param  array  $filters    Applied filters {field => value}
     * @param  string $sortBy     Sort column (must be in declared sortable fields)
     * @param  string $sortDir    'ASC' or 'DESC'
     * @param  int    $limit      Max rows (safety cap)
     * @return array<int, array>  Flat array of associative rows
     */
    public function getExportData(
        string $sourceKey,
        array  $filters = [],
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        int    $limit = 10000
    ): array;

    /** Module display name shown in export UI. */
    public function getExportModuleName(): string;

    /** Font Awesome icon class for the module. */
    public function getExportModuleIcon(): string;

    /**
     * Fetch a single record by primary key for document (PDF) generation.
     *
     * Returns a flat associative array of field => value pairs whose keys
     * match the placeholder names placed in the designer layout.
     * Return null if the source is not supported or the record does not exist.
     *
     * @param  string $sourceKey  The source key from getDataSources()
     * @param  int    $recordId   Primary key of the record to fetch
     * @return array|null
     */
    public function getSingleRecord(string $sourceKey, int $recordId): ?array;
}
