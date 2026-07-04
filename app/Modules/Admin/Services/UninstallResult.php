<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

/**
 * Result object returned by ModuleUninstallService::uninstall().
 */
class UninstallResult
{
    public bool $success;
    public array $log;
    public array $warnings;
    public ?string $error;

    public function __construct(
        bool $success,
        array $log = [],
        array $warnings = [],
        ?string $error = null
    ) {
        $this->success  = $success;
        $this->log      = $log;
        $this->warnings = $warnings;
        $this->error    = $error;
    }

    public static function fail(string $error, array $log = [], array $warnings = []): self
    {
        return new self(false, $log, $warnings, $error);
    }

    public static function ok(array $log = [], array $warnings = []): self
    {
        return new self(true, $log, $warnings);
    }
}
