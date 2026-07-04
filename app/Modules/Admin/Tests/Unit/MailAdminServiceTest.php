<?php

namespace App\Modules\Admin\Tests\Unit;

use App\Modules\Admin\Services\MailAdminService;
use App\Repositories\MailLogRepository;
use App\Repositories\MailTemplateRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

class MailAdminServiceTest extends TestCase
{
    use MakesContainer;

    private MailTemplateRepository $templateRepo;

    private function service(): MailAdminService
    {
        $this->freshContainer();
        $this->bindInstance(MailTemplateRepository::class, $this->templateRepo);
        $this->bindInstance(MailLogRepository::class, $this->createMock(MailLogRepository::class));
        return new MailAdminService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateRepo = $this->createMock(MailTemplateRepository::class);
    }

    /** @return array<string,string> */
    private function validData(): array
    {
        return [
            'slug' => 'benvenuto', 'name' => 'Benvenuto',
            'subject' => 'Ciao', 'body_html' => '<p>Ciao {{name}}</p>',
        ];
    }

    public function testValidTemplatePassesValidation(): void
    {
        $this->templateRepo->method('findBySlug')->willReturn(null);
        $this->assertSame([], $this->service()->validateTemplate($this->validData()));
    }

    public function testSlugIsRequiredAndFormatChecked(): void
    {
        $service = $this->service();
        $this->assertArrayHasKey('slug', $service->validateTemplate(['slug' => '']));
        $this->assertArrayHasKey('slug', $service->validateTemplate(['slug' => 'Non Valido!']));
    }

    public function testDuplicateSlugIsRejected(): void
    {
        $this->templateRepo->method('findBySlug')->willReturn(['id' => 99]);

        $errors = $this->service()->validateTemplate($this->validData());
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testDuplicateSlugAllowedWhenSameExcludedId(): void
    {
        $this->templateRepo->method('findBySlug')->willReturn(['id' => 5]);

        // Stesso record in update (excludeId = 5) → nessun errore di slug.
        $errors = $this->service()->validateTemplate($this->validData(), 5);
        $this->assertArrayNotHasKey('slug', $errors);
    }

    public function testRequiredFieldsAreValidated(): void
    {
        $this->templateRepo->method('findBySlug')->willReturn(null);

        $errors = $this->service()->validateTemplate(['slug' => 'ok']);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('subject', $errors);
        $this->assertArrayHasKey('body_html', $errors);
    }

    public function testBodyHtmlRejectsScriptTagAndEventHandlers(): void
    {
        $this->templateRepo->method('findBySlug')->willReturn(null);
        $service = $this->service();

        $withScript = $this->validData();
        $withScript['body_html'] = '<p>x</p><script>alert(1)</script>';
        $this->assertArrayHasKey('body_html', $service->validateTemplate($withScript));

        $withHandler = $this->validData();
        $withHandler['body_html'] = '<div onclick="x()">y</div>';
        $this->assertArrayHasKey('body_html', $service->validateTemplate($withHandler));
    }
}
