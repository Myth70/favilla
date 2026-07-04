<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Helpers\StatoHelper;
use App\Modules\Documenti\Services\WorkflowApprovazioneService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Copre la macchina a stati (TRANSIZIONI), le azioni meta non loggate
 * e il routing delle notifiche per stato risultante.
 */
class DocumentiWorkflowTest extends TestCase
{
    private function transizioni(): array
    {
        return (new ReflectionClass(WorkflowApprovazioneService::class))->getConstant('TRANSIZIONI');
    }

    public function testInviaBozzaVaInviato(): void
    {
        $t = $this->transizioni();
        $this->assertArrayHasKey('invia', $t['bozza']);
        $this->assertSame('inviato', $t['bozza']['invia'][0]);
    }

    public function testRitiraRiportaInBozza(): void
    {
        $t = $this->transizioni();
        $this->assertArrayHasKey('ritira', $t['inviato'], 'inviato deve supportare ritira');
        $this->assertSame('bozza', $t['inviato']['ritira'][0]);
        $this->assertSame('documenti.redazione', $t['inviato']['ritira'][3]);
    }

    public function testArchiviaDaPubblicatoEScaduto(): void
    {
        $t = $this->transizioni();
        $this->assertSame('archiviato', $t['pubblicato']['archivia'][0]);
        $this->assertSame('archiviato', $t['scaduto']['archivia'][0]);
        $this->assertSame('documenti.admin', $t['pubblicato']['archivia'][3]);
    }

    public function testAzioniMetaNonVannoNelLogEnum(): void
    {
        $loggate = (new ReflectionClass(WorkflowApprovazioneService::class))->getConstant('AZIONI_LOGGATE');
        $this->assertContains('invia', $loggate);
        $this->assertNotContains('ritira', $loggate, 'ritira non è un valore ENUM di documenti_approvazioni.azione');
        $this->assertNotContains('archivia', $loggate);
    }

    public function testApprovazioneFinaleNotificaAdminEOwner(): void
    {
        $jobs   = WorkflowApprovazioneService::notifichePerTransizione('approva', 'approvato');
        $slugs  = array_column($jobs, 0);
        $gruppi = array_column($jobs, 1);
        $this->assertContains('documenti.pronto_pubblicazione', $slugs);
        $this->assertContains('admin', $gruppi);
        $this->assertContains('documenti.approvato', $slugs);
        $this->assertContains('owner', $gruppi);
    }

    public function testRestituisciNotificaControllo(): void
    {
        $this->assertSame(
            [['documenti.restituito', 'controllo']],
            WorkflowApprovazioneService::notifichePerTransizione('restituisci', 'in_controllo')
        );
    }

    public function testPrendeInCaricoNotificaOwner(): void
    {
        $this->assertSame(
            [['documenti.preso_in_carico', 'owner']],
            WorkflowApprovazioneService::notifichePerTransizione('prende_in_carico', 'in_controllo')
        );
    }

    public function testInvioNotificaControllo(): void
    {
        $this->assertSame(
            [['documenti.inviato', 'controllo']],
            WorkflowApprovazioneService::notifichePerTransizione('invia', 'inviato')
        );
    }

    public function testPubblicazioneNotificaOwner(): void
    {
        $this->assertSame(
            [['documenti.approvato', 'owner']],
            WorkflowApprovazioneService::notifichePerTransizione('pubblica', 'pubblicato')
        );
    }

    public function testTransizioneSenzaNotificheRestituisceVuoto(): void
    {
        // controllato → in_approvazione (approva) non genera notifiche aggiuntive
        $this->assertSame([], WorkflowApprovazioneService::notifichePerTransizione('approva', 'in_approvazione'));
        // ritira/archivia non notificano
        $this->assertSame([], WorkflowApprovazioneService::notifichePerTransizione('ritira', 'bozza'));
        $this->assertSame([], WorkflowApprovazioneService::notifichePerTransizione('archivia', 'archiviato'));
    }

    public function testResponsabilePerStato(): void
    {
        $this->assertSame('Gruppo Controllo', StatoHelper::responsabile('in_controllo'));
        $this->assertSame('Gruppo Approvazione', StatoHelper::responsabile('in_approvazione'));
        $this->assertNull(StatoHelper::responsabile('bozza'));
    }
}
