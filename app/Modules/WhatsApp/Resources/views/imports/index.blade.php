<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">WhatsApp Import</h2>
            <div class="mt-3">@include('whatsapp::partials.section-nav')</div>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            <section class="ui-card ui-card-pad">
                <h3 class="ui-title">Import campaign results</h3>
                <p class="mt-2 text-sm ui-muted">
                    Upload the CSV export from your WhatsApp campaign platform. Expected columns:
                    <code class="text-xs">ScheduleAt, PhoneNumber, CampaignName, TemplateName, Status, Failure reason, Quick replies, Quick reply 1-3, Clicked, Retried</code>
                </p>

                <form method="POST" action="{{ route('modules.whatsapp.imports.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="file" :value="__('Campaign CSV')" />
                        <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                    <x-primary-button>Queue Import</x-primary-button>
                </form>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head">
                    <h3 class="ui-title">Import history</h3>
                </div>

                <div class="ui-divide max-h-[560px] overflow-y-auto">
                    @forelse ($imports as $import)
                        <div class="px-5 py-4 text-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                    <p class="capitalize ui-muted">{{ $import->statusLabel() }}</p>
                                    <p class="mt-1 text-xs text-theme-secondary">{{ $import->statusMessage() }}</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    @if (! in_array($import->status, ['pending', 'processing', 'reverted'], true) && $import->reverted_at === null)
                                        <form method="POST" action="{{ route('modules.whatsapp.imports.destroy', $import) }}" onsubmit="return confirm('Revert this import? This will remove its campaign messages.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-pill">Revert</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="mb-1 flex items-center justify-between gap-3 text-xs ui-muted">
                                    <span>{{ $import->processed_rows }} / {{ $import->total_rows ?: '-' }}</span>
                                    <span>{{ $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0 }}%</span>
                                </div>
                                <div class="ui-progress">
                                    <div
                                        class="ui-progress-bar"
                                        style="width: {{ $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0 }}%"
                                    ></div>
                                </div>
                                <p class="mt-2 text-xs ui-muted">
                                    {{ $import->successful_rows }} imported &ndash; {{ $import->failed_rows }} failed &ndash; {{ $import->duplicate_rows }} duplicates
                                </p>
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
</x-app-layout>
