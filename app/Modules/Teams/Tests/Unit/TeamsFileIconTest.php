<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Support\TeamsFileIcon;
use PHPUnit\Framework\TestCase;

/**
 * Unit test (puri, no DB) per TeamsFileIcon.
 * Verifica priorità estensione → fallback MIME → fallback generico.
 */
class TeamsFileIconTest extends TestCase
{
    public function testIconByExtensionMatchesPdf(): void
    {
        $this->assertSame('fa-file-pdf', TeamsFileIcon::iconClass('application/octet-stream', 'pdf'));
    }

    public function testIconByExtensionMatchesArchive(): void
    {
        $this->assertSame('fa-file-zipper', TeamsFileIcon::iconClass('', 'zip'));
        $this->assertSame('fa-file-zipper', TeamsFileIcon::iconClass('', '7z'));
    }

    public function testIconByExtensionMatchesOfficeDocs(): void
    {
        $this->assertSame('fa-file-word', TeamsFileIcon::iconClass('', 'docx'));
        $this->assertSame('fa-file-excel', TeamsFileIcon::iconClass('', 'xlsx'));
        $this->assertSame('fa-file-powerpoint', TeamsFileIcon::iconClass('', 'pptx'));
    }

    public function testIconExtensionLookupIsCaseInsensitive(): void
    {
        $this->assertSame('fa-file-pdf', TeamsFileIcon::iconClass('', 'PDF'));
        $this->assertSame('fa-file-pdf', TeamsFileIcon::iconClass('', '.PDF'));
    }

    public function testIconFallsBackToMimePrefix(): void
    {
        // Estensione sconosciuta → MIME prefix
        $this->assertSame('fa-file-image', TeamsFileIcon::iconClass('image/heic', 'heic'));
        $this->assertSame('fa-file-video', TeamsFileIcon::iconClass('video/quicktime', 'mov2'));
        $this->assertSame('fa-file-audio', TeamsFileIcon::iconClass('audio/aac', 'aac'));
        $this->assertSame('fa-file-lines', TeamsFileIcon::iconClass('text/markdown', 'md'));
    }

    public function testIconFallsBackToGenericFile(): void
    {
        $this->assertSame('fa-file', TeamsFileIcon::iconClass('application/unknown', 'xyz'));
        $this->assertSame('fa-file', TeamsFileIcon::iconClass('', ''));
    }

    public function testKindOfFromExtension(): void
    {
        $this->assertSame(TeamsFileIcon::KIND_IMAGE, TeamsFileIcon::kindOf('', 'png'));
        $this->assertSame(TeamsFileIcon::KIND_VIDEO, TeamsFileIcon::kindOf('', 'mp4'));
        $this->assertSame(TeamsFileIcon::KIND_AUDIO, TeamsFileIcon::kindOf('', 'mp3'));
        $this->assertSame(TeamsFileIcon::KIND_DOC, TeamsFileIcon::kindOf('', 'pdf'));
        $this->assertSame(TeamsFileIcon::KIND_ARCHIVE, TeamsFileIcon::kindOf('', 'zip'));
    }

    public function testKindOfFromMimeFallback(): void
    {
        $this->assertSame(TeamsFileIcon::KIND_IMAGE, TeamsFileIcon::kindOf('image/heic', 'heic'));
        $this->assertSame(TeamsFileIcon::KIND_VIDEO, TeamsFileIcon::kindOf('video/x-matroska', 'unknown'));
        $this->assertSame(TeamsFileIcon::KIND_AUDIO, TeamsFileIcon::kindOf('audio/aac', 'unknown'));
        $this->assertSame(TeamsFileIcon::KIND_DOC, TeamsFileIcon::kindOf('application/pdf', 'unknown'));
        $this->assertSame(TeamsFileIcon::KIND_ARCHIVE, TeamsFileIcon::kindOf('application/zip', 'unknown'));
    }

    public function testKindOfReturnsOtherForUnknown(): void
    {
        $this->assertSame(TeamsFileIcon::KIND_OTHER, TeamsFileIcon::kindOf('application/octet-stream', 'xyz'));
        $this->assertSame(TeamsFileIcon::KIND_OTHER, TeamsFileIcon::kindOf('', ''));
    }

    public function testKindLabelMapsAllKnownKinds(): void
    {
        $this->assertSame('Documenti', TeamsFileIcon::kindLabel(TeamsFileIcon::KIND_DOC));
        $this->assertSame('Archivi', TeamsFileIcon::kindLabel(TeamsFileIcon::KIND_ARCHIVE));
        $this->assertSame('Audio', TeamsFileIcon::kindLabel(TeamsFileIcon::KIND_AUDIO));
        $this->assertSame('Video', TeamsFileIcon::kindLabel(TeamsFileIcon::KIND_VIDEO));
        $this->assertSame('Immagini', TeamsFileIcon::kindLabel(TeamsFileIcon::KIND_IMAGE));
    }

    public function testKindLabelDefaultsToTuttiForUnknown(): void
    {
        $this->assertSame('Tutti', TeamsFileIcon::kindLabel('foo'));
        $this->assertSame('Tutti', TeamsFileIcon::kindLabel(TeamsFileIcon::KIND_OTHER));
        $this->assertSame('Tutti', TeamsFileIcon::kindLabel('all'));
    }
}
