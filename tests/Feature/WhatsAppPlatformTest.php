<?php

namespace Tests\Feature;

use App\Modules\WhatsApp\Enums\WhatsAppPlatform;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WhatsAppPlatformTest extends TestCase
{
    #[Test]
    public function gupshup_2_uses_the_gupshup_layout_and_is_not_wati(): void
    {
        // The campaign processor routes by isGupshup(), so this is what makes Gupshup 2 (SPL)
        // parse with the same column layout as Gupshup 1.
        $this->assertTrue(WhatsAppPlatform::Gupshup2->isGupshup());
        $this->assertFalse(WhatsAppPlatform::Gupshup2->isWati());

        // Existing platforms are unchanged.
        $this->assertTrue(WhatsAppPlatform::Gupshup1->isGupshup());
        $this->assertFalse(WhatsAppPlatform::Wati1->isGupshup());
        $this->assertTrue(WhatsAppPlatform::Wati1->isWati());
    }

    #[Test]
    public function gupshup_2_appears_in_the_upload_platform_options(): void
    {
        $this->assertSame('Gupshup 2 (SPL)', WhatsAppPlatform::options()['gupshup_2'] ?? null);
    }
}
