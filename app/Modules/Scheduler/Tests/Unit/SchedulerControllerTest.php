<?php

namespace App\Modules\Scheduler\Tests\Unit;

use App\Modules\Scheduler\Controllers\SchedulerController;
use App\Traits\ControllerHelpers;
use PHPUnit\Framework\TestCase;

class SchedulerControllerTest extends TestCase
{
    public function testControllerUsesControllerHelpersTrait(): void
    {
        $traits = class_uses(SchedulerController::class);

        $this->assertIsArray($traits);
        $this->assertContains(ControllerHelpers::class, $traits);
    }
}
