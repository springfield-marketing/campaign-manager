<x-filament-panels::page>
    {{ $this->form }}

    <x-filament::section heading="Central Database Export" description="Create a full Excel workbook of the business database for safekeeping or migration.">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="px-3 py-2 font-medium text-gray-500">Requested</th>
                        <th class="px-3 py-2 font-medium text-gray-500">Status</th>
                        <th class="px-3 py-2 font-medium text-gray-500">Progress</th>
                        <th class="px-3 py-2 font-medium text-gray-500">Size</th>
                        <th class="px-3 py-2 font-medium text-gray-500">Requested by</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getDatabaseExports() as $export)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $export->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2 capitalize text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $export->status) }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ number_format((int) $export->processed_rows) }} / {{ number_format((int) $export->total_rows) }}
                                ({{ $export->progressPercent() }}%)
                                @if ($export->status === 'failed' && $export->error_message)
                                    <div class="text-xs text-red-600 mt-1">{{ $export->error_message }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ $export->file_size ? number_format($export->file_size / 1024 / 1024, 2) . ' MB' : '—' }}
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $export->requester?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($export->status === 'completed')
                                    <a href="{{ route('modules.ivr.settings.database-export.download', $export) }}"
                                       class="text-primary-600 hover:underline font-medium">Download</a>
                                @elseif (in_array($export->status, ['pending', 'processing']))
                                    <span class="text-gray-400">Processing…</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-gray-400">No database exports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
