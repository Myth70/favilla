<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\CreatesTempFiles;

/**
 * Upload validation invariant (SECURITY.md): the stored MIME is decided from the
 * file's magic bytes, never from the browser-supplied name/type. A payload that
 * lies about its type (e.g. PHP/text content named .jpg/.pdf) must be rejected
 * before it is ever moved into the uploads directory.
 */
class UploadMagicByteTest extends TestCase
{
    use CreatesTempFiles;

    protected function tearDown(): void
    {
        $this->cleanupTempFiles();
        parent::tearDown();
    }

    private function tmpWith(string $content): string
    {
        return $this->makeTempFile($content, 'upltest_');
    }

    public function testImageUploadRejectsPhpPayloadNamedJpg(): void
    {
        $tmp = $this->tmpWith("<?php echo 'pwned'; ?>\n");
        $file = [
            'error'    => UPLOAD_ERR_OK,
            'size'     => 22,
            'tmp_name' => $tmp,
            'name'     => 'evil.jpg', // lies — content is not an image
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Formato non supportato');
        FileUploadService::uploadImage($file, 'profiles', 'test_');
    }

    public function testGenericUploadRejectsTextNamedPdf(): void
    {
        $tmp = $this->tmpWith('this is plain text, not a PDF document');
        $file = [
            'error'    => UPLOAD_ERR_OK,
            'size'     => 38,
            'tmp_name' => $tmp,
            'name'     => 'fake.pdf',
        ];

        $this->expectException(RuntimeException::class);
        FileUploadService::uploadFile($file, 'files', 'file_', 20971520, ['application/pdf']);
    }
}
