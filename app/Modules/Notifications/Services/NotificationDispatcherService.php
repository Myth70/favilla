<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationDeliveryRepository;
use App\Modules\Notifications\Repositories\NotificationDispatchRepository;
use App\Modules\Notifications\Repositories\NotificationEventRepository;
use App\Modules\Notifications\Repositories\NotificationPreferenceRepository;
use App\Modules\Notifications\Repositories\NotificationQueueRepository;
use PDO;

class NotificationDispatcherService
{
    private NotificationEventRepository $eventRepo;
    private NotificationPreferenceRepository $preferenceRepo;
    private NotificationDispatchRepository $dispatchRepo;
    private NotificationDeliveryRepository $deliveryRepo;
    private NotificationQueueRepository $queueRepo;
    private NotificationEventRegistryService $registryService;
    private PDO $pdo;

    /** @var array<string, NotificationChannelDriverInterface> */
    private array $drivers;
    /** @var array<int, string> */
    private array $userNameCache = [];

    public function __construct()
    {
        $this->eventRepo = app(NotificationEventRepository::class);
        $this->preferenceRepo = app(NotificationPreferenceRepository::class);
        $this->dispatchRepo = app(NotificationDispatchRepository::class);
        $this->deliveryRepo = app(NotificationDeliveryRepository::class);
        $this->queueRepo = app(NotificationQueueRepository::class);
        $this->registryService = app(NotificationEventRegistryService::class);
        $this->pdo = app(PDO::class);
        $this->drivers = [
            'in_app'   => app(InAppChannelDriver::class),
            'email'    => app(EmailChannelDriver::class),
            'telegram' => app(TelegramChannelDriver::class),
            'web_push' => app(WebPushChannelDriver::class),
        ];
    }

    /**
     * @param int[] $userIds
     * @param array<string, mixed> $eventMeta
     * @param array<string, mixed> $message
     * @return array{dispatch_id:int, legacy_notification_ids:int[], delivery_ids:int[]}
     */
    public function dispatchEventToUsers(
        string $eventSlug,
        string $sourceModule,
        array $userIds,
        array $eventMeta,
        array $message,
        ?int $fromUserId = null,
        bool $bypassPreferences = false
    ): array {
        $moduleSlug = $this->normalizeModuleSlug($sourceModule);
        $registryMeta = $this->registryService->getStaticEventDefinitions()[$eventSlug] ?? [];
        $resolvedMeta = array_merge($registryMeta, $eventMeta);

        $event = $this->eventRepo->findOrCreateEvent(
            $eventSlug,
            $moduleSlug,
            (string) ($resolvedMeta['name'] ?? $eventSlug),
            isset($resolvedMeta['description']) ? (string) $resolvedMeta['description'] : null,
            $this->normalizeType((string) ($resolvedMeta['default_level'] ?? ($message['type'] ?? 'info'))),
            $this->normalizeNullableVisual((string) ($resolvedMeta['icon'] ?? ($message['icon'] ?? ''))),
            $this->normalizeNullableVisual((string) ($resolvedMeta['color'] ?? ($message['color'] ?? ''))),
            (bool) ($resolvedMeta['is_system'] ?? false),
            (string) ($resolvedMeta['source'] ?? 'dynamic'),
            $resolvedMeta['context_variables'] ?? null
        );

        $this->ensureDefaultBindingsFromRegistry($event, $resolvedMeta);

        return $this->dispatchToUsers(
            $event['slug'],
            $sourceModule,
            $userIds,
            [
                'title'   => (string) ($message['title'] ?? ''),
                'body'    => (string) ($message['body'] ?? ''),
                'type'    => (string) ($message['type'] ?? $event['default_level'] ?? 'info'),
                'link'    => $message['link'] ?? null,
                'icon'    => $message['icon'] ?? ($event['icon'] ?? null),
                'color'   => $message['color'] ?? ($event['color'] ?? null),
                'context' => is_array($message['context'] ?? null) ? $message['context'] : [],
            ],
            $fromUserId,
            $bypassPreferences,
            null
        );
    }

    /**
     * @param array<string, mixed> $eventMeta
     * @param array<string, mixed> $message
     * @return array{dispatch_id:int, legacy_notification_ids:int[], delivery_ids:int[]}
     */
    public function dispatchEventToRole(
        string $eventSlug,
        string $sourceModule,
        string $roleSlug,
        array $eventMeta,
        array $message,
        ?int $fromUserId = null,
        bool $bypassPreferences = false
    ): array {
        $userIds = $this->getActiveUserIdsByRole($roleSlug);
        $result = $this->dispatchEventToUsers(
            $eventSlug,
            $sourceModule,
            $userIds,
            $eventMeta,
            $message,
            $fromUserId,
            $bypassPreferences
        );

        $stmt = $this->pdo->prepare('UPDATE notification_dispatches SET recipient_role_slug = ? WHERE id = ?');
        $stmt->execute([$roleSlug, $result['dispatch_id']]);

        return $result;
    }

    /**
     * @param int[] $userIds
     * @param array{title:string,body:string,type:string,link:?string,icon:?string,context:array<string,mixed>} $message
     * @return array{dispatch_id:int, legacy_notification_ids:int[], delivery_ids:int[]}
     */
    private function dispatchToUsers(
        string $eventSlug,
        string $sourceModule,
        array $userIds,
        array $message,
        ?int $fromUserId,
        bool $bypassPreferences,
        ?string $roleSlug
    ): array {
        $message['from_user_id'] = $fromUserId;
        $message = $this->resolveEventVisualMessage($eventSlug, $message);
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id) => $id > 0)));
        $dispatchId = $this->dispatchRepo->createDispatch([
            'event_slug'         => $eventSlug,
            'source_module'      => $sourceModule,
            'recipient_user_id'  => count($userIds) === 1 ? $userIds[0] : null,
            'recipient_role_slug' => $roleSlug,
            'title'              => $message['title'],
            'body'               => $message['body'] !== '' ? $message['body'] : null,
            'type'               => $this->normalizeType($message['type']),
            'link'               => $message['link'],
            'icon'               => $message['icon'],
            'color'              => $this->normalizeNullableVisual($message['color'] ?? null),
            'payload_json'       => !empty($message['context']) ? json_encode($message['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_by'         => $fromUserId,
            'bypass_preferences' => $bypassPreferences ? 1 : 0,
            'status'             => empty($userIds) ? 'failed' : 'pending',
            'total_recipients'   => count($userIds),
            'total_deliveries'   => 0,
        ]);

        $bindings = $this->eventRepo->getChannelBindingsForEvent($eventSlug);
        if (empty($bindings)) {
            $event = $this->eventRepo->findBySlug($eventSlug);
            if ($event) {
                $this->eventRepo->ensureDefaultBindings((int) $event['id']);
                $bindings = $this->eventRepo->getChannelBindingsForEvent($eventSlug);
            }
        }

        $deliveryIds = [];
        $legacyNotificationIds = [];
        $moduleSlug = $this->normalizeModuleSlug($sourceModule);

        foreach ($userIds as $userId) {
            $resolvedBindings = $bypassPreferences
                ? $this->forceBindingsEnabled($bindings)
                : $this->preferenceRepo->resolveChannelBindings($userId, $moduleSlug, $eventSlug, $bindings);

            foreach ($resolvedBindings as $binding) {
                if (empty($binding['is_enabled']) || empty($binding['channel_active'])) {
                    continue;
                }

                $delivery = $this->buildDeliveryPayload($dispatchId, $userId, $binding, $message);
                $deliveryId = $this->deliveryRepo->createDelivery($delivery);
                $deliveryIds[] = $deliveryId;

                if (empty($binding['resolved_enabled'])) {
                    $this->deliveryRepo->markSkipped($deliveryId, 'Canale disabilitato dalle preferenze utente.');
                    continue;
                }

                if ($binding['channel_slug'] === 'in_app') {
                    $job = $this->deliveryRepo->getDriverPayload($deliveryId);
                    if ($job === null) {
                        $this->deliveryRepo->markFailed($deliveryId, 'Payload notifica in-app non disponibile.');
                        continue;
                    }

                    $result = $this->drivers['in_app']->send($job);
                    if ($result['status'] === 'sent') {
                        $this->deliveryRepo->markSent($deliveryId, $result['provider_message_id'] ?? null, null);
                        if (!empty($result['provider_message_id'])) {
                            $legacyNotificationIds[] = (int) $result['provider_message_id'];
                        }
                    } elseif ($result['status'] === 'skipped') {
                        $this->deliveryRepo->markSkipped($deliveryId, (string) ($result['error_message'] ?? 'Consegna saltata.'));
                    } else {
                        $this->deliveryRepo->markFailed($deliveryId, (string) ($result['error_message'] ?? 'Invio in-app fallito.'));
                    }
                    continue;
                }

                $this->queueRepo->enqueue($deliveryId, $binding['channel_slug']);
                $this->updateDeliveryStatus($deliveryId, 'queued', null);
            }
        }

        $this->dispatchRepo->refreshStatus($dispatchId);

        return [
            'dispatch_id' => $dispatchId,
            'legacy_notification_ids' => $legacyNotificationIds,
            'delivery_ids' => $deliveryIds,
        ];
    }

    private function buildDeliveryPayload(int $dispatchId, int $userId, array $binding, array $message): array
    {
        $content = $this->renderBindingContent($binding, $message, $userId);

        return [
            'dispatch_id' => $dispatchId,
            'user_id'     => $userId,
            'channel_slug' => $binding['channel_slug'],
            'status'      => $binding['channel_slug'] === 'in_app' ? 'pending' : 'queued',
            'subject'     => $content['subject'],
            'body'        => $content['body'],
            'link'        => $message['link'],
            'icon'        => $this->normalizeNullableVisual(($message['icon'] ?? null) ?: ($binding['default_icon'] ?? null)),
            'color'       => $this->normalizeNullableVisual(($binding['default_color'] ?? null) ?: ($message['color'] ?? null)),
            'provider_message_id' => null,
            'error_message' => null,
            'attempts'    => 0,
            'sent_at'     => null,
        ];
    }

    /**
     * @param array<string, mixed> $binding
     * @param array<string, mixed> $message
     * @return array{subject:?string, body:?string}
     */
    private function renderBindingContent(array $binding, array $message, int $recipientUserId): array
    {
        $now = new \DateTimeImmutable('now');
        $senderUserId = isset($message['from_user_id']) ? (int) $message['from_user_id'] : null;

        $baseVars = [
            'title'               => (string) ($message['title'] ?? ''),
            'body'                => (string) ($message['body'] ?? ''),
            'type'                => (string) ($message['type'] ?? 'info'),
            'link'                => (string) ($message['link'] ?? ''),
            'date'                => $now->format('Y-m-d'),
            'time'                => $now->format('H:i:s'),
            'datetime'            => $now->format('Y-m-d H:i:s'),
            'date_it'             => $now->format('d/m/Y'),
            'time_it'             => $now->format('H:i'),
            'year'                => $now->format('Y'),
            'month'               => $now->format('m'),
            'day'                 => $now->format('d'),
            'weekday'             => $now->format('N'),
            'timestamp'           => (string) $now->getTimestamp(),
            'module_slug'         => (string) ($binding['module_slug'] ?? ''),
            'event_slug'          => (string) ($binding['event_slug'] ?? ''),
            'channel_slug'        => (string) ($binding['channel_slug'] ?? ''),
            'recipient_user_id'   => (string) $recipientUserId,
            'recipient_user_name' => $this->getUserNameById($recipientUserId),
            'user_id'             => (string) $recipientUserId,
            'user_name'           => $this->getUserNameById($recipientUserId),
            'user'                => $this->getUserNameById($recipientUserId),
            'sender_user_id'      => $senderUserId !== null ? (string) $senderUserId : '',
            'sender_user_name'    => $senderUserId !== null ? $this->getUserNameById($senderUserId) : '',
        ];

        $vars = array_merge(
            $baseVars,
            is_array($message['context'] ?? null) ? $message['context'] : []
        );

        $subjectTemplate = $this->normalizeNullableTemplate($binding['subject_template'] ?? null);
        $bodyTemplate = $this->normalizeNullableTemplate($binding['body_template'] ?? null);

        $subject = $subjectTemplate
            ? $this->renderTemplate($subjectTemplate, $vars)
            : (string) ($message['title'] ?? '');

        if ($bodyTemplate !== null) {
            $body = $this->renderTemplate($bodyTemplate, $vars);
        } elseif ($binding['channel_slug'] === 'email') {
            $body = null;
        } elseif ($binding['channel_slug'] === 'telegram') {
            $body = trim((string) ($message['body'] ?? ''));
        } else {
            $body = (string) ($message['body'] ?? '');
        }

        return [
            'subject' => $subject !== '' ? $subject : null,
            'body'    => $body !== '' ? $body : null,
        ];
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function resolveEventVisualMessage(string $eventSlug, array $message): array
    {
        $event = $this->eventRepo->findBySlug($eventSlug);
        if (!$event) {
            $message['type'] = $this->normalizeType((string) ($message['type'] ?? 'info'));
            $message['icon'] = $this->normalizeNullableVisual($message['icon'] ?? null);
            $message['color'] = $this->normalizeNullableVisual($message['color'] ?? null);
            return $message;
        }

        // Message values take priority when explicitly set; event DB values are defaults
        $message['type'] = $this->normalizeType((string) ($message['type'] ?? $event['default_level'] ?? 'info'));
        $message['icon'] = $this->normalizeNullableVisual($message['icon'] ?? null)
            ?? $this->normalizeNullableVisual($event['icon'] ?? null);
        $message['color'] = $this->normalizeNullableVisual($message['color'] ?? null)
            ?? $this->normalizeNullableVisual($event['color'] ?? null);

        return $message;
    }

    private function renderTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace('{{' . $key . '}}', (string) $value, $template);
            }
        }

        return $template;
    }

    /**
     * Ensure first runtime dispatch applies module.json default templates
     * even before admin/preferences screens have synced the event catalog.
     * Existing non-empty bindings are preserved.
     *
     * @param array<string, mixed> $event
     * @param array<string, mixed> $resolvedMeta
     */
    private function ensureDefaultBindingsFromRegistry(array $event, array $resolvedMeta): void
    {
        $defaultTemplates = is_array($resolvedMeta['default_templates'] ?? null)
            ? $resolvedMeta['default_templates']
            : [];

        if (empty($defaultTemplates) || empty($event['id']) || empty($event['slug'])) {
            return;
        }

        $bindings = [];
        foreach ($this->eventRepo->getChannelBindingsForEvent((string) $event['slug']) as $binding) {
            $bindings[(string) $binding['channel_slug']] = $binding;
        }

        foreach (['in_app', 'email', 'telegram'] as $channelSlug) {
            $template = is_array($defaultTemplates[$channelSlug] ?? null) ? $defaultTemplates[$channelSlug] : [];
            $defaultSubject = $this->normalizeNullableTemplate($template['subject'] ?? null);
            $defaultBody = $this->normalizeNullableTemplate($template['body'] ?? null);
            $existing = $bindings[$channelSlug] ?? null;

            if ($existing === null) {
                $this->eventRepo->upsertChannelBinding(
                    (int) $event['id'],
                    $channelSlug,
                    $channelSlug === 'in_app',
                    $defaultSubject,
                    $defaultBody,
                    null
                );
                continue;
            }

            $existingSubject = $this->normalizeNullableTemplate($existing['subject_template'] ?? null);
            $existingBody = $this->normalizeNullableTemplate($existing['body_template'] ?? null);
            $subject = $existingSubject ?? $defaultSubject;
            $body = $existingBody ?? $defaultBody;

            if ($subject === $existingSubject && $body === $existingBody) {
                continue;
            }

            $this->eventRepo->upsertChannelBinding(
                (int) $event['id'],
                $channelSlug,
                (bool) ($existing['is_enabled'] ?? false),
                $subject,
                $body,
                $this->normalizeNullableTemplate($existing['layout_config'] ?? null)
            );
        }
    }

    private function updateDeliveryStatus(int $deliveryId, string $status, ?string $errorMessage): void
    {
        if ($status === 'queued') {
            $stmt = $this->pdo->prepare('UPDATE notification_deliveries SET status = ? WHERE id = ?');
            $stmt->execute([$status, $deliveryId]);
            return;
        }

        if ($status === 'skipped') {
            $this->deliveryRepo->markSkipped($deliveryId, $errorMessage ?? 'Consegna saltata.');
            return;
        }

        $this->deliveryRepo->markFailed($deliveryId, $errorMessage ?? 'Consegna fallita.');
    }

    private function normalizeModuleSlug(string $moduleName): string
    {
        $normalized = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $moduleName) ?? $moduleName;
        $normalized = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $normalized) ?? $normalized;
        $normalized = strtolower(str_replace(['-', ' '], '_', $normalized));
        return trim($normalized, '_') !== '' ? trim($normalized, '_') : 'system';
    }

    private function normalizeType(string $type): string
    {
        $allowed = ['info', 'success', 'warning', 'danger'];
        return in_array($type, $allowed, true) ? $type : 'info';
    }

    private function normalizeNullableTemplate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableVisual(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param array<int, array<string,mixed>> $bindings
     * @return array<int, array<string,mixed>>
     */
    private function forceBindingsEnabled(array $bindings): array
    {
        foreach ($bindings as &$binding) {
            $binding['resolved_enabled'] = (bool) $binding['is_enabled'] && (bool) $binding['channel_active'];
        }
        unset($binding);

        return $bindings;
    }

    /**
     * @return int[]
     */
    private function getActiveUserIdsByRole(string $roleSlug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id
             FROM users u
             JOIN user_role ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE r.slug = ? AND u.is_active = 1 AND u.deleted_at IS NULL'
        );
        $stmt->execute([$roleSlug]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $rows ?: []);
    }

    private function getUserNameById(?int $userId): string
    {
        if ($userId === null || $userId <= 0) {
            return '';
        }

        if (array_key_exists($userId, $this->userNameCache)) {
            return $this->userNameCache[$userId];
        }

        $stmt = $this->pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $name = (string) ($stmt->fetchColumn() ?: '');
        $this->userNameCache[$userId] = $name;

        return $name;
    }
}
