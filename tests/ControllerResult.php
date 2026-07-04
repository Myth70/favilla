<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Testing\HaltResponse;
use Tests\Support\CapturingView;

/**
 * Outcome of dispatching a controller action through {@see ControllerTestCase}.
 * Exposes the terminal response (redirect / JSON) and what was rendered.
 */
final class ControllerResult
{
    public ?HaltResponse $halt = null;
    public string $echoed = '';

    public function __construct(public CapturingView $view)
    {
    }

    public function isRedirect(): bool
    {
        return $this->halt !== null && $this->halt->kind === HaltResponse::REDIRECT;
    }

    public function redirectUrl(): ?string
    {
        return $this->isRedirect() ? $this->halt->url : null;
    }

    public function isJson(): bool
    {
        return $this->halt !== null && $this->halt->kind === HaltResponse::JSON;
    }

    /** @return array<array-key,mixed>|null */
    public function jsonPayload(): ?array
    {
        return $this->isJson() ? $this->halt->payload : null;
    }

    public function jsonStatus(): int
    {
        return $this->halt?->status ?? 200;
    }

    /** Template passed to render() or renderPartial(), whichever happened. */
    public function renderedTemplate(): ?string
    {
        return $this->view->renderedTemplate ?? $this->view->renderedPartial;
    }

    /** @return array<string,mixed> */
    public function renderedData(): array
    {
        if ($this->view->renderedTemplate !== null) {
            return $this->view->renderedData;
        }
        return $this->view->renderedPartialData;
    }

    public function didRender(): bool
    {
        return $this->view->rendered;
    }
}
