<?php

declare(strict_types=1);

namespace App\Events;

class UserCreated
{
    public const NAME = 'user.created';

    public function __construct(
        public readonly int $userId,
        public readonly string $email
    ) {
    }
}
