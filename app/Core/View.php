<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    private string $basePath;
    private array $sections = [];
    private array $sectionStack = [];
    private ?string $layout = null;
    private array $extraScripts = [];
    private array $extraStyles = [];
    private array $shared = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
    }

    /**
     * Share a variable with all views.
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Set layout to use. Called from within view templates.
     */
    public function layout(string $name): void
    {
        $this->layout = $name;
    }

    /**
     * Start a named section buffer.
     */
    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * End the current section buffer.
     */
    public function end(): void
    {
        if (empty($this->sectionStack)) {
            return;
        }
        $name = array_pop($this->sectionStack);
        $output = ob_get_clean();
        if ($output === false) {
            throw new \RuntimeException("View section '{$name}': output buffer mismatch.");
        }
        $this->sections[$name] = $output;
    }

    /**
     * Output a named section's content.
     */
    public function yield(string $name, string $default = ''): void
    {
        echo $this->sections[$name] ?? $default;
    }

    /**
     * Check if a section exists.
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Push a script path to be loaded in the layout.
     */
    public function pushScript(string $src): void
    {
        if (!in_array($src, $this->extraScripts, true)) {
            $this->extraScripts[] = $src;
        }
    }

    /**
     * Push a stylesheet path to be loaded in the layout.
     */
    public function pushStyle(string $href): void
    {
        if (!in_array($href, $this->extraStyles, true)) {
            $this->extraStyles[] = $href;
        }
    }

    /**
     * Get extra scripts pushed by child views.
     */
    public function getExtraScripts(): array
    {
        return $this->extraScripts;
    }

    /**
     * Get extra styles pushed by child views.
     */
    public function getExtraStyles(): array
    {
        return $this->extraStyles;
    }

    /**
     * Render a full view with layout.
     * 1. Render the child view (captures sections + detects layout)
     * 2. Render the layout, which calls $view->yield() to insert sections
     */
    public function render(string $template, array $data = []): void
    {
        // Reset state for this render
        $this->sections = [];
        $this->sectionStack = [];
        $this->layout = null;
        $this->extraScripts = [];
        $this->extraStyles = [];

        // Resolve template path — check module Views first, then shared Views
        $file = $this->resolveTemplate($template);
        if ($file === null) {
            echo '<p>View not found: ' . e($template) . '</p>';
            return;
        }

        // Render child view to capture sections
        $data['view'] = $this;
        $data = array_merge($this->shared, $data);
        $this->renderFile($file, $data);

        // If a layout was set, render it.
        // Whitelist rigorosa: solo nomi di layout semplici, nessun separatore.
        if ($this->layout !== null && preg_match('/^[a-zA-Z0-9_\-]+$/', $this->layout)) {
            $layoutFile = $this->basePath . '/app/Views/layouts/' . $this->layout . '.php';
            if (file_exists($layoutFile)) {
                $this->renderFile($layoutFile, $data);
            }
        }
    }

    /**
     * Render a partial template (no layout) — for HTMX responses.
     */
    public function renderPartial(string $template, array $data = []): void
    {
        $file = $this->resolveTemplate($template);
        if ($file === null) {
            return;
        }

        $data['view'] = $this;
        $data = array_merge($this->shared, $data);
        $this->renderFile($file, $data);
    }

    /**
     * Resolve a template name to a file path.
     * Supports: "Module/Views/template" and "shared/template"
     */
    private function resolveTemplate(string $template): ?string
    {
        // Defense-in-depth: block directory traversal sequences
        if (str_contains($template, '..') || str_starts_with($template, '/')) {
            return null;
        }

        // Try as module view: "Dashboard/Views/index" → app/Modules/Dashboard/Views/index.php
        $modulePath = $this->basePath . '/app/Modules/' . $template . '.php';
        if (file_exists($modulePath)) {
            return $modulePath;
        }

        // Try as shared view: "errors/404" → app/Views/errors/404.php
        $sharedPath = $this->basePath . '/app/Views/' . $template . '.php';
        if (file_exists($sharedPath)) {
            return $sharedPath;
        }

        return null;
    }

    /**
     * Include a file with extracted data.
     */
    private function renderFile(string $file, array $data): void
    {
        extract($data, EXTR_SKIP);
        include $file;
    }

    /**
     * Include a partial file (for use inside templates).
     */
    public function include(string $partial, array $data = []): void
    {
        // resolveTemplate() correctly handles both module paths (app/Modules/...)
        // and shared paths (app/Views/...), including app/Views/partials/
        $file = $this->resolveTemplate($partial);
        if ($file !== null) {
            $data['view'] = $this;
            $data = array_merge($this->shared, $data);
            extract($data, EXTR_SKIP);
            include $file;
        }
    }
}
