<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    private array $bindings = [];
    private array $singletons = [];
    private array $instances = [];
    /** @var string[] Stack of classes being resolved (detect circular deps) */
    private array $resolving = [];

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function setInstance(self $container): void
    {
        self::$instance = $container;
    }

    /**
     * Register a binding (factory).
     */
    public function bind(string $abstract, callable|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Register a shared binding (singleton).
     */
    public function singleton(string $abstract, callable|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    /**
     * Register an already-built instance.
     */
    public function instance(string $abstract, mixed $object): void
    {
        $this->instances[$abstract] = $object;
    }

    /**
     * Resolve a class or interface from the container.
     */
    public function make(string $abstract): mixed
    {
        // Already resolved singleton instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Circular dependency detection
        if (in_array($abstract, $this->resolving, true)) {
            $chain = implode(' -> ', $this->resolving);
            throw new RuntimeException(
                "Container: circular dependency detected: {$chain} -> {$abstract}"
            );
        }

        $this->resolving[] = $abstract;

        try {
            // Singleton binding — resolve once, cache
            if (isset($this->singletons[$abstract])) {
                $concrete = $this->singletons[$abstract];
                $object = $this->build($concrete);
                $this->instances[$abstract] = $object;
                unset($this->singletons[$abstract]);
                return $object;
            }

            // Factory binding — resolve every time
            if (isset($this->bindings[$abstract])) {
                return $this->build($this->bindings[$abstract]);
            }

            // Auto-wiring: try to build the class directly
            return $this->build($abstract);
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Alias for make().
     */
    public function resolve(string $abstract): mixed
    {
        return $this->make($abstract);
    }

    /**
     * Check if a binding or instance exists.
     */
    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->singletons[$abstract])
            || isset($this->bindings[$abstract]);
    }

    /**
     * Build a concrete class, resolving constructor dependencies via Reflection.
     */
    private function build(callable|string $concrete): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        if (!class_exists($concrete)) {
            throw new RuntimeException("Container: class [{$concrete}] not found.");
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Container: [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                try {
                    $dependencies[] = $this->make($typeName);
                } catch (RuntimeException $e) {
                    // Re-throw circular dependency errors immediately
                    if (str_contains($e->getMessage(), 'circular dependency')) {
                        throw $e;
                    }
                    // If the type is not resolvable and the parameter has a default, use it.
                    // Il fallback resta, ma va loggato: una dipendenza obbligatoria
                    // mal configurata diventerebbe un null che esplode lontano dalla causa.
                    if ($param->isDefaultValueAvailable()) {
                        app_log('error', "Container: [{$typeName}] non risolvibile per [\${$param->getName()}] in [{$concrete}], uso il default. Causa: " . $e->getMessage());
                        $dependencies[] = $param->getDefaultValue();
                    } elseif ($type->allowsNull()) {
                        app_log('error', "Container: [{$typeName}] non risolvibile per [\${$param->getName()}] in [{$concrete}], inietto null. Causa: " . $e->getMessage());
                        $dependencies[] = null;
                    } else {
                        throw new RuntimeException(
                            "Container: cannot resolve parameter [\${$param->getName()}] of type [{$typeName}] in [{$concrete}]."
                        );
                    }
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException(
                    "Container: cannot resolve parameter [\${$param->getName()}] in [{$concrete}]."
                );
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
