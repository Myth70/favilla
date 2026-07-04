<?php

declare(strict_types=1);

namespace App\Modules\Home\Tests\Unit;

use App\Modules\Home\Controllers\ModuleFallbackController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for ModuleFallbackController: the disabled-module
 * fallback always flashes an error and redirects to the home index.
 */
class ModuleFallbackControllerTest extends ControllerTestCase
{
    public function testRedirectsToHomeWithErrorForKnownModulePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/files/123';

        $result = $this->dispatch(ModuleFallbackController::class, 'redirectToHome');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/home.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }

    public function testRedirectsToHomeForUnknownPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/something-else';

        $result = $this->dispatch(ModuleFallbackController::class, 'redirectToHome');

        $this->assertTrue($result->isRedirect());
        $this->assertSame('/home.index', $result->redirectUrl());
        $this->assertNotNull($this->flashOf('error'));
    }
}
