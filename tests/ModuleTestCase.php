<?php

namespace Tests;

use App\Core\Container;
use App\Core\ModuleLoader;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Classe base per i test dei moduli.
 *
 * Fornisce:
 * - Database SQLite in-memory (zero dipendenze da MariaDB)
 * - Funzione NOW() compatibile con le query MySQL/MariaDB
 * - Container DI configurato con il PDO di test
 * - Helper per schema e inserimento dati
 *
 * Utilizzo:
 *   class MioRepositoryTest extends ModuleTestCase
 *   {
 *       protected function setUp(): void
 *       {
 *           parent::setUp();
 *           $this->migrate("CREATE TABLE mia_tabella (id INTEGER PRIMARY KEY, ...)");
 *       }
 *   }
 */
abstract class ModuleTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite in-memory — veloce, zero setup esterno
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // NOW() non esiste in SQLite — la definiamo come user-defined function
        $this->pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'), 0);

        // Registra il PDO nel Container così app(PDO::class) funziona nei Repository
        $container = new Container();
        Container::setInstance($container);
        $container->instance(PDO::class, $this->pdo);

        // Molti service usano isModuleEnabled() -> ModuleLoader::getModules().
        // Registriamo sempre un loader configurato per evitare dipendenze implicite tra test.
        $moduleLoader = new ModuleLoader(BASE_PATH);
        $moduleLoader->loadConfig();
        $container->instance(ModuleLoader::class, $moduleLoader);

        $_ENV['APP_KEY'] = 'test-key-for-unit-tests-only-32bytes00';
        $_SESSION        = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_SESSION = [];
        $_POST    = [];
    }

    /**
     * Esegue uno statement SQL DDL per creare lo schema di test.
     * Accetta sia singole istruzioni che blocchi multi-statement separati da ";".
     */
    protected function migrate(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    /**
     * Inserisce una riga in una tabella e restituisce l'ID generato.
     *
     * @param  string  $table  Nome tabella
     * @param  array   $data   Colonna => valore
     */
    protected function insertRow(string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Schema minimo per la tabella users (usata nei LEFT JOIN dei Repository).
     * Da chiamare in setUp() dei test che coinvolgono repository con JOIN sugli utenti.
     */
    protected function createUsersTable(): void
    {
        $this->migrate('
            CREATE TABLE IF NOT EXISTS users (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                avatar_path TEXT    DEFAULT NULL
            )
        ');
    }
}
