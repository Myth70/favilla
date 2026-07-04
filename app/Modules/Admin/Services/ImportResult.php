<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

/**
 * Result object returned by ModuleImportService::import().
 */
class ImportResult
{
    public bool $success;
    public string $moduleName;
    public array $log;
    public array $warnings;
    public ?string $error;

    public function __construct(
        bool $success,
        string $moduleName = '',
        array $log = [],
        array $warnings = [],
        ?string $error = null
    ) {
        $this->success    = $success;
        $this->moduleName = $moduleName;
        $this->log        = $log;
        $this->warnings   = $warnings;
        $this->error      = $error;
    }

    public static function fail(string $error, string $moduleName = '', array $log = [], array $warnings = []): self
    {
        return new self(false, $moduleName, $log, $warnings, $error);
    }

    public static function ok(string $moduleName, array $log = [], array $warnings = []): self
    {
        return new self(true, $moduleName, $log, $warnings);
    }
}
