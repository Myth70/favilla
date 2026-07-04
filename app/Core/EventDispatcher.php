<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class EventDispatcher
{
    private static ?self $instance = null;
    private array $listeners = [];
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Get singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set the logger used for listener error reporting.
     * Called by Application after ErrorHandler is booted.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Register a listener for an event class.
     *
     * @param string $eventClass Fully-qualified event class name
     * @param callable|string $listener Callable or class name with handle() method
     */
    public function listen(string $eventClass, callable|string $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public function dispatch(object $event): void
    {
        $eventClass = get_class($event);

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            try {
                if (is_string($listener)) {
                    // Resolve tramite container per rispettare la dependency injection
                    $instance = app($listener);
                    if ($instance && method_exists($instance, 'handle')) {
                        $instance->handle($event);
                    }
                } elseif (is_callable($listener)) {
                    $listener($event);
                }
            } catch (\Throwable $e) {
                // Un listener fallito non deve bloccare gli altri né il flusso principale
                $this->logger->error('EventDispatcher listener error', [
                    'event'     => $eventClass,
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * Check if an event has listeners.
     */
    public function hasListeners(string $eventClass): bool
    {
        return !empty($this->listeners[$eventClass]);
    }

    /**
     * Remove all listeners for an event.
     */
    public function forget(string $eventClass): void
    {
        unset($this->listeners[$eventClass]);
    }
}
