<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Controllers\DocumentiController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Verifica M2: parseReminderGiorni applica range guard 1..365, dedup e sort decrescente.
 * Usa Reflection perché il metodo è privato (helper interno del controller).
 */
class ParseReminderGiorniTest extends TestCase
{
    private function call(string $csv): array
    {
        $controller = $this->getMockBuilder(DocumentiController::class)
            ->disableOriginalConstructor()
            ->getMock();
        $method = new ReflectionMethod(DocumentiController::class, 'parseReminderGiorni');
        $method->setAccessible(true);
        return $method->invoke($controller, $csv);
    }

    public function testValidCsvParsedSortedDescending(): void
    {
        $this->assertSame([30, 14, 7, 1], $this->call('1,7,14,30'));
    }

    public function testZeroAndNegativeAreFilteredOut(): void
    {
        $this->assertSame([7], $this->call('-1,0,7'));
    }

    public function testOver365IsFilteredOut(): void
    {
        $this->assertSame([365, 30], $this->call('30,365,400,9999'));
    }

    public function testDuplicatesAreDeduped(): void
    {
        $this->assertSame([14, 7], $this->call('7,7,14,14,7'));
    }

    public function testEmptyStringReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->call(''));
    }

    public function testNonNumericTokensAreFilteredOut(): void
    {
        $this->assertSame([30, 7], $this->call('7,abc,30,xyz'));
    }
}
