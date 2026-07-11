<?php

declare(strict_types=1);

namespace App\Modules\Api\Tests\Unit;

use App\Exceptions\HttpException;
use App\Modules\Api\Middleware\ApiRateLimitMiddleware;
use App\Modules\Api\Support\ApiRequestContext;
use App\Services\SettingsService;
use Tests\ModuleTestCase;
use Tests\Support\MakesContainer;

/**
 * Rate limiter API v1: entro il limite passa, oltre il limite lancia 429; il
 * conteggio è per-token e la registrazione avviene PRIMA del conteggio (nessuna
 * finestra di race COUNT-poi-INSERT). Il limite è forzato a 3/min via cache
 * SettingsService.
 */
class ApiRateLimitMiddlewareTest extends ModuleTestCase
{
    use MakesContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrate('
            CREATE TABLE rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rate_key TEXT NOT NULL, endpoint TEXT NOT NULL, ip_address TEXT NOT NULL,
                user_id INTEGER NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
        $this->primeRateLimit(3);

        $context = new ApiRequestContext();
        $context->authenticate(7, ['id' => 7], ['user'], ['tasks.view'], ['tasks.view'], 42);
        $this->bindInstance(ApiRequestContext::class, $context);
    }

    protected function tearDown(): void
    {
        $this->resetSettingsCache();
        parent::tearDown();
    }

    private function primeRateLimit(int $perMinute): void
    {
        $prop = (new \ReflectionClass(SettingsService::class))->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'api_rate_limit_per_minute' => ['value' => (string) $perMinute, 'type' => 'integer'],
        ]);
    }

    private function resetSettingsCache(): void
    {
        $prop = (new \ReflectionClass(SettingsService::class))->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testAllowsUpToLimitThenReturns429(): void
    {
        $mw = new ApiRateLimitMiddleware();
        $calls = 0;
        $next = function () use (&$calls): void {
            $calls++;
        };

        // 3 richieste entro il limite: passano.
        $mw->handle($next);
        $mw->handle($next);
        $mw->handle($next);
        $this->assertSame(3, $calls);

        // La 4ª supera il limite: 429 e $next NON eseguito.
        try {
            $mw->handle($next);
            $this->fail('La 4ª richiesta doveva lanciare 429');
        } catch (HttpException $e) {
            $this->assertSame(429, $e->getStatusCode());
        }
        $this->assertSame(3, $calls, '$next non deve essere chiamato quando si è rate-limited');
    }

    public function testCountIsPerToken(): void
    {
        $mw = new ApiRateLimitMiddleware();
        $noop = static function (): void {};

        // Esaurisce il budget del token 42.
        $mw->handle($noop);
        $mw->handle($noop);
        $mw->handle($noop);

        // Un token diverso ha un budget indipendente: la prima richiesta passa.
        $other = new ApiRequestContext();
        $other->authenticate(8, ['id' => 8], ['user'], ['tasks.view'], ['tasks.view'], 99);
        $this->bindInstance(ApiRequestContext::class, $other);

        $called = false;
        $mw->handle(function () use (&$called): void {
            $called = true;
        });
        $this->assertTrue($called, 'il budget è per-token, non globale');
    }
}
