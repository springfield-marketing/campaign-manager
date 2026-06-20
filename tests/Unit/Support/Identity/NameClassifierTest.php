<?php

namespace Tests\Unit\Support\Identity;

use App\Support\Identity\NameClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NameClassifierTest extends TestCase
{
    #[Test]
    #[DataProvider('stubNames')]
    public function it_flags_stub_names(string $name): void
    {
        $this->assertTrue(NameClassifier::isStub($name), "Expected \"{$name}\" to be a stub.");
    }

    public static function stubNames(): array
    {
        return [
            'empty'                => [''],
            'whitespace'           => ['   '],
            'dnd'                  => ['DND'],
            'do not call'          => ['Do Not Call'],
            'n/a'                  => ['N/A'],
            'unknown'              => ['UNKNOWN'],
            'agent'                => ['AGENT'],
            'one or two chars'     => ['Jo'],
            'trailing dot'         => ['Ahmed .'],
            'firstname + na'       => ['Ahmed Na'],
            'no name label'        => ['No Name'],
            'instagram dm label'   => ['✅ Instagram Dm |'],
            'pf call label'        => ['✅pf Call |'],
            'old crm label'        => ['=✅old Crm | -'],
            'single word'          => ['Ahmed'],
            // strengthened: repeated-word stubs
            'repeated word finder' => ['Finder Finder'],
            'repeated word name'   => ['Tatiana Tatiana'],
            'repeated pflead'      => ['Pflead Pflead'],
            'repeated case-insens' => ['Guest GUEST'],
            // strengthened: newly-added leaked-source labels
            'whatsapp from bayut'  => ['Whatsapp From Bayut'],
            'missed call'          => ['Missed Call'],
            'dubizzle lead'        => ['Dubizzle Lead'],
        ];
    }

    #[Test]
    #[DataProvider('realNames')]
    public function it_does_not_flag_real_names(string $name): void
    {
        $this->assertFalse(NameClassifier::isStub($name), "Expected \"{$name}\" to be a real name.");
    }

    public static function realNames(): array
    {
        return [
            'two part'         => ['John Smith'],
            'arabic style'     => ['Mohammed Al Rashid'],
            'three part'       => ['Fouad George Ghandour'],
            'hyphenated'       => ['Anne-Marie Cooper'],
            'distinct repeat'  => ['John John Smith'], // 2 distinct tokens -> not a repeated-word stub
        ];
    }

    #[Test]
    #[DataProvider('institutions')]
    public function it_flags_institutions(string $name): void
    {
        $this->assertTrue(NameClassifier::isInstitution($name), "Expected \"{$name}\" to be an institution.");
        $this->assertFalse(NameClassifier::isStub($name), "Institution \"{$name}\" should not also be a stub.");
        $this->assertSame('institution', NameClassifier::kind($name));
    }

    public static function institutions(): array
    {
        return [
            'bank'        => ['Emirates Islamic Bank'],
            'properties'  => ['Damac Properties'],
            'real estate' => ['Allsopp & Allsopp Real Estate'],
            'llc suffix'  => ['Acme Trading LLC'],
            'developers'  => ['Nakheel Developers'],
            'authority'   => ['Dubai Land Authority'],
            // IMP-003: dotted legal suffixes tokenize to single letters ("l l c") and must still
            // be caught by the trailing-suffix check. These names were absorbing 1000s of units.
            'dotted llc'        => ['Select Global Development L.l.c'],
            'dotted llc + word' => ['Damac Canal One Property Development L.l.c'],
            'dotted pjsc'       => ['Deyaar Development (p.j.s.c)'],
            'singular property' => ['Island Oasis Property'],
        ];
    }

    #[Test]
    public function a_real_person_is_neither_stub_nor_institution(): void
    {
        $this->assertSame('real', NameClassifier::kind('Fouad Ghandour'));
        $this->assertFalse(NameClassifier::isInstitution('Fouad Ghandour'));
    }

    #[Test]
    public function stub_takes_precedence_over_institution_in_kind(): void
    {
        // A placeholder that happens to contain an org word is still unusable as identity.
        $this->assertSame('stub', NameClassifier::kind('Bank'));
    }
}
