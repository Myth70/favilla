<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationChannelRepository;
use App\Modules\Notifications\Repositories\NotificationDeliveryRepository;
use App\Modules\Notifications\Repositories\NotificationDispatchRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;
use App\Modules\Notifications\Repositories\NotificationQueueRepository;
use App\Modules\Notifications\Repositories\PushSubscriptionRepository;
use App\Modules\Notifications\Repositories\TelegramBotRepository;
use App\Services\AuditService;
use PDO;

class NotificationAdminService
{
    private NotificationEventRepository $eventRepo;
    private NotificationChannelRepository $channelRepo;
    private NotificationDispatchRepository $dispatchRepo;
    private NotificationDeliveryRepository $deliveryRepo;
    private NotificationQueueRepository $queueRepo;
    private TelegramBotRepository $botRepo;
    private NotificationEventRegistryService $registryService;
    private PushSubscriptionRepository $pushSubscriptionRepo;
    private VapidKeyService $vapidKeyService;

    public function __construct()
    {
        $this->eventRepo = app(NotificationEventRepository::class);
        $this->channelRepo = app(NotificationChannelRepository::class);
        $this->dispatchRepo = app(NotificationDispatchRepository::class);
        $this->deliveryRepo = app(NotificationDeliveryRepository::class);
        $this->queueRepo = app(NotificationQueueRepository::class);
        $this->botRepo = app(TelegramBotRepository::class);
        $this->registryService = app(NotificationEventRegistryService::class);
        $this->pushSubscriptionRepo = app(PushSubscriptionRepository::class);
        $this->vapidKeyService = app(VapidKeyService::class);
    }

    public function syncEventRegistry(): void
    {
        $this->registryService->syncEventsToDb();
    }

    public function getDashboardData(): array
    {
        $this->syncEventRegistry();
        $channels = $this->channelRepo->getAllOrdered();

        $modules = $this->registryService->getEventCatalog();
        foreach ($modules as &$module) {
            foreach ($module['events'] as &$event) {
                $bindingMap = [];
                foreach (($event['channels'] ?? []) as $binding) {
                    $bindingMap[$binding['channel_slug']] = $binding;
                }

                $eventChannels = [];
                foreach ($channels as $channel) {
                    $binding = $bindingMap[$channel['slug']] ?? null;
                    $tplDefaults = $event['default_templates'][$channel['slug']] ?? ['subject' => null, 'body' => null];
                    $eventChannels[] = [
                        'slug'             => $channel['slug'],
                        'name'             => $channel['name'],
                        'enabled'          => $binding ? (bool) $binding['is_enabled'] : false,
                        'subject_template' => $binding['subject_template'] ?? ($tplDefaults['subject'] ?? ''),
                        'body_template'    => $binding['body_template'] ?? ($tplDefaults['body'] ?? ''),
                        'layout_config'    => $binding['layout_config'] ?? '',
                    ];
                }
                $event['channels'] = $eventChannels;
            }
            unset($event);
        }
        unset($module);

        return [
            'channels'            => $channels,
            'modules'             => $modules,
            'dispatchStats'       => $this->dispatchRepo->getStatusCounts(),
            'queueStats'          => $this->queueRepo->getStatusCountsByChannel(),
            'deliveryStats'       => $this->deliveryRepo->getStatusCountsByChannel(),
            'recentQueue'         => $this->queueRepo->getRecentWithDetails(),
            'defaultBot'          => $this->enrichBot($this->botRepo->findDefault()),
            'bots'                => array_map(fn (array $bot) => $this->enrichBot($bot), $this->botRepo->findAll()),
            'contextVariables'    => $this->buildContextVariableMap($modules),
            'webPush'             => [
                'configured' => $this->vapidKeyService->isConfigured(),
                'public_key' => $this->vapidKeyService->publicKey(),
                'subject'    => $this->vapidKeyService->subject(),
                'stats'      => $this->pushSubscriptionRepo->stats(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function updateBindings(array $submitted): int
    {
        $updated = 0;
        $eventsInput = is_array($submitted['events'] ?? null) ? $submitted['events'] : [];

        foreach ($eventsInput as $moduleSlug => $moduleEvents) {
            if (!is_array($moduleEvents)) {
                continue;
            }

            foreach ($moduleEvents as $eventSlug => $eventInput) {
                if (!is_array($eventInput)) {
                    continue;
                }
                $updated += $this->updateEventBindings((string) $eventSlug, $eventInput);
            }
        }

        AuditService::log('notification_bindings_updated', 'notification', null, null, [
            'events'  => array_keys($eventsInput),
        ]);

        return $updated;
    }

    public function updateEventBindings(string $eventSlug, array $submitted): int
    {
        $channels = $this->channelRepo->getAllOrdered();
        $event = $this->eventRepo->findBySlug($eventSlug);
        if (!$event) {
            return 0;
        }

        $updated = 0;
        $icon = trim((string) ($submitted['icon'] ?? $event['icon'] ?? ''));
        $color = trim((string) ($submitted['color'] ?? $event['color'] ?? ''));

        $this->eventRepo->update((int) $event['id'], [
            'icon' => $icon !== '' ? $icon : null,
            'color' => $color !== '' ? $color : null,
        ]);
        $updated++;

        $channelInputs = is_array($submitted['channels'] ?? null) ? $submitted['channels'] : [];
        foreach ($channels as $channel) {
            $channelInput = is_array($channelInputs[$channel['slug']] ?? null)
                ? $channelInputs[$channel['slug']]
                : [];

            $this->eventRepo->upsertChannelBinding(
                (int) $event['id'],
                (string) $channel['slug'],
                isset($channelInput['enabled']),
                self::normalizeNullableText($channelInput['subject_template'] ?? null),
                self::normalizeNullableText($channelInput['body_template'] ?? null),
                self::normalizeNullableText($channelInput['layout_config'] ?? null)
            );
            $updated++;
        }

        return $updated;
    }

    public function getEventCardData(string $eventSlug): ?array
    {
        $channels = $this->channelRepo->getAllOrdered();
        foreach ($this->registryService->getEventCatalog() as $module) {
            foreach ($module['events'] as $event) {
                if ($event['slug'] === $eventSlug) {
                    $bindingMap = [];
                    foreach (($event['channels'] ?? []) as $binding) {
                        $bindingMap[$binding['channel_slug']] = $binding;
                    }

                    $eventChannels = [];
                    foreach ($channels as $channel) {
                        $binding = $bindingMap[$channel['slug']] ?? null;
                        $tplDefaults = $event['default_templates'][$channel['slug']] ?? ['subject' => null, 'body' => null];
                        $eventChannels[] = [
                            'slug'             => $channel['slug'],
                            'name'             => $channel['name'],
                            'enabled'          => $binding ? (bool) $binding['is_enabled'] : false,
                            'subject_template' => $binding['subject_template'] ?? ($tplDefaults['subject'] ?? ''),
                            'body_template'    => $binding['body_template'] ?? ($tplDefaults['body'] ?? ''),
                            'layout_config'    => $binding['layout_config'] ?? '',
                        ];
                    }
                    $event['channels'] = $eventChannels;
                    return $event;
                }
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Bot management
    // ------------------------------------------------------------------

    /**
     * Create or update a Telegram bot configuration.
     *
     * @param array<string,mixed> $submitted  raw form payload (already sanitized by the caller)
     * @param int $createdBy                  authenticated user id (0 if unknown)
     */
    public function saveBot(array $submitted, int $createdBy = 0): void
    {
        $botId = (int) ($submitted['bot_id'] ?? 0);
        $existing = $botId > 0 ? $this->botRepo->find($botId) : null;
        $name = trim((string) ($submitted['name'] ?? 'Bot notifiche'));
        $username = trim((string) ($submitted['bot_username'] ?? ''));
        $token = trim((string) ($submitted['bot_token'] ?? ''));
        $isEnabled = isset($submitted['is_enabled']) ? 1 : 0;
        $isDefault = isset($submitted['is_default']) ? 1 : 0;

        if ($isDefault) {
            $this->botRepo->clearDefault($existing ? (int) $existing['id'] : null);
        }

        if ($existing) {
            $data = [
                'name'         => $name !== '' ? $name : 'Bot notifiche',
                'bot_username' => $username !== '' ? $username : null,
                'is_enabled'   => $isEnabled,
                'is_default'   => $isDefault,
            ];

            if ($token !== '') {
                $data['bot_token'] = $token;
            }

            if (empty($existing['webhook_secret'])) {
                $data['webhook_secret'] = bin2hex(random_bytes(16));
            }

            $this->botRepo->update((int) $existing['id'], $data);
            AuditService::log('notification_bot_updated', 'telegram_bot', (int) $existing['id']);
            return;
        }

        $botId = $this->botRepo->create([
            'name'           => $name !== '' ? $name : 'Bot notifiche',
            'bot_username'   => $username !== '' ? $username : null,
            'bot_token'      => $token,
            'webhook_secret' => bin2hex(random_bytes(16)),
            'is_enabled'     => $isEnabled,
            'is_default'     => $isDefault,
            'created_by'     => $createdBy > 0 ? $createdBy : null,
        ]);

        AuditService::log('notification_bot_created', 'telegram_bot', $botId);
    }

    public function validateBot(array $submitted): array
    {
        $errors = [];
        $botId = (int) ($submitted['bot_id'] ?? 0);
        $existing = $botId > 0 ? $this->botRepo->find($botId) : null;
        $name = trim((string) ($submitted['name'] ?? ''));
        $token = trim((string) ($submitted['bot_token'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Il nome del bot è obbligatorio.';
        }

        if (!$existing && $token === '') {
            $errors['bot_token'] = 'Il token del bot è obbligatorio per creare un nuovo bot.';
        }

        return $errors;
    }

    // ------------------------------------------------------------------
    // Queue management
    // ------------------------------------------------------------------

    /**
     * Retry a single failed queue item.
     */
    public function retryQueueItem(int $queueId): bool
    {
        $deliveryId = $this->queueRepo->getDeliveryId($queueId);
        $reset = $this->queueRepo->resetToRetry($queueId);

        if ($reset && $deliveryId !== null) {
            $this->deliveryRepo->resetToPending($deliveryId);
        }

        if ($reset) {
            AuditService::log('notification_queue_retry', 'notification_queue', $queueId);
        }

        return $reset;
    }

    /**
     * Retry all failed queue items.
     *
     * @return int Number of items retried.
     */
    public function retryAllFailed(): int
    {
        $count = $this->queueRepo->resetAllFailedToRetry();

        if ($count > 0) {
            AuditService::log('notification_queue_retry_all', 'notification_queue', null, null, [
                'count' => $count,
            ]);
        }

        return $count;
    }

    /**
     * Elenco admin attivi destinatari delle simulazioni evento.
     *
     * @return array<int, array{id:int,name:string,email:string}>
     */
    public function getActiveAdminRecipients(): array
    {
        $pdo = app(PDO::class);

        $stmt = $pdo->query(
            "SELECT DISTINCT u.id, u.name, u.email
             FROM users u
             INNER JOIN user_role ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.is_active = 1
               AND u.deleted_at IS NULL
               AND r.slug = 'admin'
             ORDER BY u.name ASC"
        );

        return $stmt->fetchAll() ?: [];
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private static function normalizeNullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<int, array<string, mixed>> $modules
     * @return array<string, array<string, string>>
     */
    private function buildContextVariableMap(array $modules): array
    {
        $map = [];
        foreach ($modules as $module) {
            foreach (($module['events'] ?? []) as $event) {
                $map[$event['slug']] = is_array($event['context_variables'] ?? null)
                    ? $event['context_variables']
                    : [];
            }
        }

        return $map;
    }

    private function enrichBot(?array $bot): ?array
    {
        if (!$bot) {
            return null;
        }

        $baseUrl = rtrim((string) config('app.url', ''), '/');
        $basePath = rtrim((string) config('app.base_path', ''), '/');
        $bot['webhook_url'] = $baseUrl . $basePath . '/notifications/telegram/webhook/' . rawurlencode((string) ($bot['webhook_secret'] ?? ''));
        $bot['deep_link_base'] = !empty($bot['bot_username']) ? 'https://t.me/' . $bot['bot_username'] . '?start=' : null;
        return $bot;
    }
}
