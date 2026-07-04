<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Controllers\CategoriesController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for the contact-categories JSON endpoints via the
 * HTTP harness. Validation rejects respond with json() before any DB write.
 */
class CategoriesControllerTest extends ControllerTestCase
{
    public function testStoreRejectsEmptyName(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['nome' => '', 'colore' => '#112233'])
            ->dispatch(CategoriesController::class, 'store');

        $this->assertTrue($result->isJson());
        $this->assertSame(422, $result->jsonStatus());
        $this->assertArrayHasKey('error', $result->jsonPayload());
    }

    public function testStoreRejectsInvalidColor(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['nome' => 'Lavoro', 'colore' => 'notacolor'])
            ->dispatch(CategoriesController::class, 'store');

        $this->assertTrue($result->isJson());
        $this->assertSame(422, $result->jsonStatus());
        $this->assertArrayHasKey('error', $result->jsonPayload());
    }

    public function testUpdateRejectsEmptyName(): void
    {
        $this->actingAs(1);

        $result = $this->withPost(['nome' => '', 'colore' => '#112233'])
            ->dispatch(CategoriesController::class, 'update', ['9']);

        $this->assertTrue($result->isJson());
        $this->assertSame(422, $result->jsonStatus());
    }
}
