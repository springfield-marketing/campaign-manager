<?php

namespace App\Modules\IVR\Support;

use App\Modules\IVR\Models\CentralDatabaseExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;
use XMLWriter;
use ZipArchive;

class CentralDatabaseExcelExporter
{
    private const MAX_EXCEL_ROWS = 1048576;
    private const PROGRESS_INTERVAL = 1000;

    /**
     * Framework/runtime tables do not help migrate business data and can make
     * backup workbooks noisy or stale.
     */
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'central_database_exports',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
    ];

    public function export(CentralDatabaseExport $export): void
    {
        $disk = Storage::disk('local');
        $directory = 'central-database-exports';
        $tmpDirectory = storage_path('app/private/'.$directory.'/tmp/'.$export->id);
        $fileName = 'central-database-export-'.$export->id.'-'.now()->format('Ymd-His').'.xlsx';
        $relativePath = $directory.'/'.$fileName;
        $absolutePath = storage_path('app/private/'.$relativePath);

        File::ensureDirectoryExists($tmpDirectory);
        File::ensureDirectoryExists(dirname($absolutePath));

        try {
            $tables = $this->tables();
            $totalRows = $this->totalRows($tables);

            $export->update([
                'status' => CentralDatabaseExport::STATUS_PROCESSING,
                'file_name' => $fileName,
                'storage_path' => $relativePath,
                'total_rows' => $totalRows,
                'processed_rows' => 0,
                'error_message' => null,
                'started_at' => now(),
                'summary' => [
                    'tables' => collect($tables)->mapWithKeys(fn (array $table): array => [
                        $table['name'] => ['rows' => $table['rows']],
                    ])->all(),
                ],
            ]);

            $sheets = $this->writeSheets($export, $tables, $tmpDirectory);
            $this->writeWorkbook($absolutePath, $tmpDirectory, $sheets);

            $export->update([
                'status' => CentralDatabaseExport::STATUS_COMPLETED,
                'processed_rows' => $totalRows,
                'file_size' => $disk->size($relativePath),
                'completed_at' => now(),
                'summary' => array_merge($export->summary ?? [], [
                    'sheet_count' => count($sheets),
                    'table_count' => count($tables),
                ]),
            ]);
        } catch (Throwable $throwable) {
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            $export->update([
                'status' => CentralDatabaseExport::STATUS_FAILED,
                'error_message' => $throwable->getMessage(),
                'completed_at' => now(),
            ]);

            throw $throwable;
        } finally {
            File::deleteDirectory($tmpDirectory);
        }
    }

    /**
     * @return array<int, array{name: string, rows: int, columns: array<int, string>}>
     */
    private function tables(): array
    {
        $tableNames = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->whereNotIn('table_name', self::EXCLUDED_TABLES)
            ->orderBy('table_name')
            ->pluck('table_name');

        return $tableNames
            ->map(fn (string $table): array => [
                'name' => $table,
                'rows' => DB::table($table)->count(),
                'columns' => DB::table('information_schema.columns')
                    ->where('table_schema', 'public')
                    ->where('table_name', $table)
                    ->orderBy('ordinal_position')
                    ->pluck('column_name')
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, rows: int, columns: array<int, string>}>  $tables
     */
    private function totalRows(array $tables): int
    {
        return collect($tables)->sum('rows');
    }

    /**
     * @param  array<int, array{name: string, rows: int, columns: array<int, string>}>  $tables
     * @return array<int, array{name: string, path: string}>
     */
    private function writeSheets(CentralDatabaseExport $export, array $tables, string $tmpDirectory): array
    {
        $sheets = [];
        $usedNames = [];
        $processed = 0;

        foreach ($tables as $table) {
            $part = 1;
            $rowInSheet = 0;
            $writer = null;

            foreach (DB::table($table['name'])->select($table['columns'])->cursor() as $record) {
                if ($writer === null || $rowInSheet >= self::MAX_EXCEL_ROWS) {
                    if ($writer !== null) {
                        $this->closeSheet($writer);
                    }

                    $sheetIndex = count($sheets) + 1;
                    $sheetPath = $tmpDirectory.'/sheet'.$sheetIndex.'.xml';
                    $sheetName = $this->uniqueSheetName($table['name'], $part, $usedNames);
                    $sheets[] = ['name' => $sheetName, 'path' => $sheetPath];
                    $writer = $this->openSheet($sheetPath);
                    $rowInSheet = 1;
                    $this->writeRow($writer, $rowInSheet, $table['columns']);
                    $part++;
                }

                $rowInSheet++;
                $this->writeRow($writer, $rowInSheet, array_map(
                    fn (string $column): mixed => $record->{$column},
                    $table['columns'],
                ));

                $processed++;

                if ($processed % self::PROGRESS_INTERVAL === 0) {
                    $export->forceFill(['processed_rows' => $processed])->save();
                }
            }

            if ($writer === null) {
                $sheetIndex = count($sheets) + 1;
                $sheetPath = $tmpDirectory.'/sheet'.$sheetIndex.'.xml';
                $sheetName = $this->uniqueSheetName($table['name'], 1, $usedNames);
                $sheets[] = ['name' => $sheetName, 'path' => $sheetPath];
                $writer = $this->openSheet($sheetPath);
                $this->writeRow($writer, 1, $table['columns']);
            }

            $this->closeSheet($writer);
            $export->forceFill(['processed_rows' => $processed])->save();
        }

        return $sheets;
    }

    private function openSheet(string $path): XMLWriter
    {
        $writer = new XMLWriter();
        $writer->openUri($path);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('worksheet');
        $writer->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $writer->startElement('sheetData');

        return $writer;
    }

    private function closeSheet(XMLWriter $writer): void
    {
        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function writeRow(XMLWriter $writer, int $rowIndex, array $values): void
    {
        $writer->startElement('row');
        $writer->writeAttribute('r', (string) $rowIndex);

        foreach ($values as $index => $value) {
            $writer->startElement('c');
            $writer->writeAttribute('r', $this->columnName($index + 1).$rowIndex);
            $writer->writeAttribute('t', 'inlineStr');
            $writer->startElement('is');
            $writer->writeElement('t', $this->cellValue($value));
            $writer->endElement();
            $writer->endElement();
        }

        $writer->endElement();
    }

    private function cellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_resource($value)) {
            return $this->sanitizeXmlText(stream_get_contents($value) ?: '');
        }

        if (is_array($value) || is_object($value)) {
            return $this->sanitizeXmlText(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        }

        return $this->sanitizeXmlText((string) $value);
    }

    private function sanitizeXmlText(string $value): string
    {
        return preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $sheets
     */
    private function writeWorkbook(string $absolutePath, string $tmpDirectory, array $sheets): void
    {
        $zip = new ZipArchive();

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the Excel export file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));

        foreach ($sheets as $index => $sheet) {
            $zip->addFile($sheet['path'], 'xl/worksheets/sheet'.($index + 1).'.xml');
        }

        $zip->close();
    }

    private function uniqueSheetName(string $table, int $part, array &$usedNames): string
    {
        $base = preg_replace('/[\[\]\:\*\?\/\\\\]/', '_', $table) ?: 'sheet';
        $suffix = $part > 1 ? '_'.$part : '';
        $name = substr($base, 0, 31 - strlen($suffix)).$suffix;
        $counter = 2;

        while (in_array(strtolower($name), $usedNames, true)) {
            $counterSuffix = '_'.$counter;
            $name = substr($base, 0, 31 - strlen($counterSuffix)).$counterSuffix;
            $counter++;
        }

        $usedNames[] = strtolower($name);

        return $name;
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(int $sheetCount): string
    {
        $sheetOverrides = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$sheetOverrides
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  array<int, array{name: string, path: string}>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $sheetXml = '';

        foreach ($sheets as $index => $sheet) {
            $sheetId = $index + 1;
            $sheetXml .= '<sheet name="'.$this->escapeAttribute($sheet['name']).'" sheetId="'.$sheetId.'" r:id="rId'.$sheetId.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetXml.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $relationships .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$relationships
            .'</Relationships>';
    }
}
