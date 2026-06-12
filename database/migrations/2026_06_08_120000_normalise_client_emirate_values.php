<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Some past imports mapped a "City" CSV column to clients.emirate.
 * Those columns contained district/community names instead of the seven UAE
 * emirate names, so they were stored verbatim.  This migration corrects all
 * known bad values to their proper emirate and nulls anything that cannot be
 * identified with confidence.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const KNOWN = [
        // Abu Dhabi emirate
        'Abu Dhabi District' => 'Abu Dhabi',
        'Abu Dhabi City'     => 'Abu Dhabi',
        'Ras Al Hekma'       => 'Abu Dhabi',

        // Dubai emirate
        'Dubailand District' => 'Dubai',
        'Downtown District'  => 'Dubai',
        'Al Barsha South'    => 'Dubai',
        'Meydan District'    => 'Dubai',
        'Creek District'     => 'Dubai',
        'Dubai Marina'       => 'Dubai',
        'Warsan First'       => 'Dubai',
        'Bur Dubai District' => 'Dubai',
        'Deira District'     => 'Dubai',
    ];

    /** @var list<string> */
    private const VALID = [
        'Abu Dhabi', 'Dubai', 'Sharjah',
        'Ajman', 'Umm Al Quwain', 'Ras Al Khaimah', 'Fujairah',
    ];

    public function up(): void
    {
        foreach (self::KNOWN as $bad => $correct) {
            DB::table('clients')
                ->where('emirate', $bad)
                ->update(['emirate' => $correct]);
        }

        // Anything still not a valid emirate name cannot be mapped confidently.
        DB::table('clients')
            ->whereNotNull('emirate')
            ->whereNotIn('emirate', self::VALID)
            ->update(['emirate' => null]);
    }

    public function down(): void
    {
        // Correction is one-way — the original wrong values are not recoverable.
    }
};
