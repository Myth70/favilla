<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CspBuildTest extends TestCase
{
    /**
        * Build CSP using the same logic as SecurityHeadersMiddleware::buildCsp().
     */
    private function buildCsp(string $nonce, array $csp): string
    {
        if (isset($csp['script-src'])) {
            $csp['script-src'][] = "'nonce-{$nonce}'";
        }

        $parts = [];
        foreach ($csp as $directive => $values) {
            $parts[] = $directive . ' ' . implode(' ', $values);
        }

        return implode('; ', $parts);
    }

    public function test_csp_contains_nonce_in_script_src(): void
    {
        $nonce = base64_encode(random_bytes(16));
        $csp = $this->buildCsp($nonce, [
            'default-src' => ["'self'"],
            'script-src'  => ["'self'"],
            'style-src'   => ["'self'", "'unsafe-inline'"],
        ]);

        $this->assertStringContainsString("'nonce-{$nonce}'", $csp);
    }

    public function test_csp_does_not_contain_unsafe_inline_in_script_src(): void
    {
        $nonce = base64_encode(random_bytes(16));
        $config = require dirname(__DIR__, 2) . '/app/Config/security.php';
        $csp = $this->buildCsp($nonce, $config['csp']);

        // script-src should NOT have unsafe-inline
        preg_match('/script-src ([^;]+)/', $csp, $matches);
        $scriptSrc = $matches[1] ?? '';
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
    }

    public function test_csp_preserves_style_unsafe_inline(): void
    {
        $nonce = base64_encode(random_bytes(16));
        $config = require dirname(__DIR__, 2) . '/app/Config/security.php';
        $csp = $this->buildCsp($nonce, $config['csp']);

        preg_match('/style-src ([^;]+)/', $csp, $matches);
        $styleSrc = $matches[1] ?? '';
        $this->assertStringContainsString("'unsafe-inline'", $styleSrc);
    }

    public function test_csp_contains_frame_ancestors_none(): void
    {
        $nonce = base64_encode(random_bytes(16));
        $config = require dirname(__DIR__, 2) . '/app/Config/security.php';
        $csp = $this->buildCsp($nonce, $config['csp']);

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_does_not_contain_unsafe_eval(): void
    {
        $nonce = base64_encode(random_bytes(16));
        $config = require dirname(__DIR__, 2) . '/app/Config/security.php';
        $csp = $this->buildCsp($nonce, $config['csp']);

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }
}
