<?php

namespace Database\Seeders;

use App\Models\MarketingArea;
use App\Models\OfficialArea;
use Illuminate\Database\Seeder;

class MarketingAreaSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDubai();
        $this->seedAbuDhabi();
    }

    private function seedDubai(): void
    {
        $areas = [
            'Dubai Marina'           => ['Marsa Dubai'],
            'Jumeirah Lakes Towers'  => ['Al Thanyah Fifth', 'Al Thanyah First'],
            'Downtown Dubai'         => ['Burj Khalifa'],
            'Business Bay'           => ['Business Bay'],
            'Palm Jumeirah'          => ['Palm Jumeirah'],
            'Dubai Hills Estate'     => ['Hadaeq Sheikh Mohammed Bin Rashid'],
            'Dubai South'            => ['Madinat Al Mataar'],
            'Arabian Ranches'        => ['Wadi Al Safa 2'],
            'Arabian Ranches 2'      => ['Wadi Al Safa 3'],
            'Damac Hills'            => ['Wadi Al Safa 5'],
            'Damac Hills 2'          => ['Al Hebiah Fourth'],
            'Meydan'                 => ['Meydan One', 'Meydan Avenue'],
            'Sobha Hartland'         => ['Sobha Hartland'],
            'Tilal Al Ghaf'          => ['Tilal Al Ghaf'],
            'The Valley'             => ['The Valley'],
            'Mudon'                  => ['Mudon'],
            'Barsha Heights'         => ['Al Barsha South Fifth'],
            'Al Furjan'              => ['Al Furjan'],
            'Jumeirah Village Circle'  => ['Jumeirah Village Circle'],
            'Jumeirah Village Triangle' => ['Jumeirah Village Triangle'],
            'Dubai Sports City'      => ['Dubai Sports City'],
            'Motor City'             => ['Motor City'],
            'Dubai Studio City'      => ['Dubai Studio City'],
            'Dubai Production City'  => ['Dubai Production City'],
            'Nad Al Sheba'           => ['Nad Al Sheba'],
            'Ras Al Khor'            => ['Ras Al Khor'],
            'Jumeirah'               => ['Jumeirah'],
            'Deira'                  => ['Deira'],
            'Bur Dubai'              => ['Bur Dubai'],
        ];

        foreach ($areas as $marketingName => $officialNames) {
            $marketing = MarketingArea::firstOrCreate(
                ['emirate' => 'Dubai', 'name' => $marketingName],
                ['is_active' => true]
            );

            foreach ($officialNames as $officialName) {
                $official = OfficialArea::where('emirate', 'Dubai')
                    ->where('area_name_en', $officialName)
                    ->first();

                if ($official && ! $marketing->officialAreas()->where('official_area_id', $official->id)->exists()) {
                    $marketing->officialAreas()->attach($official->id, ['confidence_level' => 'high']);
                }
            }
        }
    }

    private function seedAbuDhabi(): void
    {
        // Abu Dhabi marketing areas without official area mappings for now
        $areas = [
            'Al Reem Island',
            'Saadiyat Island',
            'Yas Island',
            'Al Raha Beach',
            'Al Reef',
            'Khalifa City',
            'Al Shamkhah',
            'Zayed City',
            'Ramhan Island',
            'Al Maryah Island',
            'Masdar City',
            'Mohammed Bin Zayed City',
            'Shakhbout City',
            'Al Ghadeer',
            'Hydra Village',
            'Al Samha',
            'Al Falah',
            'Alghadeer',
        ];

        foreach ($areas as $name) {
            MarketingArea::firstOrCreate(
                ['emirate' => 'Abu Dhabi', 'name' => $name],
                ['is_active' => true]
            );
        }
    }
}
