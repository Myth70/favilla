<?php

declare(strict_types=1);

namespace App\Modules\Teams\Tests\Unit;

use App\Modules\Teams\Services\TeamsMessageService;
use PHPUnit\Framework\TestCase;

class TeamsMessageServiceTest extends TestCase
{
    // ── extractMentionCandidates ───────────────────────────────────────────

    public function testExtractMentionCandidatesEmptyOnNoMention(): void
    {
        $this->assertSame([], TeamsMessageService::extractMentionCandidates('ciao a tutti'));
    }

    public function testExtractMentionCandidatesSimple(): void
    {
        $result = TeamsMessageService::extractMentionCandidates('ciao @mario, come va?');
        $this->assertSame(['mario'], $result);
    }

    public function testExtractMentionCandidatesMultiple(): void
    {
        $result = TeamsMessageService::extractMentionCandidates('@mario e @luigi ci siete?');
        $this->assertEqualsCanonicalizing(['mario', 'luigi'], $result);
    }

    public function testExtractMentionCandidatesUnique(): void
    {
        $result = TeamsMessageService::extractMentionCandidates('@mario, @mario, dai @mario!');
        $this->assertSame(['mario'], $result);
    }

    public function testExtractMentionCandidatesSupportsAccents(): void
    {
        $result = TeamsMessageService::extractMentionCandidates('saluti @niccolò');
        $this->assertSame(['niccolò'], $result);
    }

    public function testExtractMentionCandidatesIgnoresEmail(): void
    {
        // info@example.com non deve essere catturato (la `@` è preceduta da \w)
        $result = TeamsMessageService::extractMentionCandidates('contatta info@example.com per supporto');
        $this->assertSame([], $result);
    }

    public function testExtractMentionCandidatesSupportsDotsAndDashes(): void
    {
        $result = TeamsMessageService::extractMentionCandidates('cc @mario.rossi e @anna-bianchi');
        $this->assertEqualsCanonicalizing(['mario.rossi', 'anna-bianchi'], $result);
    }

    // ── normalizeAttachments ───────────────────────────────────────────────

    public function testNormalizeAttachmentsNullReturnsEmpty(): void
    {
        $this->assertSame([], TeamsMessageService::normalizeAttachments(null));
    }

    public function testNormalizeAttachmentsNonArrayReturnsEmpty(): void
    {
        $this->assertSame([], TeamsMessageService::normalizeAttachments('not-array'));
    }

    public function testNormalizeAttachmentsSingleEntryHappyPath(): void
    {
        $input = [
            'name'     => 'foo.txt',
            'tmp_name' => '/tmp/upXYZ',
            'type'     => 'text/plain',
            'size'     => 12,
            'error'    => UPLOAD_ERR_OK,
        ];
        $out = TeamsMessageService::normalizeAttachments($input);
        $this->assertCount(1, $out);
        $this->assertSame('foo.txt', $out[0]['name']);
    }

    public function testNormalizeAttachmentsSingleEntryWithErrorIsDropped(): void
    {
        $input = [
            'name'     => 'broken.txt',
            'tmp_name' => '/tmp/x',
            'error'    => UPLOAD_ERR_PARTIAL,
            'size'     => 0,
        ];
        $this->assertSame([], TeamsMessageService::normalizeAttachments($input));
    }

    public function testNormalizeAttachmentsMultiFileColumnFormat(): void
    {
        $input = [
            'name'     => ['a.txt', 'b.txt', 'c.txt'],
            'tmp_name' => ['/tmp/a', '/tmp/b', '/tmp/c'],
            'type'     => ['text/plain', 'text/plain', 'text/plain'],
            'size'     => [10, 20, 30],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_OK],
        ];
        $out = TeamsMessageService::normalizeAttachments($input);
        $this->assertCount(3, $out);
        $this->assertSame('a.txt', $out[0]['name']);
        $this->assertSame('c.txt', $out[2]['name']);
    }

    public function testNormalizeAttachmentsMultiFileSkipsFailedEntries(): void
    {
        $input = [
            'name'     => ['ok.txt', 'broken.txt', 'ok2.txt'],
            'tmp_name' => ['/tmp/ok', '/tmp/x', '/tmp/ok2'],
            'type'     => ['text/plain', 'text/plain', 'text/plain'],
            'size'     => [10, 0, 20],
            'error'    => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_OK],
        ];
        $out = TeamsMessageService::normalizeAttachments($input);
        $this->assertCount(2, $out);
        $this->assertSame('ok.txt', $out[0]['name']);
        $this->assertSame('ok2.txt', $out[1]['name']);
    }

    public function testNormalizeAttachmentsListOfEntries(): void
    {
        $input = [
            ['name' => 'a.txt', 'tmp_name' => '/tmp/a', 'error' => UPLOAD_ERR_OK, 'size' => 10, 'type' => 'text/plain'],
            ['name' => 'b.txt', 'tmp_name' => '/tmp/b', 'error' => UPLOAD_ERR_OK, 'size' => 20, 'type' => 'text/plain'],
        ];
        $out = TeamsMessageService::normalizeAttachments($input);
        $this->assertCount(2, $out);
    }

    public function testNormalizeAttachmentsListWithBadEntriesFiltered(): void
    {
        $input = [
            ['name' => 'ok.txt', 'tmp_name' => '/tmp/ok', 'error' => UPLOAD_ERR_OK, 'size' => 10],
            ['name' => 'bad.txt', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0],
            ['name' => 'bad2.txt', 'error' => UPLOAD_ERR_OK], // manca tmp_name
        ];
        $out = TeamsMessageService::normalizeAttachments($input);
        $this->assertCount(1, $out);
        $this->assertSame('ok.txt', $out[0]['name']);
    }

    public function testNormalizeAttachmentsEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], TeamsMessageService::normalizeAttachments([]));
    }
}
