<?php

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Repositories\RecurrencesRepository;
use App\Modules\Contacts\Services\RecurrencesService;
use PHPUnit\Framework\TestCase;

class RecurrencesServiceTest extends TestCase
{
    private function service(RecurrencesRepository $repo): RecurrencesService
    {
        return new RecurrencesService($repo);
    }

    public function testCreateNormalizesCheckboxAndNumericFields(): void
    {
        $repo = $this->createMock(RecurrencesRepository::class);
        $repo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['contatto_id'] === 10
                    && $data['user_id'] === 3
                    && $data['annuale'] === 1                       // checkbox presente → 1
                    && $data['notifica_giorno_stesso'] === 0        // assente → 0
                    && $data['promemoria_giorni_prima'] === 0       // negativo → clamp a 0
                    && $data['anno_riferimento'] === null;          // vuoto → null
            }))
            ->willReturn(42);

        $id = $this->service($repo)->create([
            'annuale' => 'on',
            'promemoria_giorni_prima' => -5,
            'anno_riferimento' => '',
        ], 10, 3);

        $this->assertSame(42, $id);
    }

    public function testCalcolaProssimaDataNonAnnualReturnsDateOnlyIfFuture(): void
    {
        $service = $this->service($this->createMock(RecurrencesRepository::class));
        $today = new \DateTime('2026-06-20');

        $future = $service->calcolaProssimaData(
            ['data_ricorrenza' => '2026-08-01', 'annuale' => 0],
            $today
        );
        $this->assertSame('2026-08-01', $future->format('Y-m-d'));

        $past = $service->calcolaProssimaData(
            ['data_ricorrenza' => '2026-01-01', 'annuale' => 0],
            $today
        );
        $this->assertNull($past);
    }

    public function testCalcolaProssimaDataAnnualRollsToNextYearWhenPassed(): void
    {
        $service = $this->service($this->createMock(RecurrencesRepository::class));
        $today = new \DateTime('2026-06-20');

        // Ricorrenza annuale già passata quest'anno → anno prossimo.
        $passed = $service->calcolaProssimaData(
            ['data_ricorrenza' => '1990-03-15', 'annuale' => 1],
            $today
        );
        $this->assertSame('2027-03-15', $passed->format('Y-m-d'));

        // Ricorrenza annuale futura quest'anno → quest'anno.
        $upcoming = $service->calcolaProssimaData(
            ['data_ricorrenza' => '1990-12-25', 'annuale' => 1],
            $today
        );
        $this->assertSame('2026-12-25', $upcoming->format('Y-m-d'));
    }

    public function testCalcolaProssimaDataReturnsNullForInvalidDate(): void
    {
        $service = $this->service($this->createMock(RecurrencesRepository::class));
        $this->assertNull($service->calcolaProssimaData(
            ['data_ricorrenza' => 'non-una-data', 'annuale' => 1],
            new \DateTime('2026-06-20')
        ));
    }

    public function testDeleteDelegatesToRepository(): void
    {
        $repo = $this->createMock(RecurrencesRepository::class);
        $repo->expects($this->once())->method('delete')->with(7)->willReturn(true);

        $this->assertTrue($this->service($repo)->delete(7));
    }
}
