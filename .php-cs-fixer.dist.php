<?php

/**
 * PHP-CS-Fixer — configurazione di base (non distruttiva).
 *
 * Ruleset @PSR12 + alcune regole sicure (non "risky": nessuna modifica che possa
 * cambiare il comportamento a runtime). Le view sono escluse perché sono template
 * con variabili extract()-ate e HTML inline: riformattarle creerebbe solo rumore.
 *
 * Uso:  composer cs        (dry-run, mostra il diff)
 *       composer cs:fix    (applica)
 */

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->exclude('Modules/_Template/stubs')
    ->exclude('Views')
    ->notPath('#/Views/#')
    ->notName('*.stub');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        'blank_line_after_namespace' => true,
        'single_blank_line_at_eof' => true,
    ])
    ->setFinder($finder);
