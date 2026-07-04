<?php

namespace App\Modules\Documenti\Tests\Unit;

use App\Modules\Documenti\Services\CategoryTreeService;
use PHPUnit\Framework\TestCase;

/**
 * Verifica il fix del path materializzato delle categorie: il path del figlio
 * non deve duplicare l'id del parent (bug: prefix '/5/' diventava '/5/5/').
 * pathFiglio() è una funzione pura: testabile senza DB.
 */
class CategoryTreePathTest extends TestCase
{
    public function testRootChild(): void
    {
        $this->assertSame('/5/', CategoryTreeService::pathFiglio('/', 5));
    }

    public function testSecondLevelChildDoesNotDuplicateParentId(): void
    {
        // Parent '/5/' → figlio 12 deve essere '/5/12/', NON '/5/5/12/'.
        $this->assertSame('/5/12/', CategoryTreeService::pathFiglio('/5/', 12));
    }

    public function testThirdLevelChild(): void
    {
        $this->assertSame('/3/7/9/', CategoryTreeService::pathFiglio('/3/7/', 9));
    }

    public function testNullParentPathTreatedAsRoot(): void
    {
        $this->assertSame('/5/', CategoryTreeService::pathFiglio(null, 5));
    }

    public function testEmptyParentPathTreatedAsRoot(): void
    {
        $this->assertSame('/5/', CategoryTreeService::pathFiglio('', 5));
    }

    public function testParentPathWithoutTrailingSlashIsNormalised(): void
    {
        $this->assertSame('/5/12/', CategoryTreeService::pathFiglio('/5', 12));
    }
}
