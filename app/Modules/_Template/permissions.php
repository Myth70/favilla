<?php

/**
 * Permessi dichiarati da questo modulo.
 *
 * ISTRUZIONI:
 * 1. Rinomina gli slug con il nome del tuo modulo (es. clienti.view, clienti.create)
 * 2. Importa tramite Admin → Moduli → "Importa permessi" per inserirli nel DB
 * 3. Assegna i permessi ai ruoli tramite Admin → Ruoli → Permessi
 *
 * CONVENZIONE SLUG: nomemodulo.azione
 *   - nomemodulo.view   → accesso in sola lettura (lista + dettaglio)
 *   - nomemodulo.create → creazione nuovi record
 *   - nomemodulo.edit   → modifica record esistenti
 *   - nomemodulo.delete → eliminazione record
 *
 * NON modificare gli slug dopo l'importazione: i ruoli li referenziano per slug.
 * Se devi rinominare uno slug, prima rimuovilo dal DB e poi reimporta.
 */
return [
    ['slug' => 'example.view',   'name' => 'Visualizza Example', 'module' => 'Example'],
    ['slug' => 'example.create', 'name' => 'Crea Example',       'module' => 'Example'],
    ['slug' => 'example.edit',   'name' => 'Modifica Example',   'module' => 'Example'],
    ['slug' => 'example.delete', 'name' => 'Elimina Example',    'module' => 'Example'],
];
