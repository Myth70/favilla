<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;

class NotificationEventRegistryService
{
    private NotificationEventRepository $eventRepo;
    private NotificationChannelRepository $channelRepo;
    private NotificationModuleCatalogService $catalogService;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $cachedDefinitions = null;

    public function __construct()
    {
        $this->eventRepo = app(NotificationEventRepository::class);
        $this->channelRepo = app(NotificationChannelRepository::class);
        $this->catalogService = app(NotificationModuleCatalogService::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStaticEventDefinitions(): array
    {
        if ($this->cachedDefinitions !== null) {
            return $this->cachedDefinitions;
        }

        $definitions = [];
        $moduleMeta = [];
        foreach ($this->catalogService->getBaselineModules() as $module) {
            $moduleMeta[$module['slug']] = $module;
        }

        foreach ($this->scanModuleJsonFiles() as $moduleSlug => $payload) {
            $module = $moduleMeta[$moduleSlug] ?? [
                'name' => $payload['module_name'],
                'slug' => $moduleSlug,
                'label' => $payload['module_name'],
                'description' => '',
                'icon' => 'fa-solid fa-bell',
            ];

            foreach ($payload['events'] as $event) {
                $slug = (string) ($event['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }

                $definitions[$slug] = [
                    'slug' => $slug,
                    'module_name' => $payload['module_name'],
                    'module_slug' => $moduleSlug,
                    'module_label' => $module['label'] ?? $payload['module_name'],
                    'module_description' => $module['description'] ?? '',
                    'module_icon' => $module['icon'] ?? 'fa-solid fa-bell',
                    'name' => (string) ($event['name'] ?? $slug),
                    'description' => isset($event['description']) ? (string) $event['description'] : null,
                    'default_level' => $this->normalizeLevel((string) ($event['default_level'] ?? 'info')),
                    'icon' => $this->normalizeNullable((string) ($event['icon'] ?? '')),
                    'color' => $this->normalizeNullable((string) ($event['color'] ?? '')),
                    'is_system' => (bool) ($event['is_system'] ?? false),
                    'source' => 'module_json',
                    'context_variables' => is_array($event['context_variables'] ?? null) ? $event['context_variables'] : [],
                    'default_templates' => $this->normalizeDefaultTemplates(
                        is_array($event['default_templates'] ?? null) ? $event['default_templates'] : []
                    ),
                ];
            }
        }

        $this->cachedDefinitions = $definitions;
        return $definitions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEventCatalog(): array
    {
        $this->syncEventsToDb();

        $definitions = $this->getStaticEventDefinitions();
        $eventsBySlug = [];
        foreach ($this->eventRepo->getAllEventsOrdered() as $row) {
            $eventsBySlug[(string) $row['slug']] = $row;
        }

        $modules = [];
        foreach ($this->catalogService->getBaselineModules() as $module) {
            $moduleSlug = (string) $module['slug'];
            $items = [];

            foreach ($definitions as $slug => $definition) {
                if ($definition['module_slug'] !== $moduleSlug) {
                    continue;
                }

                $dbEvent = $eventsBySlug[$slug] ?? null;
                if (!$dbEvent) {
                    continue;
                }

                $bindings = $this->eventRepo->getChannelBindingsForEvent($slug);
                $items[] = [
                    'id' => (int) $dbEvent['id'],
                    'slug' => $slug,
                    'name' => (string) ($dbEvent['name'] ?? $definition['name']),
                    'description' => $dbEvent['description'] ?? $definition['description'],
                    'module_slug' => $moduleSlug,
                    'default_level' => (string) ($dbEvent['default_level'] ?? $definition['default_level']),
                    'icon' => $dbEvent['icon'] ?? $definition['icon'] ?? $module['icon'],
                    'color' => $dbEvent['color'] ?? $definition['color'] ?? '',
                    'source' => $dbEvent['source'] ?? 'module_json',
                    'is_system' => (bool) ($dbEvent['is_system'] ?? $definition['is_system'] ?? false),
                    'context_variables' => $this->decodeContextSchema($dbEvent['context_schema'] ?? null, $definition['context_variables']),
                    'default_templates' => $definition['default_templates'],
                    'channels' => $bindings,
                ];
            }

            if (empty($items)) {
                continue;
            }

            usort($items, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            $modules[] = [
                'name' => $module['name'],
                'slug' => $moduleSlug,
                'label' => $module['label'],
                'description' => $module['description'],
                'icon' => $module['icon'],
                'events' => $items,
            ];
        }

        return $modules;
    }

    public function syncEventsToDb(): void
    {
        $definitions = $this->getStaticEventDefinitions();
        if (empty($definitions)) {
            return;
        }

        $channelSlugs = array_map(
            static fn (array $channel): string => (string) $channel['slug'],
            $this->channelRepo->getAllOrdered()
        );

        foreach ($definitions as $definition) {
            $event = $this->eventRepo->findOrCreateEvent(
                (string) $definition['slug'],
                (string) $definition['module_slug'],
                (string) $definition['name'],
                $definition['description'] !== null ? (string) $definition['description'] : null,
                (string) $definition['default_level'],
                $definition['icon'] !== null ? (string) $definition['icon'] : null,
                $definition['color'] !== null ? (string) $definition['color'] : null,
                (bool) $definition['is_system'],
                'module_json',
                $definition['context_variables']
            );

            $eventId = (int) $event['id'];
            $bindingMap = [];
            foreach ($this->eventRepo->getChannelBindingsForEvent((string) $definition['slug']) as $binding) {
                $bindingMap[(string) $binding['channel_slug']] = $binding;
            }

            foreach ($channelSlugs as $channelSlug) {
                $template = $definition['default_templates'][$channelSlug] ?? [];
                $defaultSubject = $this->normalizeNullable((string) ($template['subject'] ?? ''));
                $defaultBody = $this->normalizeNullable((string) ($template['body'] ?? ''));
                $enabled = $channelSlug === 'in_app';

                if (!isset($bindingMap[$channelSlug])) {
                    $this->eventRepo->upsertChannelBinding(
                        $eventId,
                        $channelSlug,
                        $enabled,
                        $defaultSubject,
                        $defaultBody,
                        null
                    );
                    continue;
                }

                $binding = $bindingMap[$channelSlug];
                $subjectTemplate = $this->normalizeNullable((string) ($binding['subject_template'] ?? ''));
                $bodyTemplate = $this->normalizeNullable((string) ($binding['body_template'] ?? ''));

                $this->eventRepo->upsertChannelBinding(
                    $eventId,
                    $channelSlug,
                    (bool) ($binding['is_enabled'] ?? false),
                    $subjectTemplate ?? $defaultSubject,
                    $bodyTemplate ?? $defaultBody,
                    $this->normalizeNullable((string) ($binding['layout_config'] ?? ''))
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function getContextVariablesForEvent(string $eventSlug): array
    {
        $definitions = $this->getStaticEventDefinitions();
        $context = $definitions[$eventSlug]['context_variables'] ?? [];
        return is_array($context) ? $context : [];
    }

    /**
     * @return array<string, array{module_name:string,events:array<int,array<string,mixed>>}>
     */
    private function scanModuleJsonFiles(): array
    {
        $result = [];

        foreach (glob(BASE_PATH . '/app/Modules/*/module.json') ?: [] as $jsonFile) {
            $moduleName = basename(dirname($jsonFile));
            if ($moduleName === '_Template') {
                continue;
            }

            $json = json_decode((string) file_get_contents($jsonFile), true);
            if (!is_array($json)) {
                continue;
            }

            $events = is_array($json['notification_events'] ?? null) ? $json['notification_events'] : [];
            if (empty($events)) {
                continue;
            }

            $moduleSlug = NotificationModuleCatalogService::moduleNameToSlug($moduleName);
            $result[$moduleSlug] = [
                'module_name' => $moduleName,
                'events' => $events,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $templates
     * @return array<string, array{subject:?string,body:?string}>
     */
    private function normalizeDefaultTemplates(array $templates): array
    {
        $normalized = [];
        foreach (['in_app', 'email', 'telegram'] as $channelSlug) {
            $tpl = is_array($templates[$channelSlug] ?? null) ? $templates[$channelSlug] : [];
            $normalized[$channelSlug] = [
                'subject' => $this->normalizeNullable((string) ($tpl['subject'] ?? '')),
                'body' => $this->normalizeNullable((string) ($tpl['body'] ?? '')),
            ];
        }

        return $normalized;
    }

    private function normalizeLevel(string $level): string
    {
        $allowed = ['info', 'success', 'warning', 'danger'];
        return in_array($level, $allowed, true) ? $level : 'info';
    }

    private function normalizeNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<string, string> $fallback
     * @return array<string, string>
     */
    private function decodeContextSchema(mixed $raw, array $fallback): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $fallback;
    }
}
