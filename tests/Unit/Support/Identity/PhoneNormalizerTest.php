<?php

namespace Tests\Unit\Support\Identity;

use App\Modules\IVR\Support\PhoneNormalizer as LegacyIvrNormalizer;
use App\Support\Identity\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    private function normalizer(): PhoneNormalizer
    {
        return new PhoneNormalizer();
    }

    #[Test]
    #[DataProvider('uaeFormats')]
    public function it_normalizes_uae_numbers_to_canonical_e164(string $input, string $expected): void
    {
        $result = $this->normalizer()->normalize($input);
        $this->assertSame($expected, $result['normalized']);
        $this->assertTrue($result['is_uae']);
        $this->assertSame('AE', $result['detected_country']);
    }

    public static function uaeFormats(): array
    {
        return [
            'local 05x'         => ['0527948163', '+971527948163'],
            'bare 5x'           => ['527948163', '+971527948163'],
            '971 prefix'        => ['971527948163', '+971527948163'],
            'plus 971'          => ['+971527948163', '+971527948163'],
            '00 971'            => ['00971527948163', '+971527948163'],
            'spaced'            => ['+971 52 794 8163', '+971527948163'],
            // libphonenumber resolves a bare UAE landline (the old hand-rolled normalizer could
            // not — it left "043905067" as a non-UAE "+043905067").
            'landline 04'       => ['043905067', '+97143905067'],
            // leaked domestic-dialling 0 between 971 and the landline.
            'leaked-0 landline' => ['971043905067', '+97143905067'],
        ];
    }

    #[Test]
    #[DataProvider('uaeParityFormats')]
    public function canonical_matches_the_legacy_ivr_normalizer_on_valid_uae_numbers(string $input): void
    {
        // The identity field (`normalized`) must not drift when we cut imports over to the
        // canonical normalizer — otherwise existing matches would break. Parity is asserted on
        // the mobile / 971-prefixed formats the legacy normalizer actually handled.
        $legacy    = (new LegacyIvrNormalizer())->normalize($input)['normalized'];
        $canonical = $this->normalizer()->normalize($input)['normalized'];

        $this->assertSame($legacy, $canonical, "normalized() diverged for input \"{$input}\"");
    }

    public static function uaeParityFormats(): array
    {
        return [
            ['0527948163'], ['527948163'], ['971527948163'], ['+971527948163'],
            ['00971527948163'], ['971043905067'], ['0568349217'],
        ];
    }

    #[Test]
    public function it_normalizes_international_numbers_with_correct_country_code(): void
    {
        $uk = $this->normalizer()->normalize('+447825594103');
        $this->assertSame('+447825594103', $uk['normalized']);
        $this->assertSame('44', $uk['country_code']);   // libphonenumber gets this right (legacy guessed "447")
        $this->assertFalse($uk['is_uae']);
        $this->assertNotSame('', $uk['detected_country']); // a real +44 region (GB/GG/JE/IM)
    }

    #[Test]
    #[DataProvider('junkInputs')]
    public function it_rejects_scientific_notation_and_placeholders(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer()->normalize($input);
    }

    public static function junkInputs(): array
    {
        return [
            'scientific notation' => ['9.71E+11'],
            'trailing zeros'      => ['971500000000'],
            'all same digit'      => ['971555555555'],
            'sequential run'      => ['0501234567'],
            'empty'               => ['   '],
        ];
    }

    /**
     * The manual "push anyway" override (allowPlaceholder) lets a human-confirmed real number —
     * e.g. a vanity number — through the placeholder guard, while libphonenumber validity still
     * applies. See the import-error "Push anyway" action.
     */
    #[Test]
    #[DataProvider('vanityNumbers')]
    public function allow_placeholder_lets_a_confirmed_number_through(string $input): void
    {
        // Rejected by default...
        try {
            $this->normalizer()->normalize($input);
            $this->fail("Expected \"{$input}\" to be rejected as a placeholder.");
        } catch (\InvalidArgumentException) {
            // expected
        }

        // ...but accepted with the override.
        $result = $this->normalizer()->normalize($input, lenient: false, allowPlaceholder: true);
        $this->assertSame($input, $result['normalized']);
    }

    public static function vanityNumbers(): array
    {
        return [
            'repeated 8s' => ['+971568888888'],
            'repeated 5s' => ['+971555551655'],
        ];
    }
}
