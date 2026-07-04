<?php

namespace App\Modules\HelpOnline\Tests\Unit;

use App\Core\Container;
use App\Core\ModuleLoader;
use App\Core\Router;
use App\Modules\HelpOnline\Repositories\HelpOnlineRepository;
use App\Modules\HelpOnline\Services\HelpAdminService;
use App\Modules\HelpOnline\Services\HelpOnlineService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class HelpOnlineServiceTest extends TestCase
{
    private HelpOnlineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = $this->createMock(HelpOnlineRepository::class);

        $router = new Router();
        $router->get('/help', ['HelpOnlineController', 'index'])->name('helponline.index');
        $router->get('/', ['HomeController', 'index'])->name('home.index');
        Container::getInstance()->bind(Router::class, fn () => $router);

        $this->service = new HelpOnlineService($repository, null);
    }

    public function testFormatAnswerHtmlRendersHeadingsListsAndCode(): void
    {
        $markdown = <<<'MD'
## Sezione esempio

Paragrafo introduttivo con **grassetto** e `codice`.

- Primo elemento
- Secondo elemento

```
echo "ciao";
```

> Citazione importante
MD;

        $html = $this->invokePrivate('formatAnswerHtml', [$markdown, '']);

        $this->assertStringContainsString('<h4 class="ho-md-heading">Sezione esempio</h4>', $html);
        $this->assertStringContainsString('<strong>grassetto</strong>', $html);
        $this->assertStringContainsString('<code>codice</code>', $html);
        $this->assertStringContainsString('<ul><li>Primo elemento</li><li>Secondo elemento</li></ul>', $html);
        $this->assertStringContainsString('<pre class="ho-code"><code>echo &quot;ciao&quot;;</code></pre>', $html);
        $this->assertStringContainsString('<blockquote class="ho-quote">Citazione importante</blockquote>', $html);
    }

    public function testApplyInlineMarkdownBlocksJavascriptUrls(): void
    {
        $escaped = htmlspecialchars('Visita [link](javascript:alert(1)) ora.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $result = $this->invokePrivate('applyInlineMarkdown', [$escaped]);

        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('<a ', $result);
    }

    public function testApplyInlineMarkdownAllowsHttpLinksWithExternalRel(): void
    {
        $escaped = htmlspecialchars('Vedi [docs](https://example.com/x).', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $result = $this->invokePrivate('applyInlineMarkdown', [$escaped]);

        $this->assertStringContainsString('<a href="https://example.com/x" target="_blank" rel="noopener noreferrer">docs</a>', $result);
    }

    public function testApplyInlineMarkdownAllowsRelativePathsWithoutTargetBlank(): void
    {
        $escaped = htmlspecialchars('Vai in [home](/home).', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $result = $this->invokePrivate('applyInlineMarkdown', [$escaped]);

        $this->assertStringContainsString('<a href="/home">home</a>', $result);
        $this->assertStringNotContainsString('target="_blank"', $result);
    }

    public function testFormatAnswerHtmlEscapesScriptInjections(): void
    {
        $markdown = 'Testo con <script>alert(1)</script> dentro.';

        $html = $this->invokePrivate('formatAnswerHtml', [$markdown, '']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testFormatAnswerHtmlReturnsPlaceholderWhenSourceIsEmpty(): void
    {
        $html = $this->invokePrivate('formatAnswerHtml', ['', '']);

        $this->assertSame('<p>Nessun contenuto disponibile.</p>', $html);
    }

    public function testBuildHelpUrlDropsEmptyQueryAndZeroChunk(): void
    {
        $base = $this->invokePrivate('buildHelpUrl', ['', 0]);
        $withQuery = $this->invokePrivate('buildHelpUrl', ['come funziona', 0]);
        $withChunk = $this->invokePrivate('buildHelpUrl', ['', 42]);

        $this->assertStringNotContainsString('?', $base);
        $this->assertStringContainsString('q=come', $withQuery);
        $this->assertStringNotContainsString('chunk=', $withQuery);
        $this->assertStringContainsString('chunk=42', $withChunk);
        $this->assertStringNotContainsString('q=', $withChunk);
    }

    public function testCreateQaModuleRejectsMissingFields(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isQaSchemaReady')->willReturn(true);

        $service = new HelpAdminService($repository);

        $result = $service->createQaModule([
            'module_key' => '',
            'module_name' => '',
            'label' => '',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Compila', (string) ($result['message'] ?? ''));
    }

    public function testCreateQaModuleCallsRepositoryOnValidPayload(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isQaSchemaReady')->willReturn(true);
        $repository->expects($this->once())
            ->method('createQaModule')
            ->with($this->callback(static function (array $payload): bool {
                return $payload['module_key'] === 'contatti'
                    && $payload['module_name'] === 'Contatti'
                    && $payload['label'] === 'Contatti';
            }))
            ->willReturn(10);

        $service = new HelpAdminService($repository);

        $result = $service->createQaModule([
            'module_key' => 'Contatti',
            'module_name' => 'Contatti',
            'label' => 'Contatti',
            'audience_default' => 'user',
            'locale_default' => 'it',
            'is_active' => '1',
        ]);

        $this->assertTrue($result['ok']);
    }

    public function testCreateQaEntryRejectsMissingQuestionOrAnswer(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isQaSchemaReady')->willReturn(true);

        $service = new HelpAdminService($repository);

        $result = $service->createQaEntry([
            'module_id' => '1',
            'question' => '',
            'answer_markdown' => '',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Compila modulo, domanda e risposta', (string) ($result['message'] ?? ''));
    }

    public function testSaveQaEntryAliasesRejectsInvalidEntryId(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $service = new HelpAdminService($repository);

        $result = $service->saveQaEntryAliases(0, "alias 1\nalias 2");

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Record non valido', (string) ($result['message'] ?? ''));
    }

    public function testDecorateRelatedEntriesFallsBackToQuestionWhenTitleMissing(): void
    {
        $items = $this->invokePrivate('decorateRelatedEntries', [[
            [
                'id' => 77,
                'question' => 'Come creo una attivita?',
                'excerpt' => 'Apri il modulo Attivita.',
                'permission_slug' => null,
            ],
        ], '']);

        $this->assertCount(1, $items);
        $this->assertSame('Come creo una attivita?', $items[0]['title']);
        $this->assertSame('Apri il modulo Attivita.', $items[0]['excerpt']);
    }

    public function testSearchKnowledgeBaseUsesOnlyQaPath(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(true);

        // searchQaCandidates è l'unico canale di ricerca: nessun altro fallback.
        $repository->expects($this->atLeastOnce())
            ->method('searchQaCandidates')
            ->willReturn([]);

        $service = new HelpOnlineService($repository, null);

        $results = $service->searchKnowledgeBase('come creo un evento', '/calendar', 5);
        $this->assertSame([], $results);
    }

    public function testGetQuickPromptsUsesExistingEntriesAndBuildsDirectLinks(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(true);
        $repository->method('listQuickPromptEntries')->willReturnCallback(
            static function (?string $moduleName, int $limit): array {
                if ($moduleName === 'Home') {
                    return [
                        ['id' => 101, 'question' => 'Come funziona la dashboard Home?', 'permission_slug' => null],
                        ['id' => 102, 'question' => 'Ricerca globale', 'permission_slug' => null],
                    ];
                }

                return [
                    ['id' => 201, 'question' => 'Come funziona il modulo Contatti?', 'permission_slug' => null],
                    ['id' => 202, 'question' => 'Come funziona il modulo Calendario?', 'permission_slug' => null],
                ];
            }
        );

        $service = new HelpOnlineService($repository, null);

        $prompts = $service->getQuickPrompts('Home');

        $this->assertSame('Come funziona la dashboard Home?', $prompts[0]['label']);
        $this->assertSame('Come funziona la dashboard Home?', $prompts[0]['message']);
        $this->assertSame(101, $prompts[0]['chunk']);
        $this->assertStringContainsString('chunk=101', $prompts[0]['url']);
        $this->assertStringNotContainsString('Dove+cambio+tema+e+preferenze', $prompts[0]['url']);
    }

    public function testAnswerQuestionUsesSelectedChunkBeforeSearchRanking(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(true);
        $repository->expects($this->never())->method('searchQaCandidates');
        $repository->method('getQaEntryById')->with(661)->willReturn([
            'id' => 661,
            'module_id' => 1,
            'module_name' => 'Home',
            'question' => 'Preferenze interfaccia',
            'answer_markdown' => 'Le preferenze si gestiscono dalla sidebar.',
            'answer_plain' => 'Le preferenze si gestiscono dalla sidebar.',
            'excerpt' => 'Le preferenze si gestiscono dalla sidebar.',
            'route_name' => 'home.index',
            'permission_slug' => null,
            'ranking_weight' => 20,
            'aliases' => '',
        ]);
        $repository->method('getRelatedQaEntries')->willReturn([]);
        $repository->method('recordQuery')->willReturn(77);

        $service = new HelpOnlineService($repository, null);

        $result = $service->answerQuestion('Dove cambio tema e preferenze?', 5, '/home', '', 661);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['matched']);
        $this->assertSame('Preferenze interfaccia', $result['answer']['title']);
        $this->assertSame(77, $result['queryId']);
    }

    public function testDecorateQaEntryPrefersModuleLabelForDisplay(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('getRelatedQaEntries')->willReturn([]);

        $service = new HelpOnlineService($repository, null);

        $entry = $this->invokePrivate('decorateQaEntry', [[
            'id' => 734,
            'module_id' => 116,
            'module_name' => 'Teams',
            'module_label' => 'Messaggi',
            'question' => 'Come avvio una chat con un collega?',
            'answer_markdown' => 'Apri Messaggi dalla sidebar.',
            'answer_plain' => 'Apri Messaggi dalla sidebar.',
            'excerpt' => 'Apri Messaggi dalla sidebar.',
            'route_name' => 'teams.index',
            'permission_slug' => null,
            'ranking_weight' => 20,
            'aliases' => '',
        ], '/teams']);

        $this->assertSame('Teams', $entry['module_name']);
        $this->assertSame('Messaggi', $entry['module_title']);
    }

    public function testBuildContextLabelUsesNavigationLabelWhenAvailable(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $loader = $this->createMock(ModuleLoader::class);
        $loader->method('getModules')->willReturn([
            ['name' => 'Blog'],
            ['name' => 'Teams'],
        ]);
        $loader->method('readModuleJson')->willReturnCallback(static function (string $moduleName): array {
            return match ($moduleName) {
                'Blog' => ['navigation' => [['label' => 'Comunicazioni']]],
                'Teams' => ['navigation' => [['label' => 'Messaggi']]],
                default => [],
            };
        });

        $service = new HelpOnlineService($repository, $loader);

        $this->assertSame('Contesto: Comunicazioni', $service->buildContextLabel('Blog', ''));
        $this->assertSame('Pagina corrente · Messaggi', $service->buildContextLabel('Teams', 'Pagina corrente'));
    }

    public function testGetPanelDataExposesDisplayContextModuleTitle(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(false);

        $loader = $this->createMock(ModuleLoader::class);
        $loader->method('getModules')->willReturn([
            ['name' => 'Teams'],
        ]);
        $loader->method('readModuleJson')->willReturnCallback(static function (string $moduleName): array {
            return $moduleName === 'Teams'
                ? ['navigation' => [['label' => 'Messaggi']]]
                : [];
        });

        $service = new HelpOnlineService($repository, $loader);

        $data = $service->getPanelData('/teams', 'Chat interna');

        $this->assertSame('Teams', $data['contextModule']);
        $this->assertSame('Messaggi', $data['contextModuleTitle']);
        $this->assertSame('Chat interna · Messaggi', $data['contextLabel']);
    }

    public function testGetAdminOverviewSummaryUsesQaCountsWithCoherentLabels(): void
    {
        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(true);
        $repository->method('getQaSummary')->willReturn([
            'modules' => 7,
            'entries' => 123,
            'aliases' => 456,
            'queries' => 12,
        ]);
        $repository->method('getQueryStats')->willReturn([
            'total' => 12, 'matched' => 8, 'unmatched' => 4,
            'helpful' => 5, 'unhelpful' => 1, 'pending' => 6,
        ]);
        $repository->method('getTopUnmatchedQueries')->willReturn([]);
        $repository->method('listQaModulesWithStats')->willReturn([]);
        $repository->method('listQaEntries')->willReturn([]);
        $repository->method('listQueries')->willReturn([]);
        $repository->method('listQaAliases')->willReturn([]);

        $service = new HelpAdminService($repository);

        $data = $service->getAdminOverviewData();

        $this->assertArrayHasKey('summary', $data);
        $this->assertSame(7, $data['summary']['modules']);
        $this->assertSame(123, $data['summary']['entries']);
        $this->assertSame(456, $data['summary']['aliases']);
        $this->assertSame(12, $data['summary']['queries']);
        $this->assertSame(67, $data['stats']['match_rate']); // 8/12 = 66.67 -> 67
    }

    public function testGetTopicCardsDedupsNonContextModulesButKeepsContextModuleFull(): void
    {
        // Simulazione realistica: 8 entries "Accesso", una per modulo, +
        // entries aggiuntive del modulo Calendario (contesto corrente).
        $topics = [
            ['id' => 1, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Attivita'],
            ['id' => 2, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Calendario'],
            ['id' => 3, 'title' => 'Eventi ricorrenti', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Calendario'],
            ['id' => 4, 'title' => 'Promemoria', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Calendario'],
            ['id' => 5, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Contatti'],
            ['id' => 6, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Files'],
            ['id' => 7, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Notifications'],
            ['id' => 8, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Reports'],
        ];

        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(true);
        $repository->method('listQaTopics')->willReturn($topics);

        $service = new HelpOnlineService($repository, null);

        // Context = Calendario, dedup attivo, limit 4
        $cards = $service->getTopicCards('Calendario', 4, true);

        $this->assertCount(4, $cards);

        // I primi 3 slot devono essere TUTTI di Calendario (modulo contesto = no dedup)
        $this->assertSame('Calendario', $cards[0]['module_name']);
        $this->assertSame('Calendario', $cards[1]['module_name']);
        $this->assertSame('Calendario', $cards[2]['module_name']);

        // Il quarto slot deve essere il primo "Accesso" di un altro modulo
        $this->assertNotSame('Calendario', $cards[3]['module_name']);
    }

    public function testGetTopicCardsDedupsByModuleWhenNoContext(): void
    {
        // Stesso dataset, ma nessun contesto: ogni modulo deve apparire al massimo 1 volta.
        $topics = [
            ['id' => 1, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Attivita'],
            ['id' => 2, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Calendario'],
            ['id' => 3, 'title' => 'Eventi ricorrenti', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Calendario'],
            ['id' => 4, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Contatti'],
            ['id' => 5, 'title' => 'Accesso', 'excerpt' => '', 'route_name' => null, 'permission_slug' => null, 'module_name' => 'Files'],
        ];

        $repository = $this->createMock(HelpOnlineRepository::class);
        $repository->method('isSchemaReady')->willReturn(true);
        $repository->method('listQaTopics')->willReturn($topics);

        $service = new HelpOnlineService($repository, null);

        $cards = $service->getTopicCards(null, 10, true);

        // Senza contesto e con dedup: 1 topic per modulo distinto
        $moduleSet = array_unique(array_map(fn (array $c): string => (string) $c['module_name'], $cards));
        $this->assertSame(count($cards), count($moduleSet), 'Senza contesto, dedupByModule deve produrre 1 topic per modulo');
        $this->assertCount(4, $cards); // 4 moduli distinti
    }

    /** @param array<int,mixed> $args */
    private function invokePrivate(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->service, $method);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($this->service, $args);
    }
}
