<?php

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\NotificationModuleCatalogService;
use PHPUnit\Framework\TestCase;

class NotificationModuleCatalogServiceTest extends TestCase
{
    /**
     * @dataProvider moduleNameToSlugProvider
     */
    public function testModuleNameToSlugNormalizesDifferentFormats(string $name, string $expected): void
    {
        $this->assertSame($expected, NotificationModuleCatalogService::moduleNameToSlug($name));
    }

    public static function moduleNameToSlugProvider(): array
    {
        return [
            'PascalCase classico' => ['HealthCheck', 'health_check'],
            'acronimo maiuscolo' => ['GDPR', 'gdpr'],
            'camel + acronimo' => ['ApiREST', 'api_rest'],
            'con trattino' => ['Task-Runner', 'task_runner'],
            'con spazi' => ['Modulo Report', 'modulo_report'],
            'stringa vuota' => ['', 'system'],
        ];
    }
}
