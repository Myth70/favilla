<?php

declare(strict_types=1);

namespace App\Modules\HealthCheck\Checks;

/**
 * Base condivisa per i check: gestisce il wrapping del gruppo e fornisce le
 * factory ok()/warn()/fail() (un tempo pubbliche su HealthCheckService).
 *
 * Le sottoclassi implementano soltanto checks() con la logica del dominio.
 */
abstract class AbstractHealthCheck implements HealthCheck
{
    public const STATUS_OK   = 'ok';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';

    public const DEPTH_FAST = 'fast';
    public const DEPTH_DEEP = 'deep';

    /** Override in sottoclasse per i check pesanti (rete/shell). */
    protected string $depth = self::DEPTH_FAST;

    public function depth(): string
    {
        return $this->depth;
    }

    /**
     * @return array{label:string,description:string,checks:array<int,array{name:string,status:string,detail:string}>}
     */
    public function run(): array
    {
        return [
            'label'       => $this->label(),
            'description' => $this->description(),
            'checks'      => $this->checks(),
        ];
    }

    /**
     * Logica specifica del check: restituisce la lista di righe ok()/warn()/fail().
     *
     * @return array<int,array{name:string,status:string,detail:string}>
     */
    abstract protected function checks(): array;

    /** @return array{name:string,status:string,detail:string} */
    protected function ok(string $name, string $detail): array
    {
        return ['name' => $name, 'status' => self::STATUS_OK, 'detail' => $detail];
    }

    /** @return array{name:string,status:string,detail:string} */
    protected function warn(string $name, string $detail): array
    {
        return ['name' => $name, 'status' => self::STATUS_WARN, 'detail' => $detail];
    }

    /** @return array{name:string,status:string,detail:string} */
    protected function fail(string $name, string $detail): array
    {
        return ['name' => $name, 'status' => self::STATUS_FAIL, 'detail' => $detail];
    }

    protected function isProduction(): bool
    {
        return env('APP_ENV', 'development') === 'production';
    }
}
