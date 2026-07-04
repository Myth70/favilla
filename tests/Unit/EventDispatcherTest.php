<?php

namespace Tests\Unit;

use App\Core\Container;
use App\Core\EventDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class DummyEvent
{
    public function __construct(public string $payload = 'x')
    {
    }
}

class OtherEvent
{
    public function __construct(public int $n = 0)
    {
    }
}

class DummyListener
{
    public static array $received = [];
    public function handle(DummyEvent $e): void
    {
        self::$received[] = $e->payload;
    }
}

class ThrowingListener
{
    public function handle(DummyEvent $e): void
    {
        throw new \RuntimeException('boom');
    }
}

class RecordingLogger extends AbstractLogger
{
    public array $records = [];
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        // Reset container
        Container::setInstance(new Container());
        $this->dispatcher = new EventDispatcher();
        DummyListener::$received = [];
    }

    public function testClosureListenerReceivesEvent(): void
    {
        $captured = null;
        $this->dispatcher->listen(DummyEvent::class, function (DummyEvent $e) use (&$captured) {
            $captured = $e->payload;
        });

        $this->dispatcher->dispatch(new DummyEvent('hello'));
        $this->assertSame('hello', $captured);
    }

    public function testDispatchWithoutListenersIsNoop(): void
    {
        $this->dispatcher->dispatch(new DummyEvent('nobody'));
        $this->assertFalse($this->dispatcher->hasListeners(DummyEvent::class));
    }

    public function testClassListenerResolvedViaContainer(): void
    {
        $this->dispatcher->listen(DummyEvent::class, DummyListener::class);
        $this->dispatcher->dispatch(new DummyEvent('foo'));
        $this->dispatcher->dispatch(new DummyEvent('bar'));

        $this->assertSame(['foo', 'bar'], DummyListener::$received);
    }

    public function testMultipleListenersAllRun(): void
    {
        $a = $b = null;
        $this->dispatcher->listen(DummyEvent::class, function ($e) use (&$a) {
            $a = $e->payload;
        });
        $this->dispatcher->listen(DummyEvent::class, function ($e) use (&$b) {
            $b = strtoupper($e->payload);
        });
        $this->dispatcher->dispatch(new DummyEvent('ok'));

        $this->assertSame('ok', $a);
        $this->assertSame('OK', $b);
    }

    public function testThrowingListenerDoesNotStopOthers(): void
    {
        $logger = new RecordingLogger();
        $this->dispatcher->setLogger($logger);

        $ran = false;
        $this->dispatcher->listen(DummyEvent::class, ThrowingListener::class);
        $this->dispatcher->listen(DummyEvent::class, function () use (&$ran) {
            $ran = true;
        });

        $this->dispatcher->dispatch(new DummyEvent());

        $this->assertTrue($ran, 'Second listener should run despite first throwing');
        $this->assertNotEmpty($logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
    }

    public function testHasListeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(DummyEvent::class));
        $this->dispatcher->listen(DummyEvent::class, fn () => null);
        $this->assertTrue($this->dispatcher->hasListeners(DummyEvent::class));
        $this->assertFalse($this->dispatcher->hasListeners(OtherEvent::class));
    }

    public function testForgetRemovesListeners(): void
    {
        $this->dispatcher->listen(DummyEvent::class, fn () => null);
        $this->dispatcher->forget(DummyEvent::class);
        $this->assertFalse($this->dispatcher->hasListeners(DummyEvent::class));
    }

    public function testEventsAreRoutedByClass(): void
    {
        $dummyReceived = 0;
        $otherReceived = 0;
        $this->dispatcher->listen(DummyEvent::class, function () use (&$dummyReceived) {
            $dummyReceived++;
        });
        $this->dispatcher->listen(OtherEvent::class, function () use (&$otherReceived) {
            $otherReceived++;
        });

        $this->dispatcher->dispatch(new DummyEvent());
        $this->dispatcher->dispatch(new OtherEvent());
        $this->dispatcher->dispatch(new OtherEvent());

        $this->assertSame(1, $dummyReceived);
        $this->assertSame(2, $otherReceived);
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        $a = EventDispatcher::getInstance();
        $b = EventDispatcher::getInstance();
        $this->assertSame($a, $b);
    }
}
