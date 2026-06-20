<?php

namespace App\Support\Identity;

/**
 * Single source of truth for classifying a contact "name" string as a stub/placeholder,
 * an institution, or a real personal name.
 *
 * This is the canonical implementation referenced by docs/data-rules/contact-data-spec.md §5.
 * The import path, the data-quality audit, and the review queue must all classify names the
 * same way — divergent copies of this logic are what let IMP-001 (stub-name merges) happen on
 * the WhatsApp path while the IVR path was guarded.
 *
 * Rule INV-1: a name is NEVER an identity key. Stub and institution names in particular must
 * never be used to match/merge one row's identity with another's.
 */
class NameClassifier
{
    /**
     * Source-system / channel labels that have been seen leaking into the "name" column
     * instead of a real contact name (a lead-source field mapped to name by mistake in some
     * export). Matched as substrings against a normalized (lowercased, symbols stripped) form,
     * since the raw values carry varying decoration (e.g. "=✅old Crm | -", "✅pf Call |").
     */
    private const PLACEHOLDER_LABEL_FRAGMENTS = [
        'no name', 'na na', 'guest', 'call summary', 'call inquiry',
        'whatsapp inquiry', 'whatsapp lead', 'whatsapp from', 'property finder', 'pf call', 'pf whatsapp',
        'call from pfinder', 'instagram dm', 'telegram', 'crm form', 'old crm',
        'missed call', 'pflead', 'dubizzle', 'bayut',
    ];

    /**
     * Whole-word tokens that mark a name as an organisation rather than a person. Matched
     * against the tokenized, normalized name.
     */
    private const INSTITUTION_TOKENS = [
        'llc', 'fzco', 'fze', 'fzllc', 'wll', 'plc', 'gmbh', 'sarl', 'pjsc', 'psc',
        'bank', 'properties', 'property', 'developers', 'development', 'holdings', 'investments',
        'investment', 'brokerage', 'brokers', 'realty', 'municipality', 'authority', 'ministry',
        'hotels', 'contracting', 'establishment',
    ];

    /**
     * Legal-form suffixes that appear at the END of an organisation name. These are frequently
     * written with dots ("L.L.C", "P.J.S.C", "F.Z.E"), which the tokenizer splits into separate
     * single letters ("l l c") so the whole-word token check above never sees them. We catch
     * them by collapsing the name to bare alphanumerics and testing the trailing suffix.
     */
    private const LEGAL_FORM_SUFFIXES = [
        'llc', 'fzllc', 'fzco', 'fze', 'wll', 'pjsc', 'psc', 'plc', 'sarl', 'gmbh',
    ];

    /**
     * Multi-word phrases that mark a name as an organisation. Matched as substrings of the
     * normalized name.
     */
    private const INSTITUTION_PHRASES = [
        'real estate', 'general trading', 'property management', 'asset management',
    ];

    /**
     * True when the name looks like a placeholder / garbage value that should always be
     * overwritten by a real imported name and must never be used to match/merge identity.
     *
     * A handful of "super clients" with hundreds of unrelated phone numbers attached were
     * caused by treating names like "No Name", "Guest", "Ahmed Na" (first name + placeholder
     * last name), or a repeated single token ("Finder Finder") as a reliable identity key.
     */
    public static function isStub(string $name): bool
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return true;
        }

        // Explicit DND / Do Not Call placeholders
        if (in_array(strtoupper($trimmed), ['DND', 'DO NOT CALL', 'DO NOT DISTURB', 'N/A', '-', '.', 'UNKNOWN', 'AGENT'], strict: true)) {
            return true;
        }

        // Very short (1–2 real characters, possibly with punctuation)
        if (mb_strlen(preg_replace('/[\s.\-_]/u', '', $trimmed) ?? '') <= 2) {
            return true;
        }

        // Ends with a bare dot/space (e.g. "Ahmed .") — truncated or partial
        if (preg_match('/\.\s*$/', $trimmed)) {
            return true;
        }

        // "Firstname Na" — a placeholder last name ("N/A") concatenated onto a real first name
        if (preg_match('/\bna$/i', $trimmed)) {
            return true;
        }

        // Source/channel label leaked into the name field
        $normalized = self::normalize($trimmed);
        foreach (self::PLACEHOLDER_LABEL_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        // A single distinct word is too weak an identity signal to safely match other rows by.
        // This also catches repeated-word stubs ("Finder Finder", "Tatiana Tatiana", "Pflead
        // Pflead"), where every token is identical — they carry no more signal than one word.
        $words = array_values(array_filter(explode(' ', $normalized), fn ($w) => $w !== ''));
        if (count(array_unique($words)) <= 1) {
            return true;
        }

        return false;
    }

    /**
     * True when the name looks like an organisation (bank, developer, brokerage, LLC, …)
     * rather than a person. Institutions are real entities, but they must not absorb
     * individuals — their shared lines are flagged and excluded from person-merging (spec §5).
     *
     * Stubs are not institutions; callers wanting a single answer should use kind().
     */
    public static function isInstitution(string $name): bool
    {
        $normalized = self::normalize(trim($name));

        if ($normalized === '') {
            return false;
        }

        foreach (self::INSTITUTION_PHRASES as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        $words = array_filter(explode(' ', $normalized), fn ($w) => $w !== '');
        foreach ($words as $word) {
            if (in_array($word, self::INSTITUTION_TOKENS, true)) {
                return true;
            }
        }

        // Trailing legal-form suffix written with dots ("...L.l.c", "...P.J.S.C").
        $collapsed = str_replace(' ', '', $normalized);
        foreach (self::LEGAL_FORM_SUFFIXES as $suffix) {
            if (str_ends_with($collapsed, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Single classification: 'stub' | 'institution' | 'real'. Stub takes precedence
     * (a placeholder that happens to contain an org word is still unusable as identity).
     */
    public static function kind(string $name): string
    {
        if (self::isStub($name)) {
            return 'stub';
        }

        if (self::isInstitution($name)) {
            return 'institution';
        }

        return 'real';
    }

    /**
     * Lowercased, punctuation-stripped, single-spaced form used for substring/token matching.
     */
    private static function normalize(string $value): string
    {
        $normalized = mb_strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $value) ?? '');

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }
}
