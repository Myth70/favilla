<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * Un singolo gruppo di controlli di salute del sistema.
 *
 * Ogni implementazione è una classe piccola e focalizzata (PHP, Database,
 * Sicurezza, ...), testabile in isolamento. Sostituisce i metodi privati
 * checkXxx() della vecchia god class HealthCheckService.
 */
interface HealthCheck
{
    /** Chiave stabile del gruppo (es. 'php', 'database'); usata come indice e nello storico JSON. */
    public function key(): string;

    /** Etichetta mostrata in UI (es. 'PHP'). */
    public function label(): string;

    /** Descrizione breve del gruppo. */
    public function description(): string;

    /**
     * Profondità del check:
     *  - 'fast' → eseguito sul dashboard ad ogni refresh (nessuna I/O di rete/shell);
     *  - 'deep' → eseguito solo da CLI o "scansione approfondita" (DNS, fetch remoto, shell).
     */
    public function depth(): string;

    /**
     * Esegue i controlli e restituisce il gruppo nel formato consumato dalle view
     * e salvato nello storico.
     *
     * @return array{label:string,description:string,checks:array<int,array{name:string,status:string,detail:string}>}
     */
    public function run(): array;
}
