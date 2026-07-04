<?php

declare(strict_types=1);

namespace App\Core\Testing;

/**
 * Test-only seam thrown by {@see \App\Core\Controller::redirect()} and
 * {@see \App\Core\Controller::json()} when the constant `FAVILLA_TESTING` is
 * defined (set in tests/bootstrap.php).
 *
 * In production those methods terminate the request with `exit`, which would
 * kill the PHPUnit process and make controller actions impossible to assert on.
 * Under test they throw this exception instead, so the test harness
 * ({@see \Tests\ControllerTestCase}) can catch it and inspect the terminal
 * response (a redirect target or a JSON payload).
 *
 * It is NEVER thrown in production: the `FAVILLA_TESTING` guard is only ever
 * defined by the test bootstrap.
 */
final class HaltResponse extends \RuntimeException
{
    public const REDIRECT = 'redirect';
    public const JSON     = 'json';

    /**
     * @param string                    $kind    self::REDIRECT | self::JSON
     * @param string|null               $url     redirect target (REDIRECT only)
     * @param int                       $status  HTTP status (JSON only)
     * @param array<array-key,mixed>|null $payload JSON body (JSON only)
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?string $url = null,
        public readonly int $status = 200,
        public readonly ?array $payload = null,
    ) {
        parent::__construct('Controller halt: ' . $kind);
    }
}
