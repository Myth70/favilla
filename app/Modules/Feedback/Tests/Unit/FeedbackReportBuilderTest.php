<?php

namespace App\Modules\Feedback\Tests\Unit;

use App\Modules\Feedback\Services\FeedbackReportBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test della logica pura che costruisce il report "Copia per LLM".
 * Nessuna dipendenza da DB/HTTP.
 */
class FeedbackReportBuilderTest extends TestCase
{
    private function sampleRow(): array
    {
        $contesto = [
            'client' => [
                'url'        => 'http://localhost/favilla/public/contacts/5/edit',
                'path'       => '/favilla/public/contacts/5/edit',
                'user_agent' => 'Mozilla/5.0 Test',
                'language'   => 'it-IT',
                'theme'      => ['theme' => 'dark', 'accent' => '#3b82f6'],
                'errors'     => [
                    ['type' => 'js', 'message' => 'x is not defined', 'source' => 'app.js', 'line' => 42, 'ts' => '2026-05-31T10:00:00Z'],
                    ['type' => 'htmx', 'verb' => 'post', 'path' => '/contacts/5', 'status' => 500, 'ts' => '2026-05-31T10:00:01Z'],
                ],
                'breadcrumb' => [
                    ['kind' => 'nav', 'path' => '/contacts', 'ts' => '2026-05-31T09:59:00Z'],
                    ['kind' => 'click', 'target' => 'button#save', 'ts' => '2026-05-31T09:59:30Z'],
                    ['kind' => 'htmx', 'verb' => 'post', 'path' => '/contacts/5', 'status' => 500, 'ts' => '2026-05-31T10:00:01Z'],
                ],
            ],
            'server' => [
                'php_version' => '8.2.12',
                'ip'          => '127.0.0.1',
                'modulo'      => 'Contatti',
                'user'        => ['id' => 7, 'name' => 'Mario Rossi', 'roles' => ['admin', 'user']],
            ],
        ];

        return [
            'id'             => 12,
            'ref_code'       => 'SG-ABC123',
            'tipo'           => 'bug',
            'severita'       => 'alta',
            'stato'          => 'nuova',
            'titolo'         => 'Errore salvataggio contatto',
            'descrizione'    => 'Quando salvo il contatto va in errore.',
            'passi'          => "1) Apro contatto\n2) Salvo",
            'modulo'         => 'Contatti',
            'pagina_url'     => 'http://localhost/favilla/public/contacts/5/edit',
            'route_name'     => null,
            'app_version'    => '2.0.0',
            'user_agent'     => 'Mozilla/5.0 Test',
            'viewport'       => '1920x1080@2',
            'creatore_nome'  => 'Mario Rossi',
            'created_at'     => '2026-05-31 10:00:05',
            'contesto_json'  => json_encode($contesto),
            'errori_console_json' => json_encode($contesto['client']['errors']),
        ];
    }

    public function testToMarkdownContainsKeySections(): void
    {
        $md = (new FeedbackReportBuilder())->toMarkdown($this->sampleRow());

        $this->assertStringContainsString('# Segnalazione SG-ABC123', $md);
        $this->assertStringContainsString('## Descrizione utente', $md);
        $this->assertStringContainsString('Quando salvo il contatto va in errore.', $md);
        $this->assertStringContainsString('## Passi per riprodurre', $md);
        $this->assertStringContainsString('## Ambiente', $md);
        $this->assertStringContainsString('PHP', $md);
        $this->assertStringContainsString('## Errori catturati', $md);
        $this->assertStringContainsString('x is not defined', $md);
        $this->assertStringContainsString('[HTMX] POST /contacts/5 → HTTP 500', $md);
        $this->assertStringContainsString('## Sequenza azioni', $md);
        $this->assertStringContainsString('## Contesto completo (JSON)', $md);
        $this->assertStringContainsString('```json', $md);
    }

    public function testToMarkdownEscapesTableCells(): void
    {
        $row = $this->sampleRow();
        // L'autore è reso in una cella della tabella: la pipe va escapata per non romperla.
        $row['creatore_nome'] = 'Rossi | Mario';
        $md = (new FeedbackReportBuilder())->toMarkdown($row);

        $this->assertStringContainsString('Rossi \\| Mario', $md);
    }

    public function testToMarkdownHandlesMissingContext(): void
    {
        $row = [
            'id' => 1,
            'ref_code' => 'SG-EMPTY0',
            'tipo' => 'domanda',
            'severita' => 'bassa',
            'stato' => 'nuova',
            'titolo' => 'Solo testo',
            'descrizione' => 'Nessun contesto allegato',
            'contesto_json' => null,
            'errori_console_json' => null,
        ];

        $md = (new FeedbackReportBuilder())->toMarkdown($row);
        $this->assertStringContainsString('SG-EMPTY0', $md);
        $this->assertStringContainsString('Nessun contesto allegato', $md);
        // Nessuna sezione errori quando non ci sono errori.
        $this->assertStringNotContainsString('## Errori catturati', $md);
    }

    public function testToArrayDecodesPayloads(): void
    {
        $out = (new FeedbackReportBuilder())->toArray($this->sampleRow());

        $this->assertSame('SG-ABC123', $out['ref_code']);
        $this->assertSame('Contatti', $out['modulo']);
        $this->assertIsArray($out['contesto']);
        $this->assertIsArray($out['errori']);
        $this->assertCount(2, $out['errori']);
        $this->assertSame('Contatti', $out['contesto']['server']['modulo']);
    }
}
