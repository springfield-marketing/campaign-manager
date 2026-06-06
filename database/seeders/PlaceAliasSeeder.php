<?php

namespace Database\Seeders;

use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\PlaceAlias;
use Illuminate\Database\Seeder;

class PlaceAliasSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMarketingAreaAliases();
        $this->seedOfficialAreaAliases();
    }

    private function seedMarketingAreaAliases(): void
    {
        $aliases = [
            // Dubai
            'Dubai'           => [
                'Dubai Marina'           => ['Marina', 'DM', 'Dubai Marina UAE'],
                'Jumeirah Lakes Towers'  => ['JLT', 'Jumeirah Lake Towers'],
                'Downtown Dubai'         => ['Downtown', 'DT', 'Burj Khalifa Area', 'Old Town'],
                'Business Bay'           => ['BB', 'Business Bay Dubai'],
                'Palm Jumeirah'          => ['Palm', 'The Palm', 'Palm Island'],
                'Dubai Hills Estate'     => ['Dubai Hills', 'DHE', 'DH'],
                'Dubai South'            => ['Dubai World Central', 'DWC', 'Expo City', 'Expo 2020'],
                'Arabian Ranches'        => ['Arabian Ranches 1', 'AR1', 'AR'],
                'Arabian Ranches 2'      => ['AR2'],
                'Damac Hills'            => ['Akoya Oxygen', 'Akoya', 'Damac Hills 1', 'DH1'],
                'Damac Hills 2'          => ['Akoya Oxygen 2', 'DAMAC Hills 2'],
                'Barsha Heights'         => ['Tecom', 'TECOM', 'Dubai Internet City Area'],
                'Dubai Production City'  => ['IMPZ', 'International Media Production Zone'],
                'Jumeirah Village Circle' => ['JVC'],
                'Jumeirah Village Triangle' => ['JVT'],
                'Dubai Sports City'      => ['DSC', 'Sports City'],
                'Motor City'             => ['Motor City Dubai'],
                'Dubai Studio City'      => ['DSC Studio', 'Studio City'],
                'Meydan'                 => ['Meydan City', 'Meydan One'],
                'Sobha Hartland'         => ['Sobha', 'Hartland'],
                'Tilal Al Ghaf'          => ['TAG', 'Tilal'],
                'Nad Al Sheba'           => ['NAS', 'Nad Al Sheba 1'],
            ],
            // Abu Dhabi
            'Abu Dhabi'       => [
                'Al Reem Island'   => ['Reem Island', 'Reem', 'Al Reem'],
                'Saadiyat Island'  => ['Saadiyat', 'Cultural District'],
                'Yas Island'       => ['Yas', 'Yas Abu Dhabi'],
                'Al Raha Beach'    => ['Raha Beach', 'Al Raha'],
                'Al Maryah Island' => ['Maryah Island', 'Al Maryah'],
                'Khalifa City'     => ['KCA', 'Khalifa City A'],
            ],
        ];

        foreach ($aliases as $emirate => $areaAliases) {
            foreach ($areaAliases as $marketingName => $aliasList) {
                $area = MarketingArea::where('emirate', $emirate)->where('name', $marketingName)->first();

                if (! $area) {
                    continue;
                }

                foreach ($aliasList as $alias) {
                    PlaceAlias::firstOrCreate([
                        'entity_type' => 'marketing_area',
                        'entity_id'   => $area->id,
                        'alias_name'  => $alias,
                    ], [
                        'source'           => 'seed',
                        'confidence_level' => 'high',
                    ]);
                }
            }
        }
    }

    private function seedOfficialAreaAliases(): void
    {
        $aliases = [
            'Dubai' => [
                'Marsa Dubai'    => ['Dubai Marina'],
                'Al Thanyah Fifth' => ['JLT South', 'Jumeirah Lakes Towers South'],
                'Al Thanyah First' => ['JLT North', 'Jumeirah Lakes Towers North'],
                'Burj Khalifa'   => ['Downtown Dubai', 'Old Town'],
                'Al Barsha South Fifth' => ['Barsha Heights', 'Tecom Area'],
                'Dubai Production City' => ['IMPZ Area'],
                'Hadaeq Sheikh Mohammed Bin Rashid' => ['Dubai Hills'],
            ],
        ];

        foreach ($aliases as $emirate => $areaAliases) {
            foreach ($areaAliases as $officialName => $aliasList) {
                $area = OfficialArea::where('emirate', $emirate)->where('area_name_en', $officialName)->first();

                if (! $area) {
                    continue;
                }

                foreach ($aliasList as $alias) {
                    PlaceAlias::firstOrCreate([
                        'entity_type' => 'official_area',
                        'entity_id'   => $area->id,
                        'alias_name'  => $alias,
                    ], [
                        'source'           => 'seed',
                        'confidence_level' => 'high',
                    ]);
                }
            }
        }
    }
}
