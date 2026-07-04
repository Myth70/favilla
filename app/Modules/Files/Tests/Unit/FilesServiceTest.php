<?php

namespace App\Modules\Files\Tests\Unit;

use App\Modules\Files\Services\FilesService;
use PHPUnit\Framework\TestCase;

class FilesServiceTest extends TestCase
{
    private FilesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new class () extends FilesService {
            public function __construct()
            {
            }
        };
    }

    public function testNormalizeTagsRemovesEmptyAndDuplicates(): void
    {
        $result = $this->service->normalizeTags(' alpha, beta, alpha, , gamma ');
        $this->assertSame('alpha, beta, gamma', $result);
    }

    public function testNormalizeTagsReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->service->normalizeTags(' , , '));
    }

    public function testSanitizeFolderRemovesTraversalAndNormalizesSlashes(): void
    {
        $result = $this->service->sanitizeFolder(' /team\\..\\docs//q1/ ');
        $this->assertSame('team/docs/q1', $result);
    }

    public function testHumanSizeFormatsMegabytes(): void
    {
        $this->assertSame('2 MB', FilesService::humanSize(2097152));
    }

    public function testIconClassDetectsPdf(): void
    {
        $this->assertSame('fa-file-pdf fm-icon-pdf', FilesService::iconClass('pdf', 'application/pdf'));
    }

    public function testBuildRestorePayloadNormalizesVersionData(): void
    {
        $payload = $this->service->buildRestorePayload([
            'original_name' => 'report.pdf',
            'stored_name' => 'file_abc.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => '12345',
            'checksum_sha256' => 'abc123',
        ]);

        $this->assertSame('report.pdf', $payload['original_name']);
        $this->assertSame('file_abc.pdf', $payload['stored_name']);
        $this->assertSame('application/pdf', $payload['mime_type']);
        $this->assertSame('pdf', $payload['extension']);
        $this->assertSame(12345, $payload['size_bytes']);
        $this->assertSame('abc123', $payload['checksum_sha256']);
    }
}
