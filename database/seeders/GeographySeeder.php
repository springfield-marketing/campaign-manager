<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        // -----------------------------------------------------------------------
        // Countries
        // -----------------------------------------------------------------------
        $countries = [
            ['name' => 'United Arab Emirates', 'iso_code' => 'AE'],
            ['name' => 'South Africa',         'iso_code' => 'ZA'],
            ['name' => 'Saudi Arabia',          'iso_code' => 'SA'],
            ['name' => 'India',                 'iso_code' => 'IN'],
            ['name' => 'Lebanon',               'iso_code' => 'LB'],
            ['name' => 'Russian Federation',    'iso_code' => 'RU'],
            ['name' => 'Germany',               'iso_code' => 'DE'],
            ['name' => 'France',                'iso_code' => 'FR'],
            ['name' => 'Israel',                'iso_code' => 'IL'],
            ['name' => 'Egypt',                 'iso_code' => 'EG'],
            ['name' => 'Iran',                  'iso_code' => 'IR'],
            ['name' => 'Jordan',                'iso_code' => 'JO'],
            ['name' => 'Kuwait',                'iso_code' => 'KW'],
            ['name' => 'Poland',                'iso_code' => 'PL'],
            ['name' => 'Bahrain',               'iso_code' => 'BH'],
            ['name' => 'Ukraine',               'iso_code' => 'UA'],
            ['name' => 'Iraq',                  'iso_code' => 'IQ'],
            ['name' => 'Switzerland',           'iso_code' => 'CH'],
            ['name' => 'Turkey',                'iso_code' => 'TR'],
            ['name' => 'Netherlands',           'iso_code' => 'NL'],
            ['name' => 'United Kingdom',        'iso_code' => 'GB'],
            ['name' => 'Belgium',               'iso_code' => 'BE'],
            ['name' => 'Spain',                 'iso_code' => 'ES'],
            ['name' => 'Pakistan',              'iso_code' => 'PK'],
            ['name' => 'United States',         'iso_code' => 'US'],
            ['name' => 'Kenya',                 'iso_code' => 'KE'],
            ['name' => 'Latvia',                'iso_code' => 'LV'],
            ['name' => 'Portugal',              'iso_code' => 'PT'],
            ['name' => 'Kazakhstan',            'iso_code' => 'KZ'],
            ['name' => 'Romania',               'iso_code' => 'RO'],
            ['name' => 'Czech Republic',        'iso_code' => 'CZ'],
            ['name' => 'Canada',                'iso_code' => 'CA'],
            ['name' => 'Sweden',                'iso_code' => 'SE'],
            ['name' => 'Qatar',                 'iso_code' => 'QA'],
            ['name' => 'Austria',               'iso_code' => 'AT'],
            ['name' => 'Oman',                  'iso_code' => 'OM'],
            ['name' => 'Azerbaijan',            'iso_code' => 'AZ'],
            ['name' => 'Bulgaria',              'iso_code' => 'BG'],
            ['name' => 'Greece',                'iso_code' => 'GR'],
            ['name' => 'Italy',                 'iso_code' => 'IT'],
            ['name' => 'Morocco',               'iso_code' => 'MA'],
            ['name' => 'Tunisia',               'iso_code' => 'TN'],
            ['name' => 'Tanzania',              'iso_code' => 'TZ'],
            ['name' => 'Serbia',                'iso_code' => 'RS'],
            ['name' => 'Belarus',               'iso_code' => 'BY'],
            ['name' => 'Cameroon',              'iso_code' => 'CM'],
            ['name' => 'Bangladesh',            'iso_code' => 'BD'],
            ['name' => 'Philippines',           'iso_code' => 'PH'],
            ['name' => 'Cyprus',                'iso_code' => 'CY'],
            ['name' => 'Brazil',                'iso_code' => 'BR'],
            ['name' => 'Denmark',               'iso_code' => 'DK'],
            ['name' => 'Uzbekistan',            'iso_code' => 'UZ'],
            ['name' => 'Slovakia',              'iso_code' => 'SK'],
            ['name' => 'Colombia',              'iso_code' => 'CO'],
            ['name' => 'Sri Lanka',             'iso_code' => 'LK'],
            ['name' => 'Sudan',                 'iso_code' => 'SD'],
            ['name' => 'Benin',                 'iso_code' => 'BJ'],
            ['name' => 'Yemen',                 'iso_code' => 'YE'],
            ['name' => 'Uganda',                'iso_code' => 'UG'],
            ['name' => 'Armenia',               'iso_code' => 'AM'],
            ['name' => 'Angola',                'iso_code' => 'AO'],
            ['name' => 'Albania',               'iso_code' => 'AL'],
            ['name' => 'Mauritius',             'iso_code' => 'MU'],
            ['name' => 'Singapore',             'iso_code' => 'SG'],
            ['name' => 'Luxembourg',            'iso_code' => 'LU'],
            ['name' => 'Nigeria',               'iso_code' => 'NG'],
            ['name' => 'Norway',                'iso_code' => 'NO'],
            ['name' => 'Libya',                 'iso_code' => 'LY'],
            ['name' => 'Japan',                 'iso_code' => 'JP'],
            ['name' => 'Ireland',               'iso_code' => 'IE'],
            ['name' => 'Hungary',               'iso_code' => 'HU'],
            ['name' => 'Nepal',                 'iso_code' => 'NP'],
            ['name' => 'Seychelles',            'iso_code' => 'SC'],
            ['name' => 'Guernsey',              'iso_code' => 'GG'],
            ['name' => 'Congo',                 'iso_code' => 'CD'],
        ];

        foreach ($countries as $data) {
            Country::firstOrCreate(['iso_code' => $data['iso_code']], ['name' => $data['name']]);
        }

        // -----------------------------------------------------------------------
        // UAE regions (emirates)
        // -----------------------------------------------------------------------
        $uae = Country::where('iso_code', 'AE')->first();

        $emirates = [
            'Abu Dhabi',
            'Dubai',
            'Sharjah',
            'Ras Al Khaimah',
            'Ajman',
            'Umm Al Quwain',
            'Fujairah',
        ];

        $regionIds = [];
        foreach ($emirates as $name) {
            $region = Region::firstOrCreate(['country_id' => $uae->id, 'name' => $name]);
            $regionIds[$name] = $region->id;
        }

        // -----------------------------------------------------------------------
        // Communities — canonical names mapped to their emirate.
        // Variants / duplicates from the old text column are handled in Phase 2
        // (the data migration), not here.
        // -----------------------------------------------------------------------
        $communities = [
            // Dubai
            'Dubai' => [
                'Palm Jumeirah',
                'Tilal Al Ghaf',
                'Jumeirah Golf Estates',
                'Dubai Hills Estate',
                'Nad Al Sheba Gardens',
                'District One',
                'Jumeirah Islands',
                'Emaar Oasis',
                'Al Barari',
                'Emirates Hills',
                'Palm Jebel Ali',
                'Jumeirah Bay Islands',
                'Downtown Dubai',
                'La Mer Plots',
                'Jumeirah Pearl',
                'Jumeirah',
                'Dubai Marina',
                'Dubailand',
                'Arabian Ranches',
                'Jumeirah Village Circle',
                'Mohammed Bin Rashid City',
                'Town Square Dubai',
                'Meydan',
                'Expo City Dubai',
            ],

            // Abu Dhabi
            'Abu Dhabi' => [
                'Al Reem Island',
                'Jubail Island',
                'Al Reef',
                'Yas Island',
                'Al Raha Beach',
                'Saadiyat Island',
                'Al Ghadeer',
                'Hydra Village',
                'Abu Dhabi Gate',
                'Abu Dhabi Island',
                'Ramhan Island',
                'Al Fahid Island',
                'Al Bateen',
                'Mussafah',
            ],

            // Ras Al Khaimah
            'Ras Al Khaimah' => [
                'Mina Al Arab',
            ],
        ];

        foreach ($communities as $emirate => $names) {
            $regionId = $regionIds[$emirate];
            foreach ($names as $name) {
                Community::firstOrCreate(
                    ['region_id' => $regionId, 'name' => $name],
                );
            }
        }
    }
}
