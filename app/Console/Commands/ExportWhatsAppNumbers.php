<?php

namespace App\Console\Commands;

use App\Models\ClientPhoneNumber;
use App\Models\WhatsAppExportBatch;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExportWhatsAppNumbers extends Command
{
    protected $signature = 'whatsapp:export-numbers
                            {--limit=10000 : Maximum number of numbers to export}
                            {--emirate= : Restrict to a client emirate, e.g. "Dubai"}
                            {--exclude-batch=* : Exclude numbers that appeared in these export batch IDs}
                            {--name= : Batch name recorded for future exclusion (defaults to a dated name)}
                            {--out= : Output CSV path (defaults to storage/app/whatsapp/exports/...)}
                            {--no-batch : Do not record a WhatsAppExportBatch (read-only export)}';

    protected $description = 'Export active/never-messaged valid UAE WhatsApp numbers to CSV, never-messaged first';

    public function handle(): int
    {
        $limit    = max(1, (int) $this->option('limit'));
        $name     = $this->option('name') ?: 'CLI export ' . now()->format('d M Y H:i');
        $record   = ! $this->option('no-batch');
        $emirate  = $this->option('emirate') ?: null;
        $excludeBatchIds = array_filter(array_map('intval', (array) $this->option('exclude-batch')));

        // Eligible = valid UAE mobile (is_uae, national number starts with 5),
        // not WhatsApp-suppressed, and either never messaged (no profile) or
        // profile currently active. Mirrors ListWhatsAppNumbers::applyActiveConditions.
        $base = fn (): Builder => ClientPhoneNumber::query()
            ->where('is_uae', true)
            ->where('national_number', 'like', '5%')
            ->when($emirate, fn (Builder $q) => $q->whereExists(fn ($e) => $e
                ->selectRaw('1')
                ->from('clients')
                ->whereColumn('clients.id', 'client_phone_numbers.client_id')
                ->where('clients.emirate', $emirate)
            ))
            ->when($excludeBatchIds, fn (Builder $q) => $q->whereNotExists(fn ($x) => $x
                ->selectRaw('1')
                ->from('whatsapp_export_batch_numbers')
                ->whereColumn('whatsapp_export_batch_numbers.client_phone_number_id', 'client_phone_numbers.id')
                ->whereIn('whatsapp_export_batch_numbers.whatsapp_export_batch_id', $excludeBatchIds)
            ))
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('contact_suppressions')
                ->whereColumn('contact_suppressions.client_phone_number_id', 'client_phone_numbers.id')
                ->where('contact_suppressions.channel', 'whatsapp')
                ->whereNull('contact_suppressions.released_at')
            )
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('whatsAppProfile')
                ->orWhereHas('whatsAppProfile', fn (Builder $p) => $p->where('usage_status', 'active'))
            );

        // Never-messaged first: take from the never-messaged pool, then top up
        // with active (previously-messaged) numbers only if needed.
        $neverMessaged = $base()
            ->whereDoesntHave('whatsAppProfile')
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('client_phone_numbers.id')
            ->all();

        $ids = $neverMessaged;

        if (count($ids) < $limit) {
            $remaining = $limit - count($ids);
            $topUp = $base()
                ->whereHas('whatsAppProfile')
                ->inRandomOrder()
                ->limit($remaining)
                ->pluck('client_phone_numbers.id')
                ->all();

            $ids = array_merge($ids, $topUp);
        }

        if (empty($ids)) {
            $this->warn('No eligible numbers found.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Selected %s numbers (%s never-messaged, %s active top-up).',
            number_format(count($ids)),
            number_format(count($neverMessaged)),
            number_format(count($ids) - count($neverMessaged)),
        ));

        if ($record) {
            $batch = WhatsAppExportBatch::create([
                'name'            => $name,
                'exported_by'     => null,
                'record_count'    => count($ids),
                'filters_summary' => array_filter([
                    'source'           => 'cli',
                    'scope'            => 'uae_active_or_never_messaged',
                    'ordering'         => 'never_messaged_first',
                    'emirate'          => $emirate,
                    'excluded_batches' => $excludeBatchIds ?: null,
                    'limit'            => $limit,
                ]),
            ]);

            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('whatsapp_export_batch_numbers')->insertOrIgnore(
                    array_map(fn (int $id): array => [
                        'whatsapp_export_batch_id' => $batch->id,
                        'client_phone_number_id'   => $id,
                    ], $chunk)
                );
            }

            $this->info("Recorded export batch #{$batch->id} \"{$name}\".");
        }

        $slug = Str::slug($name);
        $path = $this->option('out')
            ?: storage_path('app/whatsapp/exports/whatsapp_export_' . now()->format('Y-m-d') . ($slug ? "_{$slug}" : '') . '.csv');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, ['phone_number']);

        $written = 0;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $phones = DB::table('client_phone_numbers')
                ->whereIn('id', $chunk)
                ->pluck('normalized_phone');

            foreach ($phones as $phone) {
                fputcsv($handle, [$phone]);
                $written++;
            }
        }

        fclose($handle);

        $this->info("Wrote {$written} numbers to {$path}");

        return self::SUCCESS;
    }
}
