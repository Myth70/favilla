<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Tests\Unit;

use App\Modules\Contacts\Services\ContactsService;
use PHPUnit\Framework\TestCase;

class ContactsServiceTest extends TestCase
{
    public function testNormalizeTagsDeduplicatesAndTrims(): void
    {
        $result = ContactsService::normalizeTags('  cliente,vip,cliente,  urgente  , ');
        $this->assertSame('cliente, vip, urgente', $result);
    }

    public function testInitialsUsesNameAndSurname(): void
    {
        $this->assertSame('MR', ContactsService::initials('Mario', 'Rossi'));
        $this->assertSame('M', ContactsService::initials('Mario'));
    }

    public function testAvatarColorIsDeterministicForSameInput(): void
    {
        $c1 = ContactsService::avatarColor('Mario Rossi');
        $c2 = ContactsService::avatarColor('Mario Rossi');

        $this->assertSame($c1, $c2);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $c1);
    }
}
