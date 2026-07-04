<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Modules\Reports\Repositories\StylePresetRepository;
use App\Modules\Reports\Services\StyleService;
use PHPUnit\Framework\TestCase;
use Tests\Support\MakesContainer;

/**
 * create()/update() dipendono da FileUploadService e auth(): qui si testa la
 * logica decisionale di delete() (gestione "non trovato" / "in uso" / successo)
 * e i delegatori semplici, con StylePresetRepository mockato.
 */
class StyleServiceTest extends TestCase
{
    use MakesContainer;

    private function serviceWith(StylePresetRepository $repo): StyleService
    {
        $this->freshContainer();
        $this->bindInstance(StylePresetRepository::class, $repo);
        return new StyleService();
    }

    public function testDeleteReturnsMessageWhenNotFound(): void
    {
        $repo = $this->createMock(StylePresetRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->expects($this->never())->method('delete');

        $result = $this->serviceWith($repo)->delete(7);
        $this->assertSame('Stile non trovato.', $result);
    }

    public function testDeleteRefusesWhenStyleInUse(): void
    {
        $repo = $this->createMock(StylePresetRepository::class);
        $repo->method('find')->willReturn(['id' => 7]);
        $repo->method('countTemplatesUsing')->willReturn(3);
        $repo->expects($this->never())->method('delete');

        $result = $this->serviceWith($repo)->delete(7);
        $this->assertIsString($result);
        $this->assertStringContainsString('3 template', $result);
    }

    public function testDeleteSucceedsWhenNotInUse(): void
    {
        $repo = $this->createMock(StylePresetRepository::class);
        $repo->method('find')->willReturn(['id' => 7, 'logo_path' => null, 'logo_secondary_path' => null]);
        $repo->method('countTemplatesUsing')->willReturn(0);
        $repo->expects($this->once())->method('delete')->with(7)->willReturn(true);

        $this->assertTrue($this->serviceWith($repo)->delete(7));
    }

    public function testListAllAndFindDelegateToRepo(): void
    {
        $repo = $this->createMock(StylePresetRepository::class);
        $repo->method('listAll')->willReturn([['id' => 1]]);
        $repo->method('find')->with(1)->willReturn(['id' => 1]);

        $service = $this->serviceWith($repo);
        $this->assertSame([['id' => 1]], $service->listAll());
        $this->assertSame(['id' => 1], $service->find(1));
    }

    public function testSetDefaultDelegatesToRepo(): void
    {
        $repo = $this->createMock(StylePresetRepository::class);
        $repo->expects($this->once())->method('setDefault')->with(4);

        $this->serviceWith($repo)->setDefault(4);
    }
}
