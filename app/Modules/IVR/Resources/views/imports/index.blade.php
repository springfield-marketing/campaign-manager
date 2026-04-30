<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Raw Import</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-[4px] border border-[#8C8C8C] bg-white px-4 py-3 text-sm text-[#262526]">
                    {{ session('status') }}
                </div>
            @endif

            <div
                class="grid gap-6"
                x-data="importProgress({
                    endpoint: '{{ route('modules.ivr.imports.status') }}',
                    imports: @js($imports->map(fn ($import) => [
                        'id' => $import->id,
                        'status' => $import->status,
                        'status_label' => str_replace('_', ' ', $import->status),
                        'total_rows' => $import->total_rows,
                        'processed_rows' => $import->processed_rows,
                        'successful_rows' => $import->successful_rows,
                        'failed_rows' => $import->failed_rows,
                        'duplicate_rows' => $import->duplicate_rows,
                        'progress' => $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0,
                        'is_active' => in_array($import->status, ['pending', 'processing'], true),
                    ])->values())
                })"
                x-init="start()"
            >
                <section class="ivr-panel bg-white p-5">
                    <h3 class="text-lg font-semibold text-[#0D0D0D]">Upload raw file</h3>
                    <p class="mt-2 text-sm text-[#595859]">
                        Required columns are name and phone. Other columns may be present or omitted, and column order can vary.
                    </p>

                    <form method="POST" action="{{ route('modules.ivr.imports.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="file" :value="__('CSV file')" />
                                <input id="file" name="file" type="file" class="mt-1 block w-full rounded-[4px] border border-[#8C8C8C] bg-white px-3 py-2 text-sm">
                                <x-input-error :messages="$errors->get('file')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="source_name" :value="__('Source name')" />
                                <x-text-input id="source_name" name="source_name" type="text" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('source_name')" class="mt-2" />
                            </div>
                        </div>

                        <x-primary-button>Queue Import</x-primary-button>
                    </form>
                </section>

                <section class="ivr-panel overflow-hidden bg-white">
                    <div class="border-b border-[#D9D9D9] px-5 py-4">
                        <h3 class="text-lg font-semibold text-[#0D0D0D]">Import history</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-[#D9D9D9] text-[#595859]">
                                <tr>
                                    <th class="px-5 py-3 font-medium">File</th>
                                    <th class="px-5 py-3 font-medium">Source</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium">Rows</th>
                                    <th class="px-5 py-3 font-medium">Errors</th>
                                    <th class="px-5 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($imports as $import)
                                    <tr class="border-b border-[#D9D9D9]" x-data="{ item: get({{ $import->id }}) }">
                                        <td class="px-5 py-3">{{ $import->original_file_name }}</td>
                                        <td class="px-5 py-3">{{ $import->source_name ?: '-' }}</td>
                                        <td class="px-5 py-3 capitalize" x-text="item.status_label"></td>
                                        <td class="px-5 py-3">
                                            <div class="min-w-40">
                                                <div class="mb-1 flex items-center justify-between gap-3 text-xs text-[#595859]">
                                                    <span x-text="`${item.processed_rows} / ${item.total_rows || '-'}`"></span>
                                                    <span x-text="`${item.progress}%`"></span>
                                                </div>
                                                <div class="h-2 rounded-[4px] bg-[#D9D9D9]">
                                                    <div class="h-2 rounded-[4px] bg-[#262526]" :style="`width: ${item.progress}%`"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3" x-text="`${item.failed_rows} failed`"></td>
                                        <td class="px-5 py-3">
                                            <a href="{{ route('modules.ivr.imports.show', $import) }}" class="text-[#262526]">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-6 text-[#595859]">No imports yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4">
                        {{ $imports->links() }}
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
