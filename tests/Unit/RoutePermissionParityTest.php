<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the access-control contract: every permission slug used as a route
 * guard — RoleMiddleware::withPermission('...') — must be declared in some
 * module's permissions.php.
 *
 * A typo'd or removed permission would otherwise leave a route guarded by a
 * slug no role can ever hold (locking everyone out) or an unmanageable guard
 * invisible to the admin UI. The reverse direction is intentionally NOT
 * asserted: permissions used only via has_permission() in views legitimately
 * appear in permissions.php without a matching route guard.
 */
class RoutePermissionParityTest extends TestCase
{
    /** @return array<string,true> declared permission slugs */
    private function declaredPermissions(): array
    {
        $slugs = [];
        foreach (glob(BASE_PATH . '/app/Modules/*/permissions.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match_all("/'slug'\\s*=>\\s*'([^']+)'/", $src, $m)) {
                foreach ($m[1] as $slug) {
                    $slugs[$slug] = true;
                }
            }
        }
        return $slugs;
    }

    /** @return array<string,string> slug => "module/file" where it is referenced */
    private function referencedPermissions(): array
    {
        $files = glob(BASE_PATH . '/app/Modules/*/routes.php') ?: [];
        $config = BASE_PATH . '/app/Config/routes.php';
        if (is_file($config)) {
            $files[] = $config;
        }

        $refs = [];
        foreach ($files as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match_all('/withPermission\\(\\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
                foreach ($m[1] as $slug) {
                    $refs[$slug] = basename(dirname($file)) . '/' . basename($file);
                }
            }
        }
        return $refs;
    }

    public function testEveryRouteGuardPermissionIsDeclared(): void
    {
        $declared = $this->declaredPermissions();
        $this->assertNotEmpty($declared, 'No permissions discovered — check permissions.php globbing.');

        $referenced = $this->referencedPermissions();
        $this->assertNotEmpty($referenced, 'No withPermission() guards discovered — check routes.php globbing.');

        $missing = [];
        foreach ($referenced as $slug => $where) {
            if (!isset($declared[$slug])) {
                $missing[] = "{$slug} (guarded in {$where})";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Route guards reference permissions not declared in any permissions.php:\n  "
                . implode("\n  ", $missing)
        );
    }
}
