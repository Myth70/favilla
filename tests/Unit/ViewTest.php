<?php

namespace Tests\Unit;

use App\Core\View;
use PHPUnit\Framework\TestCase;

class ViewTest extends TestCase
{
    private string $tmpBase;
    private View $view;

    protected function setUp(): void
    {
        // Project-relative tmp (open_basedir friendly)
        $this->tmpBase = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/cache/view_test_' . bin2hex(random_bytes(4));
        @mkdir($this->tmpBase . '/app/Modules', 0777, true);
        @mkdir($this->tmpBase . '/app/Views/layouts', 0777, true);
        @mkdir($this->tmpBase . '/app/Views/partials', 0777, true);

        $this->view = new View($this->tmpBase);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpBase);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    public function testSectionStartEndYieldFlow(): void
    {
        $this->view->start('title');
        echo 'Hello';
        $this->view->end();

        ob_start();
        $this->view->yield('title');
        $this->assertSame('Hello', ob_get_clean());
        $this->assertTrue($this->view->hasSection('title'));
    }

    public function testYieldFallsBackToDefault(): void
    {
        ob_start();
        $this->view->yield('missing', 'fallback');
        $this->assertSame('fallback', ob_get_clean());
    }

    public function testHasSectionReturnsFalseBeforeCapture(): void
    {
        $this->assertFalse($this->view->hasSection('header'));
    }

    public function testPushScriptDeduplicates(): void
    {
        $this->view->pushScript('/app.js');
        $this->view->pushScript('/app.js');
        $this->view->pushScript('/other.js');
        $this->assertSame(['/app.js', '/other.js'], $this->view->getExtraScripts());
    }

    public function testPushStyleDeduplicates(): void
    {
        $this->view->pushStyle('/a.css');
        $this->view->pushStyle('/a.css');
        $this->assertSame(['/a.css'], $this->view->getExtraStyles());
    }

    public function testShareMakesDataAvailableToTemplate(): void
    {
        file_put_contents(
            $this->tmpBase . '/app/Views/hello.php',
            '<?php echo "Ciao " . $name; ?>'
        );

        $this->view->share('name', 'Mario');

        ob_start();
        $this->view->renderPartial('hello');
        $this->assertSame('Ciao Mario', ob_get_clean());
    }

    public function testRenderShowsFriendlyErrorWhenNotFound(): void
    {
        ob_start();
        $this->view->render('Missing/View/that/does/not/exist');
        $out = ob_get_clean();
        $this->assertStringContainsString('View not found', $out);
    }

    public function testResolveTemplateBlocksTraversal(): void
    {
        ob_start();
        $this->view->renderPartial('../etc/passwd');
        $this->assertSame('', ob_get_clean());

        ob_start();
        $this->view->renderPartial('/absolute');
        $this->assertSame('', ob_get_clean());
    }

    public function testRenderWithLayout(): void
    {
        // Child view sets layout + writes a section
        file_put_contents(
            $this->tmpBase . '/app/Views/child.php',
            '<?php $view->layout("main"); $view->start("content"); echo "BODY"; $view->end(); ?>'
        );
        file_put_contents(
            $this->tmpBase . '/app/Views/layouts/main.php',
            '<?php echo "<html>"; $view->yield("content"); echo "</html>"; ?>'
        );

        ob_start();
        $this->view->render('child');
        $out = ob_get_clean();
        $this->assertSame('<html>BODY</html>', $out);
    }

    public function testRenderRejectsInvalidLayoutName(): void
    {
        file_put_contents(
            $this->tmpBase . '/app/Views/child2.php',
            '<?php $view->layout("../evil"); echo "BODY"; ?>'
        );

        ob_start();
        $this->view->render('child2');
        $out = ob_get_clean();
        // Invalid layout names are silently skipped — only child output remains
        $this->assertSame('BODY', $out);
    }

    public function testEndWithoutStartIsNoOp(): void
    {
        $this->view->end();
        $this->assertFalse($this->view->hasSection('x'));
    }

    public function testIncludePartialFromTemplate(): void
    {
        file_put_contents(
            $this->tmpBase . '/app/Views/partials/bar.php',
            '<?php echo "BAR:" . ($msg ?? ""); ?>'
        );
        file_put_contents(
            $this->tmpBase . '/app/Views/main.php',
            '<?php echo "A-"; $view->include("partials/bar", ["msg" => "ok"]); echo "-B"; ?>'
        );

        ob_start();
        $this->view->renderPartial('main');
        $this->assertSame('A-BAR:ok-B', ob_get_clean());
    }
}
