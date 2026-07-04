<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;
use App\Modules\Notifications\Services\NotificationEventRegistryService;
use App\Modules\Notifications\Services\NotificationModuleCatalogService;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionProperty;
use Tests\ModuleTestCase;

class NotificationEventRegistryServiceTest extends ModuleTestCase
{
    /** @var NotificationEventRepository&MockObject */
    private NotificationEventRepository $eventRepo;
    /** @var NotificationChannelRepository&MockObject */
    private NotificationChannelRepository $channelRepo;
    /** @var NotificationModuleCatalogService&MockObject */
    private NotificationModuleCatalogService $catalogService;
    private NotificationEventRegistryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepo = $this->getMockBuilder(NotificationEventRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOrCreateEvent', 'getChannelBindingsForEvent', 'upsertChannelBinding'])
            ->getMock();

        $this->channelRepo = $this->getMockBuilder(NotificationChannelRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllOrdered'])
            ->getMock();

        $this->catalogService = $this->getMockBuilder(NotificationModuleCatalogService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaselineModules'])
            ->getMock();

        app()->instance(NotificationEventRepository::class, $this->eventRepo);
        app()->instance(NotificationChannelRepository::class, $this->channelRepo);
        app()->instance(NotificationModuleCatalogService::class, $this->catalogService);

        $this->service = new NotificationEventRegistryService();
    }

    public function testSyncEventsToDbCreatesBindingsUsingDefaultTemplates(): void
    {
        $definitions = [
            'tasks.task_overdue' => [
                'slug' => 'tasks.task_overdue',
                'module_name' => 'Attivita',
                'module_slug' => 'tasks',
                'module_label' => 'Attivita',
                'module_description' => '',
                'module_icon' => 'fa-solid fa-list-check',
                'name' => 'Task scaduta',
                'description' => 'Task oltre scadenza',
                'default_level' => 'warning',
                'icon' => 'fa-clock',
                'color' => 'warning',
                'is_system' => false,
                'source' => 'module_json',
                'context_variables' => ['task_title' => 'Titolo task'],
                'default_templates' => [
                    'in_app' => ['subject' => 'Task scaduta: {{task_title}}', 'body' => 'Dettagli {{task_title}}'],
                    'email' => ['subject' => 'Email task {{task_title}}', 'body' => 'Body email {{task_title}}'],
                    'telegram' => ['subject' => null, 'body' => 'TG {{task_title}}'],
                ],
            ],
        ];

        $this->setCachedDefinitions($definitions);

        $this->channelRepo->expects($this->once())
            ->method('getAllOrdered')
            ->willReturn([
                ['slug' => 'in_app'],
                ['slug' => 'email'],
                ['slug' => 'telegram'],
            ]);

        $this->eventRepo->expects($this->once())
            ->method('findOrCreateEvent')
            ->with(
                'tasks.task_overdue',
                'tasks',
                'Task scaduta',
                'Task oltre scadenza',
                'warning',
                'fa-clock',
                'warning',
                false,
                'module_json',
                ['task_title' => 'Titolo task']
            )
            ->willReturn(['id' => 11]);

        $this->eventRepo->expects($this->once())
            ->method('getChannelBindingsForEvent')
            ->with('tasks.task_overdue')
            ->willReturn([]);

        $expectedCalls = [
            [11, 'in_app', true, 'Task scaduta: {{task_title}}', 'Dettagli {{task_title}}', null],
            [11, 'email', false, 'Email task {{task_title}}', 'Body email {{task_title}}', null],
            [11, 'telegram', false, null, 'TG {{task_title}}', null],
        ];
        $callIndex = 0;

        $this->eventRepo->expects($this->exactly(3))
            ->method('upsertChannelBinding')
            ->willReturnCallback(function (
                int $eventTypeId,
                string $channelSlug,
                bool $isEnabled,
                ?string $subjectTemplate,
                ?string $bodyTemplate,
                ?string $layoutConfig
            ) use (&$callIndex, $expectedCalls): void {
                $this->assertSame($expectedCalls[$callIndex], [
                    $eventTypeId,
                    $channelSlug,
                    $isEnabled,
                    $subjectTemplate,
                    $bodyTemplate,
                    $layoutConfig,
                ]);
                $callIndex++;
            });

        $this->service->syncEventsToDb();
    }

    public function testSyncEventsToDbPreservesExistingTemplatesAndEnabledState(): void
    {
        $definitions = [
            'reports.generated' => [
                'slug' => 'reports.generated',
                'module_name' => 'Reports',
                'module_slug' => 'reports',
                'module_label' => 'Reports',
                'module_description' => '',
                'module_icon' => 'fa-solid fa-file',
                'name' => 'Report generato',
                'description' => null,
                'default_level' => 'success',
                'icon' => null,
                'color' => null,
                'is_system' => false,
                'source' => 'module_json',
                'context_variables' => [],
                'default_templates' => [
                    'in_app' => ['subject' => 'Nuovo report', 'body' => 'Body default'],
                    'email' => ['subject' => 'Email default', 'body' => 'Email body default'],
                    'telegram' => ['subject' => null, 'body' => null],
                ],
            ],
        ];

        $this->setCachedDefinitions($definitions);

        $this->channelRepo->expects($this->once())
            ->method('getAllOrdered')
            ->willReturn([
                ['slug' => 'in_app'],
                ['slug' => 'email'],
            ]);

        $this->eventRepo->expects($this->once())
            ->method('findOrCreateEvent')
            ->willReturn(['id' => 21]);

        $this->eventRepo->expects($this->once())
            ->method('getChannelBindingsForEvent')
            ->with('reports.generated')
            ->willReturn([
                [
                    'channel_slug' => 'in_app',
                    'is_enabled' => 0,
                    'subject_template' => 'Titolo custom',
                    'body_template' => 'Corpo custom',
                    'layout_config' => '{"x":1}',
                ],
                [
                    'channel_slug' => 'email',
                    'is_enabled' => 1,
                    'subject_template' => '',
                    'body_template' => null,
                    'layout_config' => null,
                ],
            ]);

        $expectedCalls = [
            [21, 'in_app', false, 'Titolo custom', 'Corpo custom', '{"x":1}'],
            [21, 'email', true, 'Email default', 'Email body default', null],
        ];
        $callIndex = 0;

        $this->eventRepo->expects($this->exactly(2))
            ->method('upsertChannelBinding')
            ->willReturnCallback(function (
                int $eventTypeId,
                string $channelSlug,
                bool $isEnabled,
                ?string $subjectTemplate,
                ?string $bodyTemplate,
                ?string $layoutConfig
            ) use (&$callIndex, $expectedCalls): void {
                $this->assertSame($expectedCalls[$callIndex], [
                    $eventTypeId,
                    $channelSlug,
                    $isEnabled,
                    $subjectTemplate,
                    $bodyTemplate,
                    $layoutConfig,
                ]);
                $callIndex++;
            });

        $this->service->syncEventsToDb();
    }

    public function testGetContextVariablesForEventUsesCachedDefinitions(): void
    {
        $this->setCachedDefinitions([
            'calendar.evento' => [
                'context_variables' => [
                    'event_name' => 'Nome evento',
                    'start_date' => 'Data inizio',
                ],
            ],
        ]);

        $vars = $this->service->getContextVariablesForEvent('calendar.evento');
        $missing = $this->service->getContextVariablesForEvent('unknown.event');

        $this->assertSame(['event_name' => 'Nome evento', 'start_date' => 'Data inizio'], $vars);
        $this->assertSame([], $missing);
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function setCachedDefinitions(array $definitions): void
    {
        $prop = new ReflectionProperty(NotificationEventRegistryService::class, 'cachedDefinitions');
        $prop->setAccessible(true);
        $prop->setValue($this->service, $definitions);
    }
}
