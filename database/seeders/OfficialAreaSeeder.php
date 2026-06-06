<?php

namespace Database\Seeders;

use App\Models\OfficialArea;
use Illuminate\Database\Seeder;

class OfficialAreaSeeder extends Seeder
{
    public function run(): void
    {
        $dubai = [
            // source_area_id = DLD area ID where known
            ['source_area_id' => 390, 'area_name_en' => 'Burj Khalifa',           'zone_id' => 2],
            ['source_area_id' => 391, 'area_name_en' => 'Business Bay',            'zone_id' => 2],
            ['source_area_id' => 394, 'area_name_en' => 'Marsa Dubai',             'zone_id' => 2], // Dubai Marina
            ['source_area_id' => 395, 'area_name_en' => 'Palm Jumeirah',           'zone_id' => 2],
            ['source_area_id' => 396, 'area_name_en' => 'Al Thanyah Fifth',        'zone_id' => 2], // JLT south
            ['source_area_id' => 397, 'area_name_en' => 'Al Thanyah First',        'zone_id' => 2], // JLT north
            ['source_area_id' => 398, 'area_name_en' => 'Al Thanyah Fourth',       'zone_id' => 2],
            ['source_area_id' => 399, 'area_name_en' => 'Al Thanyah Second',       'zone_id' => 2],
            ['source_area_id' => 400, 'area_name_en' => 'Al Thanyah Third',        'zone_id' => 2],
            ['source_area_id' => 401, 'area_name_en' => 'Hadaeq Sheikh Mohammed Bin Rashid', 'zone_id' => 2], // Dubai Hills
            ['source_area_id' => 402, 'area_name_en' => 'Madinat Al Mataar',       'zone_id' => 2], // Dubai South
            ['source_area_id' => 403, 'area_name_en' => 'Wadi Al Safa 2',          'zone_id' => 2], // Arabian Ranches
            ['source_area_id' => 404, 'area_name_en' => 'Wadi Al Safa 3',          'zone_id' => 2], // Arabian Ranches 2/3
            ['source_area_id' => 405, 'area_name_en' => 'Wadi Al Safa 5',          'zone_id' => 2], // Damac Hills
            ['source_area_id' => 406, 'area_name_en' => 'Meydan One',              'zone_id' => 2],
            ['source_area_id' => 407, 'area_name_en' => 'Meydan Avenue',           'zone_id' => 2],
            ['source_area_id' => 408, 'area_name_en' => 'Sobha Hartland',          'zone_id' => 2],
            ['source_area_id' => 409, 'area_name_en' => 'Tilal Al Ghaf',           'zone_id' => 2],
            ['source_area_id' => 410, 'area_name_en' => 'The Valley',              'zone_id' => 2],
            ['source_area_id' => 411, 'area_name_en' => 'Mudon',                   'zone_id' => 2],
            ['source_area_id' => 412, 'area_name_en' => 'Al Barsha South Fourth',  'zone_id' => 2], // Dubai Science Park
            ['source_area_id' => 413, 'area_name_en' => 'Al Barsha South Fifth',   'zone_id' => 2], // Barsha Heights / Tecom
            ['source_area_id' => 414, 'area_name_en' => 'Al Furjan',               'zone_id' => 2],
            ['source_area_id' => 415, 'area_name_en' => 'Jumeirah Village Circle', 'zone_id' => 2],
            ['source_area_id' => 416, 'area_name_en' => 'Jumeirah Village Triangle','zone_id' => 2],
            ['source_area_id' => 417, 'area_name_en' => 'Dubai Sports City',       'zone_id' => 2],
            ['source_area_id' => 418, 'area_name_en' => 'Motor City',              'zone_id' => 2],
            ['source_area_id' => 419, 'area_name_en' => 'Dubai Studio City',       'zone_id' => 2],
            ['source_area_id' => 420, 'area_name_en' => 'Dubai Production City',   'zone_id' => 2],
            ['source_area_id' => 421, 'area_name_en' => 'Al Hebiah Fourth',        'zone_id' => 2], // Damac Hills 2
            ['source_area_id' => 422, 'area_name_en' => 'Nad Al Sheba',            'zone_id' => 2],
            ['source_area_id' => 423, 'area_name_en' => 'Ras Al Khor',             'zone_id' => 2],
            ['source_area_id' => 424, 'area_name_en' => 'Port Saeed',              'zone_id' => 1],
            ['source_area_id' => 425, 'area_name_en' => 'Deira',                   'zone_id' => 1],
            ['source_area_id' => 426, 'area_name_en' => 'Bur Dubai',               'zone_id' => 1],
            ['source_area_id' => 427, 'area_name_en' => 'Al Karama',               'zone_id' => 1],
            ['source_area_id' => 428, 'area_name_en' => 'Jumeirah',                'zone_id' => 1],
            ['source_area_id' => 465, 'area_name_en' => 'Wadi Al Safa 3',          'zone_id' => 2],
        ];

        foreach ($dubai as $row) {
            OfficialArea::firstOrCreate(
                ['emirate' => 'Dubai', 'area_name_en' => $row['area_name_en']],
                ['source_area_id' => $row['source_area_id'], 'zone_id' => $row['zone_id'], 'is_active' => true]
            );
        }
    }
}
