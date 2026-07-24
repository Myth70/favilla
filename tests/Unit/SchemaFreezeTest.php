<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Schema freeze invariant (STABILITY.md).
 *
 * `database/schema.sql` e' la fonte canonica dello schema ed e' congelata dalla
 * 2.4.0: la superficie dati non cresce piu'. Questo test non impedisce di
 * cambiarla — impedisce di cambiarla *per sbaglio*. Chi la modifica deve
 * aggiornare il fingerprint qui sotto, e quel gesto e' il punto in cui la
 * decisione diventa esplicita e finisce nel diff di una review.
 *
 * Vive in tests/Unit (suite Core) e non tocca il database: gira sempre, anche
 * senza MariaDB, quindi il vincolo e' applicato dal CI a ogni push.
 */
class SchemaFreezeTest extends TestCase
{
    /**
     * SHA-256 del contenuto normalizzato di database/schema.sql.
     * Ultimo aggiornamento: 2.4.0 (congelamento).
     */
    private const SCHEMA_FINGERPRINT = '286268cf9bb0fadc453e854f8fc01022c2a6ff0aa559d05bfb038be8190834c1';

    /** Numero di tabelle nello schema congelato. */
    private const FROZEN_TABLE_COUNT = 105;

    private const UPDATE_HINT = <<<'TXT'

        Se la modifica e' voluta:
          1. aggiorna la costante in tests/Unit/SchemaFreezeTest.php
          2. motivala nel CHANGELOG sotto la voce della release
          3. verifica che STABILITY.md copra ancora quello che dichiara

        Se non lo e', lo schema e' congelato: la modifica va annullata.
        TXT;

    private function schemaPath(): string
    {
        return dirname(__DIR__, 2) . '/database/schema.sql';
    }

    /**
     * Normalizza cio' che non e' semanticamente rilevante — fine riga e spazi
     * finali — cosi' il fingerprint non cambia solo perche' il file e' passato
     * per un editor con impostazioni diverse.
     */
    private function normalizedSchema(): string
    {
        $raw = file_get_contents($this->schemaPath());
        self::assertNotFalse($raw, 'database/schema.sql non leggibile.');

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $normalized = implode("\n", array_map('rtrim', explode("\n", $normalized)));

        return rtrim($normalized, "\n") . "\n";
    }

    public function testCanonicalSchemaFileExists(): void
    {
        $this->assertFileExists(
            $this->schemaPath(),
            'database/schema.sql e la fonte canonica dello schema: non puo mancare.'
        );
    }

    public function testFrozenSchemaHasNotChanged(): void
    {
        $actual = hash('sha256', $this->normalizedSchema());

        $this->assertSame(
            self::SCHEMA_FINGERPRINT,
            $actual,
            "database/schema.sql e' cambiato rispetto allo schema congelato nella 2.4.0."
            . self::UPDATE_HINT
        );
    }

    /**
     * Il fingerprint dice *che* qualcosa e' cambiato, non *cosa*. Il conteggio
     * tabelle separa il caso benigno (una colonna ritoccata) da quello che il
     * congelamento vuole davvero fermare: la superficie dati che si allarga.
     */
    public function testFrozenSchemaHasNotGrownNewTables(): void
    {
        $tables = preg_match_all('/^\s*CREATE TABLE/mi', $this->normalizedSchema());

        $this->assertSame(
            self::FROZEN_TABLE_COUNT,
            $tables,
            'Lo schema congelato ha ' . self::FROZEN_TABLE_COUNT . " tabelle, ne sono state contate {$tables}."
            . " Aggiungere tabelle e' esattamente cio' che il congelamento esclude."
            . self::UPDATE_HINT
        );
    }
}
