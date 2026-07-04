<?php

namespace Tests\Unit;

use App\Cli\Support\CliBootstrap;
use App\Core\Container;
use App\Core\ModuleLoader;
use App\Core\Router;
use Tests\ModuleTestCase;

class CliBootstrapTest extends ModuleTestCase
{
    public function testBootRegistersRouterAndModuleLoaderForCliRuntime(): void
    {
        CliBootstrap::boot();

        $container = Container::getInstance();

        $this->assertTrue($container->has(ModuleLoader::class));
        $this->assertTrue($container->has(Router::class));
        $this->assertStringContainsString('/scheduler', route('scheduler.index'));
        $this->assertIsBool(isModuleEnabled('Scheduler'));
    }
}
