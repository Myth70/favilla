<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\EnvWriterService;
use PHPUnit\Framework\TestCase;
use Tests\Support\CreatesTempFiles;

class EnvWriterServiceTest extends TestCase
{
    use CreatesTempFiles;

    private string $envFile;
    private EnvWriterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->envFile = $this->makeTempFile("APP_NAME=Favilla\nDB_HOST=localhost\n# commento\n", 'env_', '.env');
        $this->service = new EnvWriterService($this->envFile);
    }

    protected function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    public function testReadReturnsExistingValue(): void
    {
        $this->assertSame('Favilla', $this->service->read('APP_NAME'));
        $this->assertSame('localhost', $this->service->read('DB_HOST'));
    }

    public function testReadReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->service->read('NON_ESISTE'));
    }

    public function testReadReturnsNullWhenFileMissing(): void
    {
        $svc = new EnvWriterService($this->tempDir() . '/nope_' . uniqid() . '.env');
        $this->assertNull($svc->read('APP_NAME'));
    }

    public function testWriteUpdatesExistingKey(): void
    {
        $this->service->write('DB_HOST', '127.0.0.1');
        $this->assertSame('127.0.0.1', $this->service->read('DB_HOST'));
        // Le altre chiavi restano intatte.
        $this->assertSame('Favilla', $this->service->read('APP_NAME'));
    }

    public function testWriteAppendsNewKey(): void
    {
        $this->service->write('NEW_KEY', 'valore');
        $this->assertSame('valore', $this->service->read('NEW_KEY'));
    }

    public function testWriteQuotesValuesWithSpaces(): void
    {
        $this->service->write('TITLE', 'Hello World');

        $this->assertSame('Hello World', $this->service->read('TITLE'));
        $raw = file_get_contents($this->envFile);
        $this->assertStringContainsString('TITLE="Hello World"', $raw);
    }

    public function testWriteManyUpdatesMultipleKeys(): void
    {
        $this->service->writeMany(['APP_NAME' => 'Nuovo', 'EXTRA' => 'x']);

        $this->assertSame('Nuovo', $this->service->read('APP_NAME'));
        $this->assertSame('x', $this->service->read('EXTRA'));
    }

    public function testWriteThrowsWhenFileMissing(): void
    {
        $svc = new EnvWriterService($this->tempDir() . '/missing_' . uniqid() . '.env');
        $this->expectException(\RuntimeException::class);
        $svc->write('K', 'v');
    }
}
