<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit;

use App\Modules\Notifications\Services\WebPushSender;
use PHPUnit\Framework\TestCase;

/**
 * Mappatura status HTTP → esito consegna. È la logica di lifecycle più delicata
 * del canale: decide quali subscription vengono eliminate (404/410) e quali no
 * (401/403 = problema di firma, NON subscription morta).
 */
class WebPushSenderTest extends TestCase
{
    public function testSuccessStatusesAreNotExpired(): void
    {
        foreach ([200, 201, 202] as $status) {
            $result = WebPushSender::classifyStatus($status);
            $this->assertTrue($result['success'], "HTTP {$status} deve essere success");
            $this->assertFalse($result['expired']);
            $this->assertNull($result['error']);
        }
    }

    public function testGoneAndNotFoundMarkExpired(): void
    {
        foreach ([404, 410] as $status) {
            $result = WebPushSender::classifyStatus($status);
            $this->assertFalse($result['success']);
            $this->assertTrue($result['expired'], "HTTP {$status} deve marcare la subscription come scaduta");
        }
    }

    public function testAuthErrorsAreFailuresButNotExpired(): void
    {
        // 401/403 = firma/VAPID errata → NON eliminare la subscription (potrebbe
        // essere una misconfigurazione temporanea).
        foreach ([401, 403] as $status) {
            $result = WebPushSender::classifyStatus($status);
            $this->assertFalse($result['success']);
            $this->assertFalse($result['expired'], "HTTP {$status} NON deve eliminare la subscription");
            $this->assertNotNull($result['error']);
        }
    }

    public function testServerErrorsAreFailuresNotExpired(): void
    {
        $result = WebPushSender::classifyStatus(500);
        $this->assertFalse($result['success']);
        $this->assertFalse($result['expired']);
        $this->assertNotNull($result['error']);
    }
}
