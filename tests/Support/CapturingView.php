<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\View;

/**
 * Test double for {@see \App\Core\View}: records render()/renderPartial() calls
 * (template name + data) instead of including the real template files. Lets
 * controller tests assert on WHAT a controller rendered, without executing the
 * heavy view PHP (and the dozens of partials/translations it pulls in).
 */
final class CapturingView extends View
{
    public ?string $renderedTemplate = null;
    /** @var array<string,mixed> */
    public array $renderedData = [];
    public ?string $renderedPartial = null;
    /** @var array<string,mixed> */
    public array $renderedPartialData = [];
    public bool $rendered = false;

    public function __construct()
    {
        parent::__construct(defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2));
    }

    public function render(string $template, array $data = []): void
    {
        $this->renderedTemplate = $template;
        $this->renderedData     = $data;
        $this->rendered         = true;
    }

    public function renderPartial(string $template, array $data = []): void
    {
        $this->renderedPartial     = $template;
        $this->renderedPartialData = $data;
        $this->rendered            = true;
    }

    public function include(string $partial, array $data = []): void
    {
        // No-op under test: partial includes are not exercised by controller unit tests.
    }
}
