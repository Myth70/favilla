<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Repositories\NotificationDeliveryRepository;
use App\Modules\Notifications\Repositories\NotificationDispatchRepository;
use App\Modules\Notifications\Repositories\NotificationQueueRepository;

class NotificationQueueProcessorService
{
    private NotificationQueueRepository $queueRepo;
    private NotificationDeliveryRepository $deliveryRepo;
    private NotificationDispatchRepository $dispatchRepo;

    /** @var array<string, NotificationChannelDriverInterface> */
    private array $drivers;

    public function __construct()
    {
        $this->queueRepo = app(NotificationQueueRepository::class);
        $this->deliveryRepo = app(NotificationDeliveryRepository::class);
        $this->dispatchRepo = app(NotificationDispatchRepository::class);
        $this->drivers = [
            'email'    => app(EmailChannelDriver::class),
            'telegram' => app(TelegramChannelDriver::class),
        ];
    }

    /**
     * @return array{processed:int,sent:int,failed:int,skipped:int,released:int}
     */
    public function process(int $limit = 25, ?string $channel = null): array
    {
        $jobs = $this->queueRepo->claimPending($limit, $channel);
        $stats = [
            'processed' => 0,
            'sent'      => 0,
            'failed'    => 0,
            'skipped'   => 0,
            'released'  => 0,
        ];

        foreach ($jobs as $job) {
            $stats['processed']++;
            $queueId = (int) $job['queue_id'];
            $deliveryId = (int) $job['delivery_id'];
            $attempts = (int) $job['queue_attempts'];
            $maxAttempts = (int) $job['max_attempts'];
            $channelSlug = (string) $job['channel_slug'];

            $this->deliveryRepo->markProcessing($deliveryId, $attempts);
            $driver = $this->drivers[$channelSlug] ?? null;

            if ($driver === null) {
                $error = 'Driver canale non disponibile: ' . $channelSlug;
                $this->deliveryRepo->markFailed($deliveryId, $error);
                $this->queueRepo->markFailed($queueId, $error);
                $this->dispatchRepo->refreshStatus((int) $job['dispatch_id']);
                $stats['failed']++;
                continue;
            }

            $result = $driver->send($job);
            $status = (string) ($result['status'] ?? 'failed');
            $errorMessage = (string) ($result['error_message'] ?? 'Invio fallito.');

            if ($status === 'sent') {
                $this->deliveryRepo->markSent($deliveryId, $result['provider_message_id'] ?? null, null);
                $this->queueRepo->markSent($queueId);
                $stats['sent']++;
            } elseif ($status === 'skipped') {
                $this->deliveryRepo->markSkipped($deliveryId, $errorMessage);
                $this->queueRepo->markSkipped($queueId, $errorMessage);
                $stats['skipped']++;
            } elseif ($attempts < $maxAttempts) {
                $this->deliveryRepo->markFailed($deliveryId, $errorMessage);
                $this->queueRepo->releaseForRetry($queueId, $attempts, $errorMessage);
                $stats['released']++;
            } else {
                $this->deliveryRepo->markFailed($deliveryId, $errorMessage);
                $this->queueRepo->markFailed($queueId, $errorMessage);
                $stats['failed']++;
            }

            $this->dispatchRepo->refreshStatus((int) $job['dispatch_id']);
        }

        return $stats;
    }
}
