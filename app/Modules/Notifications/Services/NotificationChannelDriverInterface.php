<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

interface NotificationChannelDriverInterface
{
    public function channel(): string;

    /**
     * @param array<string, mixed> $job
     * @return array{status: string, provider_message_id?: string|null, error_message?: string|null}
     */
    public function send(array $job): array;
}
