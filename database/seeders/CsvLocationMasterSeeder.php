<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\MarketingArea;
use App\Models\OfficialArea;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;
use SplFileObject;

class CsvLocationMasterSeeder extends Seeder
{
    /** @var array<string, int|null> */
    private array $officialAreaIds = [];

    /** @var array<string, int|null> */
    private array $marketingAreaIds = [];

    /** @var array<string, int|null> */
    private array $projectIds = [];

    public function run(): void
    {
        DB::disableQueryLog();

        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        $this->seedOfficialAreas();
        $this->seedMarketingAreas();
        $this->seedProjects();
        $this->dedupeProjects();
        $this->seedBuildings();
        $this->dedupeBuildings();
    }

    private function seedOfficialAreas(): void
    {
        foreach ($this->readCsv(base_path('official_areas_seed.csv')) as $row) {
            $emirate = $this->clean($row['emirate'] ?? null);
            $name = $this->clean($row['area_name_en'] ?? null);

            if ($emirate === null || $name === null) {
                continue;
            }

            $officialArea = OfficialArea::updateOrCreate(
                ['emirate' => $emirate, 'area_name_en' => $name],
                [
                    'source_area_id' => $this->integer($row['source_area_id'] ?? null),
                    'zone_id' => $this->integer($row['zone_id'] ?? null),
                    'is_active' => $this->boolean($row['is_active'] ?? null),
                ],
            );

            $this->officialAreaIds[$this->key($emirate, $name)] = $officialArea->id;
        }
    }

    private function seedMarketingAreas(): void
    {
        foreach ($this->readCsv(base_path('marketing_areas_seed.csv')) as $row) {
            $emirate = $this->clean($row['emirate'] ?? null);
            $name = $this->clean($row['marketing_area_name'] ?? null);

            if ($emirate === null || $name === null) {
                continue;
            }

            $marketingArea = MarketingArea::updateOrCreate(
                ['emirate' => $emirate, 'name' => $name],
                ['is_active' => $this->boolean($row['is_active'] ?? null)],
            );

            $this->marketingAreaIds[$this->key($emirate, $name)] = $marketingArea->id;

            $officialAreaName = $this->clean($row['official_area_name'] ?? null);
            $officialAreaId = $officialAreaName ? $this->officialAreaIds[$this->key($emirate, $officialAreaName)] ?? null : null;

            if ($officialAreaId && ! $marketingArea->officialAreas()->where('official_area_id', $officialAreaId)->exists()) {
                $marketingArea->officialAreas()->attach($officialAreaId, ['confidence_level' => 'high']);
            }
        }
    }

    private function seedProjects(): void
    {
        foreach ($this->readCsv(base_path('projects_seed.csv')) as $row) {
            $emirate = $this->clean($row['emirate'] ?? null);
            $name = $this->clean($row['project_name'] ?? null);
            $marketingAreaName = $this->clean($row['marketing_area_name'] ?? null);

            if ($emirate === null || $name === null || $marketingAreaName === null) {
                continue;
            }

            $officialAreaName = $this->clean($row['official_area_name'] ?? null);
            $marketingAreaId = $this->marketingAreaIds[$this->key($emirate, $marketingAreaName)] ?? null;
            $officialAreaId = $officialAreaName ? $this->officialAreaIds[$this->key($emirate, $officialAreaName)] ?? null : null;

            $project = Project::query()
                ->where('emirate', $emirate)
                ->where('marketing_area_id', $marketingAreaId)
                ->whereRaw('lower(name) = lower(?)', [$name])
                ->first();

            $values = [
                'official_area_id' => $officialAreaId,
                'developer_name' => $this->clean($row['developer_name'] ?? null),
                'dld_project_id' => $this->integer($row['dld_project_id'] ?? null),
                'is_active' => $this->boolean($row['is_active'] ?? null),
            ];

            if ($project) {
                $project->forceFill($values)->save();
            } else {
                $project = Project::create(array_merge($values, [
                    'emirate' => $emirate,
                    'name' => $name,
                    'marketing_area_id' => $marketingAreaId,
                ]));
            }

            $this->projectIds[$this->key($emirate, $marketingAreaName, $name)] = $project->id;
        }
    }

    private function dedupeProjects(): void
    {
        $duplicates = DB::table('projects')
            ->selectRaw('emirate, marketing_area_id, lower(name) as lower_name, min(id) as keep_id, array_agg(id order by id) as ids, count(*) as total')
            ->groupBy('emirate', 'marketing_area_id', DB::raw('lower(name)'))
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = $this->postgresArrayToIntegers((string) $duplicate->ids);
            $keepId = (int) $duplicate->keep_id;
            $removeIds = array_values(array_diff($ids, [$keepId]));

            if ($removeIds === []) {
                continue;
            }

            DB::table('buildings')->whereIn('project_id', $removeIds)->update(['project_id' => $keepId]);
            DB::table('ownerships')->whereIn('project_id', $removeIds)->update(['project_id' => $keepId]);
            DB::table('projects')->whereIn('id', $removeIds)->delete();
        }

        $this->reloadProjectIds();
    }

    private function seedBuildings(): void
    {
        foreach ($this->readCsv(base_path('buildings_seed.csv')) as $row) {
            $emirate = $this->clean($row['emirate'] ?? null);
            $name = $this->clean($row['building_name'] ?? null);
            $projectName = $this->clean($row['project_name'] ?? null);
            $marketingAreaName = $this->clean($row['marketing_area_name'] ?? null);

            if ($emirate === null || $name === null || $projectName === null || $marketingAreaName === null) {
                continue;
            }

            $officialAreaName = $this->clean($row['official_area_name'] ?? null);
            $marketingAreaId = $this->marketingAreaIds[$this->key($emirate, $marketingAreaName)] ?? null;
            $officialAreaId = $officialAreaName ? $this->officialAreaIds[$this->key($emirate, $officialAreaName)] ?? null : null;
            $projectId = $this->projectIds[$this->key($emirate, $marketingAreaName, $projectName)] ?? null;

            Building::updateOrCreate(
                ['project_id' => $projectId, 'name' => $name],
                [
                    'emirate' => $emirate,
                    'marketing_area_id' => $marketingAreaId,
                    'official_area_id' => $officialAreaId,
                    'is_active' => $this->boolean($row['is_active'] ?? null),
                ],
            );
        }
    }

    private function dedupeBuildings(): void
    {
        $duplicates = DB::table('buildings')
            ->selectRaw('project_id, lower(name) as lower_name, min(id) as keep_id, array_agg(id order by id) as ids, count(*) as total')
            ->groupBy('project_id', DB::raw('lower(name)'))
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = $this->postgresArrayToIntegers((string) $duplicate->ids);
            $keepId = (int) $duplicate->keep_id;
            $removeIds = array_values(array_diff($ids, [$keepId]));

            if ($removeIds === []) {
                continue;
            }

            DB::table('ownerships')->whereIn('building_id', $removeIds)->update(['building_id' => $keepId]);
            DB::table('buildings')->whereIn('id', $removeIds)->delete();
        }
    }

    private function reloadProjectIds(): void
    {
        $this->projectIds = [];

        $projects = Project::query()
            ->with('marketingArea:id,emirate,name')
            ->get(['id', 'emirate', 'marketing_area_id', 'name']);

        foreach ($projects as $project) {
            if (! $project->marketingArea) {
                continue;
            }

            $this->projectIds[$this->key($project->emirate, $project->marketingArea->name, $project->name)] = $project->id;
        }
    }

    /**
     * @return \Generator<int, array<string, string|null>>
     */
    private function readCsv(string $path): \Generator
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
        $file->setCsvControl(',', '"', '');

        $header = null;

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $row);
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
                continue;
            }

            $mapped = [];
            foreach ($header as $index => $column) {
                $mapped[$column] = isset($row[$index]) ? (string) $row[$index] : null;
            }

            yield $mapped;
        }
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function integer(?string $value): ?int
    {
        $value = $this->clean($value);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    private function boolean(?string $value): bool
    {
        $value = strtolower((string) $this->clean($value));

        return ! in_array($value, ['0', 'false', 'no'], true);
    }

    private function key(string ...$values): string
    {
        return implode('|', array_map(fn (string $value): string => mb_strtolower(trim($value)), $values));
    }

    /**
     * @return array<int, int>
     */
    private function postgresArrayToIntegers(string $value): array
    {
        $value = trim($value, '{}');

        if ($value === '') {
            return [];
        }

        return array_map('intval', explode(',', $value));
    }
}
