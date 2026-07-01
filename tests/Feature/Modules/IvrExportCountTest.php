<?php

namespace Tests\Feature\Modules;

use App\Filament\Resources\IvrNumbers\Pages\ListIvrNumbers;
use App\Models\Client;
use App\Models\ClientPhoneNumber;
use App\Models\ContactSuppression;
use App\Modules\IVR\Models\IvrPhoneProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the query shared by the export modal's live count and the export stream
 * (ListIvrNumbers::eligibleExportQuery). If these ever diverge the count would lie
 * about what the CSV contains, which is the whole point of Option B.
 */
class IvrExportCountTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function number(array $clientAttrs, array $phoneAttrs = []): ClientPhoneNumber
    {
        $national = '50'.str_pad((string) (++$this->seq), 7, '0', STR_PAD_LEFT); // 9 digits, starts 5
        $client = Client::create(array_merge(['full_name' => 'Caller'], $clientAttrs));

        return ClientPhoneNumber::create(array_merge([
            'client_id' => $client->id,
            'raw_phone' => '+971'.$national,
            'normalized_phone' => '+971'.$national,   // 13 chars: +9715XXXXXXXX
            'national_number' => $national,
            'country_code' => '971',
            'detected_country' => 'AE',
            'is_uae' => true,
            'verification_status' => 'verified',
        ], $phoneAttrs));
    }

    private function countMatching(array $filters): int
    {
        $method = new ReflectionMethod(ListIvrNumbers::class, 'eligibleExportQuery');
        $method->setAccessible(true);

        return $method->invoke(null, $filters)->count('client_phone_numbers.normalized_phone');
    }

    #[Test]
    public function the_count_reflects_eligibility_and_the_emirate_filter(): void
    {
        // Eligible: has name, no profile (never called) → ready.
        $this->number(['emirate' => 'Abu Dhabi']);
        // Eligible: active profile, off cooldown.
        $active = $this->number(['emirate' => 'Abu Dhabi']);
        IvrPhoneProfile::create(['client_phone_number_id' => $active->id, 'usage_status' => 'active', 'cooldown_until' => null]);
        // Eligible but different emirate.
        $this->number(['emirate' => 'Dubai']);

        // Eligible: a nameless number is NOT blocked (the name requirement was removed so we can
        // try calling numbers imported without a contact name).
        $this->number(['emirate' => 'Abu Dhabi', 'full_name' => null]);
        // NOT eligible: resting (inactive profile).
        $resting = $this->number(['emirate' => 'Abu Dhabi']);
        IvrPhoneProfile::create(['client_phone_number_id' => $resting->id, 'usage_status' => 'inactive', 'cooldown_until' => null]);
        // NOT eligible: on Do Not Call.
        $dnc = $this->number(['emirate' => 'Abu Dhabi']);
        ContactSuppression::create([
            'client_phone_number_id' => $dnc->id,
            'channel' => 'ivr',
            'reason' => 'manual',
            'suppressed_at' => now(),
        ]);

        // All emirates: three Abu Dhabi-eligible (ready, active, nameless) + the Dubai-eligible = 4.
        $this->assertSame(4, $this->countMatching([]));

        // Filtered to Abu Dhabi: drops the Dubai number = 3.
        $this->assertSame(3, $this->countMatching(['emirate' => ['value' => 'Abu Dhabi']]));
    }
}
