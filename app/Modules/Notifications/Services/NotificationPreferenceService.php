<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;
use App\Modules\Notifications\Repositories\NotificationPreferenceRepository;
use App\Modules\Notifications\Repositories\PushSubscriptionRepository;
use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Modules\Notifications\Repositories\TelegramUserLinkRepository;
use App\Services\AuditService;

class NotificationPreferenceService
{
    private NotificationEventRepository $eventRepo;
    private NotificationChannelRepository $channelRepo;
    private NotificationPreferenceRepository $preferenceRepo;
    private TelegramBotRepository $botRepo;
    private TelegramUserLinkRepository $linkRepo;
    private TelegramLinkService $telegramLinkService;
    private NotificationModuleCatalogService $catalogService;
    private NotificationEventRegistryService $registryService;
    private PushSubscriptionRepository $pushSubscriptionRepo;
    private VapidKeyService $vapidKeyService;

    public function __construct()
    {
        $this->eventRepo = app(NotificationEventRepository::class);
        $this->channelRepo = app(NotificationChannelRepository::class);
        $this->preferenceRepo = app(NotificationPreferenceRepository::class);
        $this->botRepo = app(TelegramBotRepository::class);
        $this->linkRepo = app(TelegramUserLinkRepository::class);
        $this->telegramLinkService = app(TelegramLinkService::class);
        $this->catalogService = app(NotificationModuleCatalogService::class);
        $this->registryService = app(NotificationEventRegistryService::class);
        $this->pushSubscriptionRepo = app(PushSubscriptionRepository::class);
        $this->vapidKeyService = app(VapidKeyService::class);
    }

    public function getProfileSettings(int $userId): array
    {
        $channels = $this->channelRepo->getAllOrdered();
        $userPrefs = $this->preferenceRepo->getModulePreferenceMap($userId);
        $eventPrefs = $this->preferenceRepo->getEventPreferenceMap($userId);
        $this->registryService->syncEventsToDb();
        $catalogModules = $this->registryService->getEventCatalog();
        $moduleMap = [];
        foreach ($catalogModules as $catalogModule) {
            $moduleMap[$catalogModule['slug']] = $catalogModule;
        }
        $telegramLink = $this->linkRepo->findLinkedByUserId($userId);
        $defaultBot = $this->botRepo->findDefaultEnabled();
        $vapidConfigured = $this->vapidKeyService->isConfigured();
        $pushDeviceCount = $this->pushSubscriptionRepo->countForUser($userId);

        $items = [];
        foreach ($this->catalogService->getBaselineModules() as $module) {
            $moduleCatalog = $moduleMap[$module['slug']] ?? null;
            if ($moduleCatalog === null) {
                continue;
            }

            $moduleDefaultEvent = $moduleCatalog['events'][0] ?? null;
            $bindings = $moduleDefaultEvent['channels'] ?? [];
            $bindingMap = [];
            foreach ($bindings as $binding) {
                $bindingMap[$binding['channel_slug']] = $binding;
            }

            $channelItems = [];
            foreach ($channels as $channel) {
                $channelSlug = $channel['slug'];
                $binding = $bindingMap[$channelSlug] ?? null;
                $defaultEnabled = $binding
                    ? ((bool) $binding['is_enabled'] && (bool) $binding['channel_active'])
                    : false;
                $preferenceValue = $userPrefs[$module['slug']][$channelSlug] ?? null;
                $enabled = $preferenceValue !== null ? $preferenceValue : $defaultEnabled;
                $note = null;

                if ($channelSlug === 'telegram' && !$telegramLink) {
                    $note = $defaultBot
                        ? 'Bot non ancora collegato al tuo account. Le consegne Telegram verranno saltate finché non completi il collegamento.'
                        : 'Nessun bot Telegram attivo configurato dagli amministratori.';
                }
                if ($channelSlug === 'web_push') {
                    if (!$vapidConfigured) {
                        $note = t('notifications.settings.push.note_not_configured');
                    } elseif ($pushDeviceCount === 0) {
                        $note = t('notifications.settings.push.note_no_device');
                    }
                }

                $channelItems[] = [
                    'slug'           => $channelSlug,
                    'name'           => $channel['name'],
                    'description'    => $channel['description'],
                    'enabled'        => $enabled,
                    'default_enabled' => $defaultEnabled,
                    'available'      => (bool) $channel['is_enabled'],
                    'note'           => $note,
                ];
            }

            $eventItems = [];
            foreach (($moduleCatalog['events'] ?? []) as $eventItem) {
                $resolvedBindings = $this->preferenceRepo->resolveChannelBindings(
                    $userId,
                    $module['slug'],
                    $eventItem['slug'],
                    $this->eventRepo->getChannelBindingsForEvent($eventItem['slug'])
                );
                $resolvedMap = [];
                foreach ($resolvedBindings as $binding) {
                    $resolvedMap[$binding['channel_slug']] = $binding;
                }

                $overrideMap = $eventPrefs[$module['slug']][$eventItem['slug']] ?? [];
                $eventChannels = [];
                foreach ($channels as $channel) {
                    $channelSlug = $channel['slug'];
                    $binding = $resolvedMap[$channelSlug] ?? null;
                    $defaultEnabled = $binding
                        ? ((bool) $binding['is_enabled'] && (bool) $binding['channel_active'])
                        : false;
                    $resolvedEnabled = $binding
                        ? (bool) ($binding['resolved_enabled'] ?? false)
                        : false;
                    $overrideState = array_key_exists($channelSlug, $overrideMap)
                        ? ($overrideMap[$channelSlug] ? 'enabled' : 'disabled')
                        : 'inherit';
                    $note = null;

                    if ($channelSlug === 'telegram' && !$telegramLink) {
                        $note = $defaultBot
                            ? 'Per ricevere questo evento su Telegram devi completare il collegamento del bot.'
                            : 'Nessun bot Telegram attivo configurato dagli amministratori.';
                    }
                    if ($channelSlug === 'web_push') {
                        if (!$vapidConfigured) {
                            $note = t('notifications.settings.push.note_not_configured');
                        } elseif ($pushDeviceCount === 0) {
                            $note = t('notifications.settings.push.note_no_device');
                        }
                    }

                    $eventChannels[] = [
                        'slug'            => $channelSlug,
                        'name'            => $channel['name'],
                        'available'       => (bool) $channel['is_enabled'],
                        'default_enabled' => $defaultEnabled,
                        'resolved_enabled' => $resolvedEnabled,
                        'override_state'  => $overrideState,
                        'note'            => $note,
                    ];
                }

                $eventItems[] = [
                    'slug'        => $eventItem['slug'],
                    'name'        => $eventItem['name'],
                    'description' => $eventItem['description'],
                    'icon'        => $eventItem['icon'] ?: $module['icon'],
                    'color'       => $eventItem['color'] ?? '',
                    'context_variables' => $eventItem['context_variables'] ?? [],
                    'is_system'   => (bool) ($eventItem['is_system'] ?? false),
                    'channels'    => $eventChannels,
                ];
            }

            $items[] = [
                'name'        => $module['name'],
                'slug'        => $module['slug'],
                'label'       => $module['label'],
                'description' => $module['description'],
                'icon'        => $module['icon'],
                'event_count' => count($eventItems),
                'channels'    => $channelItems,
                'events'      => $eventItems,
            ];
        }

        $wizard = $this->telegramLinkService->getWizardData($userId);

        return [
            'modules' => $items,
            'channels' => $channels,
            'telegram' => [
                'linked'      => $telegramLink !== null,
                'linked_at'   => $telegramLink['linked_at'] ?? null,
                'username'    => $telegramLink['telegram_username'] ?? null,
                'chat_id'     => $telegramLink['chat_id'] ?? null,
                'bot_name'    => $defaultBot['name'] ?? null,
                'bot_username' => $defaultBot['bot_username'] ?? null,
                'available'   => $defaultBot !== null,
                'deep_link'   => $wizard['deep_link'] ?? null,
                'pending_token' => $wizard['pending_token'] ?? null,
                'webhook_url' => $wizard['webhook_url'] ?? null,
            ],
            'web_push' => [
                'available'        => $vapidConfigured,
                'subscribed'       => $pushDeviceCount > 0,
                'device_count'     => $pushDeviceCount,
                'vapid_public_key' => $this->vapidKeyService->publicKey(),
            ],
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $submittedModules
     * @param array<string, array<string, mixed>> $submittedEvents
     */
    public function updatePreferences(int $userId, array $submittedModules, array $submittedEvents): array
    {
        $channels = $this->channelRepo->getAllOrdered();
        $changes = [];
        $updatedModules = 0;
        $updatedEvents = 0;
        $clearedEvents = 0;

        foreach ($this->catalogService->getBaselineModules() as $module) {
            $moduleInput = $submittedModules[$module['slug']] ?? [];
            foreach ($channels as $channel) {
                $channelSlug = $channel['slug'];
                $isEnabled = isset($moduleInput[$channelSlug]);

                $this->preferenceRepo->upsertPreference(
                    $userId,
                    $module['slug'],
                    '',
                    $channelSlug,
                    $isEnabled
                );

                $changes[$module['slug']][$channelSlug] = $isEnabled;
                $updatedModules++;
            }
        }

        $eventsByModule = $this->eventRepo->getExplicitEventCatalog();
        foreach ($eventsByModule as $moduleSlug => $events) {
            foreach ($events as $event) {
                $eventInput = $submittedEvents[$moduleSlug][$event['slug']] ?? [];
                foreach ($channels as $channel) {
                    $channelSlug = $channel['slug'];
                    $rawValue = $eventInput[$channelSlug] ?? 'inherit';
                    if (is_bool($rawValue)) {
                        $value = $rawValue ? 'enabled' : 'disabled';
                    } else {
                        $value = (string) $rawValue;
                    }

                    if ($value === '1' || $value === 'on') {
                        $value = 'enabled';
                    }
                    if ($value === '0' || $value === 'off') {
                        $value = 'disabled';
                    }

                    if ($value === 'enabled' || $value === 'disabled') {
                        $enabled = $value === 'enabled';
                        $this->preferenceRepo->upsertPreference(
                            $userId,
                            $moduleSlug,
                            $event['slug'],
                            $channelSlug,
                            $enabled
                        );
                        $changes['events'][$event['slug']][$channelSlug] = $value;
                        $updatedEvents++;
                        continue;
                    }

                    if ($this->preferenceRepo->deletePreference($userId, $moduleSlug, $event['slug'], $channelSlug)) {
                        $changes['events'][$event['slug']][$channelSlug] = 'inherit';
                        $clearedEvents++;
                    }
                }
            }
        }

        AuditService::log('notification_preferences_updated', 'user', $userId, null, [
            'changes' => $changes,
            'module_updates' => $updatedModules,
            'event_updates' => $updatedEvents,
            'event_cleared' => $clearedEvents,
        ]);

        return [
            'module_updates' => $updatedModules,
            'event_updates'  => $updatedEvents,
            'event_cleared'  => $clearedEvents,
        ];
    }
}
