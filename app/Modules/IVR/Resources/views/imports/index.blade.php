<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Raw Import</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">
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
                        'is_active' => in_array($import->status, ['pending', 'processing', 'reverting'], true),
                    ])->values())
                })"
                x-init="start()"
            >
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">Upload raw file</h3>
                    <p class="mt-2 text-sm ui-muted">
                        Required columns are name and phone. Other columns may be present or omitted, and column order can vary.
                    </p>

                    <div class="mt-4 grid gap-3 rounded border border-[var(--line)] bg-theme-subtle p-4 text-sm md:grid-cols-2">
                        <div>
                            <p class="font-medium text-theme-primary">Accepted CSV format</p>
                            <p class="mt-1 ui-muted">
                                The file must include a header row. Required columns are <strong>name</strong> and <strong>phone</strong>.
                                Accepted phone headers include phone, mobile, phone number, or contact number.
                            </p>
                        </div>

                        <div>
                            <p class="font-medium text-theme-primary">Optional columns</p>
                            <p class="mt-1 ui-muted">
                                You may also include email, country, nationality, community, resident, city, gender, interest, and source.
                                Phone numbers can be UAE local or international format, such as 0501234567 or +971501234567.
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <p class="font-medium text-theme-primary">Example header</p>
                            <code class="mt-1 block overflow-x-auto whitespace-nowrap text-xs text-theme-secondary">
                                name,phone,email,city,nationality,interest,source
                            </code>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('modules.ivr.imports.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="file" :value="__('CSV file')" />
                                <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
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

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">Import history</h3>
                    </div>

                    <div class="ui-divide max-h-[560px] overflow-y-auto">
                        @forelse ($imports as $import)
                            <div class="px-5 py-4 text-sm" x-data="{ item: get({{ $import->id }}) }">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                        <p class="ui-muted">
                                            <span>{{ $import->source_name ?: 'No source name' }}</span>
                                            <span aria-hidden="true">-</span>
                                            <span class="capitalize" x-text="item.status_label"></span>
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <span
                                            class="ui-pill"
                                            x-show="item.is_active"
                                            x-cloak
                                        >
                                            Live
                                        </span>
                                        <a href="{{ route('modules.ivr.imports.show', $import) }}" class="ui-pill">Import log</a>

                                        @if (! in_array($import->status, ['pending', 'processing', 'reverting', 'reverted'], true) && $import->reverted_at === null)
                                            <form method="POST" action="{{ route('modules.ivr.imports.destroy', $import) }}" onsubmit="return confirm('Revert this raw import? This will remove contacts and source links created only by this import. Shared contacts with other history will be kept.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ui-pill">Revert</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <div class="mb-1 flex items-center justify-between gap-3 text-xs ui-muted">
                                        <span x-text="`${item.processed_rows} / ${item.total_rows || '-'}`"></span>
                                        <span x-text="`${item.progress}%`"></span>
                                    </div>
                                    <div class="ui-progress">
                                        <div class="ui-progress-bar" :style="`width: ${item.progress}%`"></div>
                                    </div>
                                    <p class="mt-2 text-xs ui-muted" x-text="`${item.successful_rows} imported - ${item.failed_rows} failed - ${item.duplicate_rows} duplicates`"></p>
                                </div>
                            </div>
                        @empty
                            <div class="ui-empty">No imports yet.</div>
                        @endforelse
                    </div>

                    <div class="px-5 py-4">
                        {{ $imports->links() }}
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
