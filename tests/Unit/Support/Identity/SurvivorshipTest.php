<?php

namespace Tests\Unit\Support\Identity;

use App\Support\Identity\SourceTrust;
use App\Support\Identity\Survivorship;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SurvivorshipTest extends TestCase
{
    #[Test]
    public function source_trust_orders_sources_correctly(): void
    {
        $this->assertGreaterThan(SourceTrust::rank('staging_promoted'), SourceTrust::rank('manual'));
        $this->assertGreaterThan(SourceTrust::rank('campaign_result'), SourceTrust::rank('staging_promoted'));
        $this->assertGreaterThan(SourceTrust::rank('raw_import'), SourceTrust::rank('campaign_result'));
        $this->assertGreaterThan(SourceTrust::rank('totally_unknown_type'), SourceTrust::rank('raw_import'));

        $this->assertSame(SourceTrust::DEFAULT_RANK, SourceTrust::rank(null));
        $this->assertSame(SourceTrust::rank('raw_import'), SourceTrust::rank('RAW_IMPORT')); // case-insensitive
        $this->assertTrue(SourceTrust::outranks('manual', 'raw_import'));
        $this->assertFalse(SourceTrust::outranks('raw_import', 'manual'));
    }

    #[Test]
    public function highest_trust_value_wins(): void
    {
        $result = Survivorship::resolve([
            ['value' => 'Dubai',     'trust' => SourceTrust::rank('raw_import')],
            ['value' => 'Abu Dhabi', 'trust' => SourceTrust::rank('manual')],
        ]);

        $this->assertSame('Abu Dhabi', $result['value']);
        $this->assertSame(['Dubai'], $result['alternates']);
    }

    #[Test]
    public function ties_break_to_most_recent_then_most_complete(): void
    {
        $recent = Survivorship::resolve([
            ['value' => 'Old value', 'trust' => 50, 'at' => 100],
            ['value' => 'New value', 'trust' => 50, 'at' => 200],
        ]);
        $this->assertSame('New value', $recent['value']);

        $complete = Survivorship::resolve([
            ['value' => 'Ahmed',          'trust' => 50, 'at' => 100],
            ['value' => 'Ahmed Al Rashid', 'trust' => 50, 'at' => 100],
        ]);
        $this->assertSame('Ahmed Al Rashid', $complete['value']);
    }

    #[Test]
    public function blanks_are_ignored_and_empty_input_yields_null(): void
    {
        $result = Survivorship::resolve([
            ['value' => '',    'trust' => 100],
            ['value' => '   ', 'trust' => 100],
            ['value' => 'Real', 'trust' => 10],
        ]);
        $this->assertSame('Real', $result['value']);

        $empty = Survivorship::resolve([]);
        $this->assertNull($empty['value']);
        $this->assertSame([], $empty['alternates']);
    }

    #[Test]
    public function alternates_exclude_the_winner_and_are_deduplicated(): void
    {
        $result = Survivorship::resolve([
            ['value' => 'Winner', 'trust' => 90],
            ['value' => 'Other',  'trust' => 50],
            ['value' => 'other',  'trust' => 40], // same as 'Other' case-insensitively
            ['value' => 'Winner', 'trust' => 10], // dup of winner
        ]);

        $this->assertSame('Winner', $result['value']);
        $this->assertSame(['Other'], $result['alternates']);
    }

    #[Test]
    public function a_real_name_never_loses_to_a_higher_trust_stub(): void
    {
        // The stub has higher source trust, but a real name must still win for the name field.
        $result = Survivorship::resolveName([
            ['value' => 'No Name',        'trust' => SourceTrust::rank('manual')],
            ['value' => 'Fouad Ghandour', 'trust' => SourceTrust::rank('raw_import')],
        ]);

        $this->assertSame('Fouad Ghandour', $result['value']);
        $this->assertContains('No Name', $result['alternates']);
    }

    #[Test]
    public function when_every_name_is_a_stub_a_stub_is_returned(): void
    {
        $result = Survivorship::resolveName([
            ['value' => 'No Name', 'trust' => 50],
            ['value' => 'Guest',   'trust' => 90],
        ]);

        $this->assertSame('Guest', $result['value']); // higher-trust stub
    }
}
