<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Unit;

use App\Modules\Auth\Controllers\AvatarCropController;
use Tests\ControllerTestCase;

/**
 * Controller-level tests for the avatar crop JSON endpoint via the HTTP harness.
 * Exercises the authentication / authorization / missing-file guards, all of
 * which respond with json() before touching the upload service or DB.
 */
class AvatarCropControllerTest extends ControllerTestCase
{
    public function testCropRequiresAuthentication(): void
    {
        // No user_id in session.
        $result = $this->withPost(['context_id' => '1'])
            ->dispatch(AvatarCropController::class, 'crop');

        $this->assertTrue($result->isJson());
        $this->assertSame(401, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['success']);
    }

    public function testCropRejectsMismatchedContext(): void
    {
        $this->actingAs(5);

        $result = $this->withPost(['context_id' => '9'])
            ->dispatch(AvatarCropController::class, 'crop');

        $this->assertTrue($result->isJson());
        $this->assertSame(403, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['success']);
    }

    public function testCropRejectsMissingFile(): void
    {
        $this->actingAs(5);

        $result = $this->withPost(['context_id' => '5'])
            ->dispatch(AvatarCropController::class, 'crop');

        $this->assertTrue($result->isJson());
        $this->assertSame(400, $result->jsonStatus());
        $this->assertFalse($result->jsonPayload()['success']);
    }
}
