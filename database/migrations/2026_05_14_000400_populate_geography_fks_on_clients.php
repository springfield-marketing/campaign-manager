<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Load reference IDs keyed by iso_code / name so we never hardcode PKs.
        $countryIdByIso = DB::table('countries')->pluck('id', 'iso_code')->all();
        $regionIdByName = DB::table('regions')->pluck('id', 'name')->all();
        $communityIdByName = DB::table('communities')->pluck('id', 'name')->all();

        // -----------------------------------------------------------------------
        // 1. country text  →  country_id
        //
        // Each entry maps one or more raw text values that exist in clients.country
        // to a single canonical iso_code. Variants (Saudia, Saudi) are merged here.
        // Values with no iso match (American Samoa, Holy See, Åland Islands) are
        // intentionally absent — those rows stay null.
        // -----------------------------------------------------------------------
        $countryTextToIso = [
            'United Arab Emirates'             => 'AE',
            'South Africa'                     => 'ZA',
            'Saudi Arabia'                     => 'SA',
            'Saudia'                           => 'SA',
            'Saudi'                            => 'SA',
            'Guernsey'                         => 'GG',
            'India'                            => 'IN',
            'Lebanon'                          => 'LB',
            'Russian Federation'               => 'RU',
            'Germany'                          => 'DE',
            'France'                           => 'FR',
            'Israel'                           => 'IL',
            'Egypt'                            => 'EG',
            'Iran (Islamic Republic Of)'       => 'IR',
            'Iran, Islamic Republic Of'        => 'IR',
            'Jordan'                           => 'JO',
            'Holy See'                         => null,   // not seeded — intentionally null
            'Poland'                           => 'PL',
            'Kuwait'                           => 'KW',
            'Bahrain'                          => 'BH',
            'Ukraine'                          => 'UA',
            'Iraq'                             => 'IQ',
            'Switzerland'                      => 'CH',
            'Turkey'                           => 'TR',
            'Netherlands'                      => 'NL',
            'United Kingdom'                   => 'GB',
            'Belgium'                          => 'BE',
            'Spain'                            => 'ES',
            'Pakistan'                         => 'PK',
            'United States'                    => 'US',
            'Kenya'                            => 'KE',
            'Latvia'                           => 'LV',
            'Portugal'                         => 'PT',
            'Kazakhstan'                       => 'KZ',
            'Romania'                          => 'RO',
            'Czech Republic'                   => 'CZ',
            'Canada'                           => 'CA',
            'Sweden'                           => 'SE',
            'Qatar'                            => 'QA',
            'Austria'                          => 'AT',
            'Oman'                             => 'OM',
            'Azerbaijan'                       => 'AZ',
            'Bulgaria'                         => 'BG',
            'Greece'                           => 'GR',
            'Italy'                            => 'IT',
            'Morocco'                          => 'MA',
            'Tunisia'                          => 'TN',
            'Tanzania, United Republic Of'     => 'TZ',
            'Serbia'                           => 'RS',
            'Belarus'                          => 'BY',
            'Cameroon'                         => 'CM',
            'Bangladesh'                       => 'BD',
            'Philippines'                      => 'PH',
            'Cyprus'                           => 'CY',
            'Brazil'                           => 'BR',
            'Denmark'                          => 'DK',
            'Uzbekistan'                       => 'UZ',
            'Congo (Democratic Republic Of The)' => 'CD',
            'Seychelles'                       => 'SC',
            'Nepal'                            => 'NP',
            'Slovakia'                         => 'SK',
            'Colombia'                         => 'CO',
            'Sri Lanka'                        => 'LK',
            'Sudan'                            => 'SD',
            'Benin'                            => 'BJ',
            'Yemen'                            => 'YE',
            'Uganda'                           => 'UG',
            'Armenia'                          => 'AM',
            'Angola'                           => 'AO',
            'Albania'                          => 'AL',
            'Mauritius'                        => 'MU',
            'Singapore'                        => 'SG',
            'Luxembourg'                       => 'LU',
            'Nigeria'                          => 'NG',
            'Norway'                           => 'NO',
            'Libya'                            => 'LY',
            'Japan'                            => 'JP',
            'Ireland'                          => 'IE',
            'Hungary'                          => 'HU',
            'American Samoa'                   => null,   // not seeded — intentionally null
            "Åland Islands"               => null,   // not seeded — intentionally null
        ];

        foreach ($countryTextToIso as $text => $iso) {
            if ($iso === null || ! isset($countryIdByIso[$iso])) {
                continue;
            }
            DB::table('clients')
                ->where('country', $text)
                ->whereNull('country_id')
                ->update(['country_id' => $countryIdByIso[$iso]]);
        }

        // -----------------------------------------------------------------------
        // 2. city text  →  region_id
        //
        // The city column holds UAE district/area names rather than emirate names.
        // Each maps to its parent emirate (a row in regions).
        // -----------------------------------------------------------------------
        $cityToRegion = [
            'Abu Dhabi District'  => 'Abu Dhabi',
            'Abu Dhabi'           => 'Abu Dhabi',
            'Abu Dhabi City'      => 'Abu Dhabi',
            'Ras Al Hekma'        => 'Abu Dhabi',   // western coastal area, Abu Dhabi emirate
            'Dubailand District'  => 'Dubai',
            'Dubai'               => 'Dubai',
            'Downtown District'   => 'Dubai',
            'Al Barsha South'     => 'Dubai',
            'Meydan District'     => 'Dubai',
            'Creek District'      => 'Dubai',
            'Dubai Marina'        => 'Dubai',
            'Warsan First'        => 'Dubai',
            'Expo City Dubai'     => 'Dubai',
            'Sheik Zayed'         => 'Dubai',
            'Bur Dubai District'  => 'Dubai',
            'Deira District'      => 'Dubai',
        ];

        foreach ($cityToRegion as $city => $regionName) {
            if (! isset($regionIdByName[$regionName])) {
                continue;
            }
            DB::table('clients')
                ->where('city', $city)
                ->whereNull('region_id')
                ->update(['region_id' => $regionIdByName[$regionName]]);
        }

        // -----------------------------------------------------------------------
        // 3. community text  →  community_id
        //
        // Variants and duplicates are merged to their canonical community name.
        // -----------------------------------------------------------------------
        $communityTextToCanonical = [
            // Direct matches
            'Palm Jumeirah'             => 'Palm Jumeirah',
            'The Palm Jumeirah'         => 'Palm Jumeirah',     // variant
            'Tilal Al Ghaf'             => 'Tilal Al Ghaf',
            'Jumeirah Golf Estates'     => 'Jumeirah Golf Estates',
            'Dubai Hills Estate'        => 'Dubai Hills Estate',
            'Nad Al Sheba Gardens'      => 'Nad Al Sheba Gardens',
            'District One'              => 'District One',
            'Jumeirah Islands'          => 'Jumeirah Islands',
            'Emaar Oasis'               => 'Emaar Oasis',
            'Al Barari'                 => 'Al Barari',
            'Emirates Hills'            => 'Emirates Hills',
            'Al Reem Island'            => 'Al Reem Island',
            'Palm Jebel Ali'            => 'Palm Jebel Ali',
            'Jumeirah Bay Islands'      => 'Jumeirah Bay Islands',
            'Downtown'                  => 'Downtown Dubai',    // variant
            'Downtown Dubai'            => 'Downtown Dubai',
            'Downtown Burj Dubai'       => 'Downtown Dubai',    // variant
            'Jubail Island'             => 'Jubail Island',
            'La Mer Plots'              => 'La Mer Plots',
            'Al Reef'                   => 'Al Reef',
            'Yas Island'                => 'Yas Island',
            'Al Raha Beach'             => 'Al Raha Beach',
            'Jumeirah Pearl'            => 'Jumeirah Pearl',
            'Saadiyat Island'           => 'Saadiyat Island',
            'Jumeirah'                  => 'Jumeirah',
            'Al Ghadeer'                => 'Al Ghadeer',
            'Hydra Village'             => 'Hydra Village',
            'Abu Dhabi Gate'            => 'Abu Dhabi Gate',
            'Dubai Marina'              => 'Dubai Marina',
            'Dubailand'                 => 'Dubailand',
            'Abu Dhabi Island'          => 'Abu Dhabi Island',
            'Arabian Ranches'           => 'Arabian Ranches',
            'Mina Al Arab'              => 'Mina Al Arab',
            'Mussafah'                  => 'Mussafah',
            'JVC - Jumeirah Village Circle'   => 'Jumeirah Village Circle',  // variant
            'MBR - Mohammad Bin Rashid City'  => 'Mohammed Bin Rashid City', // variant
            'Ramhan Island'             => 'Ramhan Island',
            'Al Fahid Island'           => 'Al Fahid Island',
            'Al Bateen'                 => 'Al Bateen',
            'Town Square Dubai'         => 'Town Square Dubai',
            'Meydan'                    => 'Meydan',
        ];

        foreach ($communityTextToCanonical as $text => $canonical) {
            if (! isset($communityIdByName[$canonical])) {
                continue;
            }
            DB::table('clients')
                ->where('community', $text)
                ->whereNull('community_id')
                ->update(['community_id' => $communityIdByName[$canonical]]);
        }

        // -----------------------------------------------------------------------
        // 4. Derive region_id from community where city was blank
        //
        // If a client has a mapped community_id but still no region_id, we can
        // infer the region from the community's own region.
        // -----------------------------------------------------------------------
        DB::statement('
            UPDATE clients
            SET region_id = communities.region_id
            FROM communities
            WHERE communities.id = clients.community_id
              AND clients.region_id IS NULL
              AND clients.community_id IS NOT NULL
        ');

        // -----------------------------------------------------------------------
        // 5. Derive country_id from region where country text was blank
        //
        // If a client has a mapped region_id but still no country_id, we can
        // infer the country from the region's country.
        // -----------------------------------------------------------------------
        DB::statement('
            UPDATE clients
            SET country_id = regions.country_id
            FROM regions
            WHERE regions.id = clients.region_id
              AND clients.country_id IS NULL
              AND clients.region_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::table('clients')->update([
            'country_id'   => null,
            'region_id'    => null,
            'community_id' => null,
        ]);
    }
};
