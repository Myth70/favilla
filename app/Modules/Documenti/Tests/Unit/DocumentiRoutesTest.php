<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\ApprovazioniController;
use PHPUnit\Framework\TestCase;

/**
 * Verifica B1: tutte le named route del workflow approvazione devono mappare a
 * un metodo PHP realmente esistente sul controller.
 *
 * Test analitico (non launcha il router): legge routes.php come PHP e verifica
 * che il file dichiari ogni nome richiesto e che il controller esponga il metodo
 * corrispondente.
 */
class DocumentiRoutesTest extends TestCase
{
    private const REQUIRED_NAMES = [
        'documenti.approvazioni.invia',
        'documenti.approvazioni.prende_in_carico',
        'documenti.approvazioni.approva',
        'documenti.approvazioni.rifiuta',
        'documenti.approvazioni.restituisci',
        'documenti.approvazioni.pubblica',
        'documenti.approvazioni.riprendi',
    ];

    private const METHOD_BY_NAME = [
        'documenti.approvazioni.invia'             => 'invia',
        'documenti.approvazioni.prende_in_carico'  => 'prendeInCarico',
        'documenti.approvazioni.approva'           => 'approva',
        'documenti.approvazioni.rifiuta'           => 'rifiuta',
        'documenti.approvazioni.restituisci'       => 'restituisci',
        'documenti.approvazioni.pubblica'          => 'pubblica',
        'documenti.approvazioni.riprendi'          => 'riprendi',
    ];

    public function testRoutesFileExists(): void
    {
        $this->assertFileExists(
            BASE_PATH . '/app/Modules/Documenti/routes.php',
            'routes.php deve esistere'
        );
    }

    public function testAllWorkflowRouteNamesAreDeclared(): void
    {
        $contents = (string) file_get_contents(BASE_PATH . '/app/Modules/Documenti/routes.php');
        foreach (self::REQUIRED_NAMES as $name) {
            $needle = "'{$name}'";
            $this->assertStringContainsString(
                $needle,
                $contents,
                "routes.php deve dichiarare la named route {$name}"
            );
        }
    }

    public function testRoutesPointToExistingControllerMethods(): void
    {
        foreach (self::METHOD_BY_NAME as $routeName => $method) {
            $this->assertTrue(
                method_exists(ApprovazioniController::class, $method),
                "ApprovazioniController deve esporre il metodo {$method} (richiesto da {$routeName})"
            );
        }
    }

    public function testNoOrphanRouteToMissingMethod(): void
    {
        // Bug originale: routes.php aveva 'controlla' che puntava a un metodo non esistente.
        // Verifica che NESSUNA delle route note punti a 'controlla'.
        $contents = (string) file_get_contents(BASE_PATH . '/app/Modules/Documenti/routes.php');
        $this->assertStringNotContainsString(
            "[ApprovazioniController::class, 'controlla']",
            $contents,
            "routes.php non deve riferire il metodo 'controlla' di ApprovazioniController (non esiste)"
        );
        $this->assertFalse(
            method_exists(ApprovazioniController::class, 'controlla'),
            'ApprovazioniController::controlla() non dovrebbe esistere — usare prendeInCarico()'
        );
    }
}
