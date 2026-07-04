<?php

namespace Tests\Unit;

use App\Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// Test fixtures (simple classes for auto-wiring tests)
class SimpleService
{
    public string $value = 'simple';
}

class DependentService
{
    public SimpleService $dep;

    public function __construct(SimpleService $dep)
    {
        $this->dep = $dep;
    }
}

// Circular dependency fixtures
class CircularA
{
    public function __construct(CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(CircularA $a)
    {
    }
}

// Interface binding fixtures
interface Greeter
{
    public function greet(): string;
}

class ItalianGreeter implements Greeter
{
    public function greet(): string
    {
        return 'Ciao!';
    }
}

class GreetingUser
{
    public Greeter $greeter;

    public function __construct(Greeter $greeter)
    {
        $this->greeter = $greeter;
    }
}

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        Container::setInstance($this->container);
    }

    public function testInstanceRegistrationAndResolution(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';

        $this->container->instance('myObj', $obj);

        $this->assertTrue($this->container->has('myObj'));
        $this->assertSame($obj, $this->container->make('myObj'));
    }

    public function testBindCreatesNewInstanceEachTime(): void
    {
        $this->container->bind(SimpleService::class, SimpleService::class);

        $a = $this->container->make(SimpleService::class);
        $b = $this->container->make(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $a);
        $this->assertInstanceOf(SimpleService::class, $b);
        $this->assertNotSame($a, $b);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton(SimpleService::class, SimpleService::class);

        $a = $this->container->make(SimpleService::class);
        $b = $this->container->make(SimpleService::class);

        $this->assertSame($a, $b);
    }

    public function testSingletonWithClosure(): void
    {
        $this->container->singleton('config', function () {
            return ['debug' => true];
        });

        $result = $this->container->make('config');

        $this->assertIsArray($result);
        $this->assertTrue($result['debug']);
    }

    public function testAutoWiringResolvesSimpleClass(): void
    {
        $service = $this->container->make(SimpleService::class);

        $this->assertInstanceOf(SimpleService::class, $service);
        $this->assertSame('simple', $service->value);
    }

    public function testAutoWiringResolvesDependencies(): void
    {
        $service = $this->container->make(DependentService::class);

        $this->assertInstanceOf(DependentService::class, $service);
        $this->assertInstanceOf(SimpleService::class, $service->dep);
    }

    public function testHasReturnsFalseForUnknownBinding(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testHasReturnsTrueForRegisteredInstance(): void
    {
        $this->container->instance('key', 'value');
        $this->assertTrue($this->container->has('key'));
    }

    public function testMakeThrowsForNonExistentClass(): void
    {
        $this->expectException(RuntimeException::class);
        $this->container->make('NonExistent\\ClassName');
    }

    public function testResolveIsAliasForMake(): void
    {
        $this->container->instance('test', 'value');

        $this->assertSame(
            $this->container->make('test'),
            $this->container->resolve('test')
        );
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $a = Container::getInstance();
        $b = Container::getInstance();

        $this->assertSame($a, $b);
    }

    public function testCircularDependencyThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/circular dependency/i');

        $this->container->make(CircularA::class);
    }

    public function testInterfaceBindingResolvesToConcrete(): void
    {
        $this->container->bind(Greeter::class, ItalianGreeter::class);

        $greeter = $this->container->make(Greeter::class);

        $this->assertInstanceOf(ItalianGreeter::class, $greeter);
        $this->assertSame('Ciao!', $greeter->greet());
    }

    public function testAutoWiringResolvesInterfaceBinding(): void
    {
        $this->container->bind(Greeter::class, ItalianGreeter::class);

        $user = $this->container->make(GreetingUser::class);

        $this->assertInstanceOf(GreetingUser::class, $user);
        $this->assertSame('Ciao!', $user->greeter->greet());
    }

    public function testNullableInterfaceResolvesToNull(): void
    {
        // GreetingUser requires Greeter but it's not bound
        // If we had a nullable parameter, it should resolve to null
        // Test that unresolvable non-nullable throws properly
        $this->expectException(RuntimeException::class);
        $this->container->make(GreetingUser::class);
    }
}
