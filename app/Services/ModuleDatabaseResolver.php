<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ModuleLoader;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Centralized resolver/provisioner for per-module database connections.
 *
 * Source of truth: table `module_databases` in the main DB.
 * Lazy: opens module PDO only on first request, then caches per-request.
 *
 * Resolution order in pdoFor():
 *   1. module declares database='shared' (or nothing)        -> main PDO
 *   2. mapping in module_databases (status ready|manual)     -> dedicated PDO
 *   3. legacy fallback: module.json + database_env_prefix    -> ModulePdoFactory
 *   4. otherwise                                             -> RuntimeException
 *
 * No silent fallback to the main DB for `independent` modules.
 */
class ModuleDatabaseResolver
{
    private const NAME_REGEX     = '/^[a-z][a-z0-9_]{0,63}$/';
    private const RESERVED_NAMES = ['mysql', 'information_schema', 'performance_schema', 'sys'];

    /** @var array<string, PDO> */
    private array $pdoCache = [];

    /** @var array<string, ?array> */
    private array $mappingCache = [];

    public function __construct(
        private readonly PDO $mainPdo,
        private readonly ModuleLoader $loader,
        private readonly array $baselineConfig
    ) {
    }

    /**
     * Resolve PDO for a module. Always returns a usable connection or throws.
     */
    public function pdoFor(string $moduleName): PDO
    {
        if (isset($this->pdoCache[$moduleName])) {
            return $this->pdoCache[$moduleName];
        }

        $meta = $this->loader->readModuleJson($moduleName) ?? [];
        $declaredMode = $meta['database'] ?? 'shared';

        if ($declaredMode !== 'independent') {
            return $this->pdoCache[$moduleName] = $this->mainPdo;
        }

        // 1. Internal mapping
        $mapping = $this->getMapping($moduleName);
        if ($mapping
            && $mapping['mode'] === 'independent'
            && in_array($mapping['provisioning_status'], ['ready', 'manual'], true)
            && !empty($mapping['database_name'])
        ) {
            return $this->pdoCache[$moduleName] = $this->openPdo(
                $mapping['database_name'],
                $mapping['host'] ?? null,
                $mapping['port'] !== null ? (int) $mapping['port'] : null
            );
        }

        // 2. Legacy fallback: env prefix
        $prefix = $meta['database_env_prefix'] ?? null;
        if ($prefix !== null && ModulePdoFactory::hasConfig($prefix)) {
            return $this->pdoCache[$moduleName] = ModulePdoFactory::get($prefix);
        }

        throw new RuntimeException(sprintf(
            "Modulo '%s' dichiara database='independent' ma non ha mapping in module_databases ne' env prefix valido. Eseguire provisioning prima dell'uso.",
            $moduleName
        ));
    }

    /**
     * Return mapping row for a module, or null if not registered.
     *
     * @return array{module_name:string,mode:string,database_name:?string,host:?string,port:?int,provisioning_status:string,last_error:?string,last_error_at:?string,provisioned_at:?string,created_by:?int,created_at:string,updated_at:string}|null
     */
    public function getMapping(string $moduleName): ?array
    {
        if (array_key_exists($moduleName, $this->mappingCache)) {
            return $this->mappingCache[$moduleName];
        }

        try {
            $stmt = $this->mainPdo->prepare(
                'SELECT module_name, mode, database_name, host, port, provisioning_status,
                        last_error, last_error_at, provisioned_at, created_by, created_at, updated_at
                 FROM module_databases WHERE module_name = ? LIMIT 1'
            );
            $stmt->execute([$moduleName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table may not exist yet (pre-migration). Treat as no mapping.
            $row = false;
        }

        return $this->mappingCache[$moduleName] = $row ?: null;
    }

    /**
     * Return all active independent module databases (status ready|manual).
     *
     * Source of truth for "which modules own a dedicated DB". Used by the
     * Backup module to dump every database, not just the main one. Degrades
     * to an empty array if the mapping table does not exist yet.
     *
     * @return array<array{module_name:string,database_name:string,host:?string,port:?int,provisioning_status:string}>
     */
    public function allActiveIndependent(): array
    {
        try {
            $stmt = $this->mainPdo->query(
                "SELECT module_name, database_name, host, port, provisioning_status
                 FROM module_databases
                 WHERE mode = 'independent'
                   AND provisioning_status IN ('ready', 'manual')
                   AND database_name IS NOT NULL AND database_name <> ''
                 ORDER BY module_name"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'module_name'         => (string) $row['module_name'],
                'database_name'       => (string) $row['database_name'],
                'host'                => $row['host'] !== null ? (string) $row['host'] : null,
                'port'                => $row['port'] !== null ? (int) $row['port'] : null,
                'provisioning_status' => (string) $row['provisioning_status'],
            ];
        }, $rows ?: []);
    }

    /**
     * Suggested DB name for a module: favilla_mod_<snake_case>.
     */
    public function suggestName(string $moduleName): string
    {
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $moduleName));
        $snake = preg_replace('/[^a-z0-9_]/', '', $snake);
        return 'favilla_mod_' . $snake;
    }

    /**
     * Validate a database name against regex + blacklist.
     *
     * @throws InvalidArgumentException
     */
    public function validateName(string $name): void
    {
        if (!preg_match(self::NAME_REGEX, $name)) {
            throw new InvalidArgumentException(
                "Nome database non valido: '{$name}'. Deve essere [a-z][a-z0-9_]{0,63}."
            );
        }

        $reserved = array_map('strtolower', self::RESERVED_NAMES);
        $reserved[] = strtolower((string) ($this->baselineConfig['name'] ?? ''));

        if (in_array(strtolower($name), $reserved, true)) {
            throw new InvalidArgumentException(
                "Nome database '{$name}' e' riservato o coincide con il DB principale."
            );
        }
    }

    /**
     * Provision (create) a database for a module and persist mapping as 'ready'.
     * Idempotent: CREATE DATABASE IF NOT EXISTS.
     *
     * @throws InvalidArgumentException on invalid name
     * @throws RuntimeException on DDL/permission failure
     */
    public function provision(
        string $moduleName,
        string $databaseName,
        string $mode = 'independent',
        ?int $userId = null
    ): void {
        $this->validateName($databaseName);

        try {
            $this->mainPdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '', $databaseName)
            ));
        } catch (PDOException $e) {
            $this->upsertMapping($moduleName, $mode, $databaseName, 'error', $e->getMessage(), $userId);
            throw new RuntimeException(
                "CREATE DATABASE per '{$databaseName}' fallito: {$e->getMessage()}. "
                . "L'utente DB principale ha il privilegio CREATE? In hosting condivisi usare provisioning manuale.",
                0,
                $e
            );
        }

        $this->upsertMapping($moduleName, $mode, $databaseName, 'ready', null, $userId, true);
        $this->invalidate($moduleName);
    }

    /**
     * Register a manually-created database (admin created it themselves).
     * No DDL is executed.
     */
    public function markManual(string $moduleName, string $databaseName, ?int $userId = null): void
    {
        $this->validateName($databaseName);
        $this->upsertMapping($moduleName, 'independent', $databaseName, 'manual', null, $userId, true);
        $this->invalidate($moduleName);
    }

    /**
     * Mark a module as removed (uninstalled). Mapping row is preserved for audit.
     */
    public function markRemoved(string $moduleName): void
    {
        $stmt = $this->mainPdo->prepare(
            'UPDATE module_databases SET provisioning_status = ?, updated_at = CURRENT_TIMESTAMP WHERE module_name = ?'
        );
        $stmt->execute(['removed', $moduleName]);
        $this->invalidate($moduleName);
    }

    /**
     * Record a connection/provisioning error for a module.
     */
    public function recordError(string $moduleName, string $message): void
    {
        try {
            $stmt = $this->mainPdo->prepare(
                'UPDATE module_databases
                 SET provisioning_status = ?, last_error = ?, last_error_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE module_name = ?'
            );
            $stmt->execute(['error', $message, $moduleName]);
        } catch (PDOException $e) {
            // best-effort
        }
        $this->invalidate($moduleName);
    }

    /**
     * Test if module DB is reachable. Never throws.
     */
    public function isUsable(string $moduleName): bool
    {
        try {
            $pdo = $this->pdoFor($moduleName);
            $pdo->query('SELECT 1')->fetchColumn();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Drop the dedicated database for a module.
     * Destructive; caller must confirm.
     */
    public function dropDatabase(string $moduleName): void
    {
        $mapping = $this->getMapping($moduleName);
        if (!$mapping || empty($mapping['database_name'])) {
            return;
        }
        $name = $mapping['database_name'];
        $this->validateName($name);
        $this->mainPdo->exec(sprintf(
            'DROP DATABASE IF EXISTS `%s`',
            str_replace('`', '', $name)
        ));
        unset($this->pdoCache[$moduleName]);
    }

    /**
     * Forget cached mapping/PDO for a module (call after external changes).
     */
    public function invalidate(string $moduleName): void
    {
        unset($this->mappingCache[$moduleName], $this->pdoCache[$moduleName]);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Open a PDO for a dedicated DB using baseline credentials.
     */
    private function openPdo(string $databaseName, ?string $host, ?int $port): PDO
    {
        $effectiveHost = $host ?? ($this->baselineConfig['host'] ?? 'localhost');
        $effectivePort = $port ?? (int) ($this->baselineConfig['port'] ?? 3306);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $effectiveHost,
            $effectivePort,
            $databaseName,
            $this->baselineConfig['charset'] ?? 'utf8mb4'
        );

        return new PDO(
            $dsn,
            $this->baselineConfig['user'] ?? 'root',
            $this->baselineConfig['pass'] ?? '',
            $this->baselineConfig['options'] ?? [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /**
     * Insert or update a mapping row, optionally clearing last_error.
     */
    private function upsertMapping(
        string $moduleName,
        string $mode,
        string $databaseName,
        string $status,
        ?string $lastError,
        ?int $userId,
        bool $setProvisionedAt = false
    ): void {
        $sql = 'INSERT INTO module_databases
                    (module_name, mode, database_name, provisioning_status, last_error, last_error_at, provisioned_at, created_by)
                VALUES
                    (:module_name, :mode, :database_name, :status, :last_error, :last_error_at, :provisioned_at, :created_by)
                ON DUPLICATE KEY UPDATE
                    mode = VALUES(mode),
                    database_name = VALUES(database_name),
                    provisioning_status = VALUES(provisioning_status),
                    last_error = VALUES(last_error),
                    last_error_at = VALUES(last_error_at),
                    provisioned_at = COALESCE(VALUES(provisioned_at), provisioned_at),
                    updated_at = CURRENT_TIMESTAMP';

        $stmt = $this->mainPdo->prepare($sql);
        $stmt->execute([
            ':module_name'    => $moduleName,
            ':mode'           => $mode,
            ':database_name'  => $databaseName,
            ':status'         => $status,
            ':last_error'     => $lastError,
            ':last_error_at'  => $lastError !== null ? date('Y-m-d H:i:s') : null,
            ':provisioned_at' => $setProvisionedAt ? date('Y-m-d H:i:s') : null,
            ':created_by'     => $userId,
        ]);
    }
}
