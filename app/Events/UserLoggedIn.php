<?php

declare(strict_types=1);

namespace App\Events;

class UserLoggedIn
{
    public const NAME = 'user.logged_in';

    public function __construct(
        public readonly int $userId,
        public readonly string $ip,
        public readonly string $userAgent
    ) {
    }
}
