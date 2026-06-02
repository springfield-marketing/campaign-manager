<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Imports</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            {{-- Tab switcher --}}
            <div class="mb-6 flex gap-2 border-b border-[var(--line)]">
                <a
                    href="{{ route('modules.whatsapp.imports.index') }}?tab=contacts"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'contacts' ? 'border-theme-accent text-theme-accent' : 'border-transparent ui-muted hover:text-theme-primary' }}"
                >
                    Contacts
                </a>
                <a
                    href="{{ route('modules.whatsapp.imports.index') }}?tab=campaign-results"
                    class="px-4 py-2 text-sm font-medium border-b-2 transition-colors {{ $tab === 'campaign-results' ? 'border-theme-accent text-theme-accent' : 'border-transparent ui-muted hover:text-theme-primary' }}"
                >
                    Campaign Results
                </a>
            </div>

            @if ($tab === 'contacts')
                {{-- ── CONTACTS TAB ─────────────────────────────────────────── --}}
                <div
                    class="grid gap-6"
                    x-data="importProgress({
                        endpoint: '{{ route('modules.whatsapp.imports.status') }}',
                        wsChannel: '',
                        imports: @js($imports->map(fn ($import) => \App\Modules\WhatsApp\Support\WhatsAppImportStatusPayload::make($import))->values())
                    })"
                >
                    <section class="ui-card ui-card-pad">
                        <h3 class="ui-title">Upload raw contacts</h3>
                        <p class="mt-2 text-sm ui-muted">
                            Add contacts to the client database. Required columns are <strong>name</strong> and <strong>phone</strong>.
                            Existing numbers are updated — not duplicated.
                        </p>

                        <div class="mt-4 grid gap-3 rounded border border-[var(--line)] bg-theme-subtle p-4 text-sm md:grid-cols-2">
                            <div>
                                <p class="font-medium text-theme-primary">Required columns</p>
                                <p class="mt-1 ui-muted">
                                    The file must have a header row with at least <strong>name</strong> and <strong>phone</strong>.
                                    Accepted phone headers: <code class="text-xs">phone, mobile, phone number, contact number</code>.
                                </p>
                            </div>
                            <div>
                                <p class="font-medium text-theme-primary">Optional columns</p>
                                <p class="mt-1 ui-muted">
                                    email, country, nationality, community, resident, city, gender, interest, source.
                                    Phones can be local (0501234567) or international (+971501234567).
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <p class="font-medium text-theme-primary">Example header row</p>
                                <code class="mt-1 block overflow-x-auto whitespace-nowrap text-xs text-theme-secondary">
                                    name,phone,email,city,nationality,interest,source
                                </code>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('modules.whatsapp.imports.upload') }}" enctype="multipart/form-data" class="mt-6">
                            @csrf
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <x-input-label for="file" :value="__('CSV file')" />
                                    <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="source_name" :value="__('Source name')" />
                                    <x-text-input id="source_name" name="source_name" type="text" class="mt-1 block w-full" placeholder="e.g. May 2026 Event" />
                                    <x-input-error :messages="$errors->get('source_name')" class="mt-2" />
                                </div>
                            </div>
                            <div class="mt-4">
                                <x-primary-button>Next: Map columns &rarr;</x-primary-button>
                            </div>
                        </form>
                    </section>

                    <section class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Import history</h3>
                        </div>

                        <div class="ui-divide max-h-[560px] overflow-y-auto">
                            @forelse ($imports as $import)
                                @if ($import->status === 'draft')
                                    <div class="px-5 py-4 text-sm opacity-75">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                                <p class="ui-muted">
                                                    <span>{{ $import->source_name ?: 'No source name' }}</span>
                                                    <span aria-hidden="true"> &ndash; </span>
                                                    <span>Draft</span>
                                                </p>
                                                <p class="mt-1 text-xs ui-muted">{{ $import->statusMessage() }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                                <span class="ui-pill">Draft</span>
                                                <a href="{{ route('modules.whatsapp.imports.map', $import) }}" class="ui-pill">Continue setup &rarr;</a>
                                                <form method="POST" action="{{ route('modules.whatsapp.imports.destroy', $import) }}"
                                                      onsubmit="return confirm('Cancel this draft? The uploaded file will be removed.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="ui-pill">Cancel</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="px-5 py-4 text-sm" x-data="{ item: get({{ $import->id }}) }">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                                <p class="ui-muted">
                                                    <span>{{ $import->source_name ?: 'No source name' }}</span>
                                                    <span aria-hidden="true"> &ndash; </span>
                                                    <span class="capitalize" x-text="item.status_label">{{ $import->statusLabel() }}</span>
                                                </p>
                                                <p class="mt-2 text-xs font-medium text-theme-secondary" x-text="item.status_message">{{ $import->statusMessage() }}</p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                                <span class="ui-pill ui-pill-active" x-show="item.is_active" x-cloak>
                                                    <span x-text="item.status === 'deleting' ? 'Deleting' : 'Live'"></span>
                                                </span>
                                                <span class="ui-pill ui-pill-active" x-show="['deleted', 'reverted'].includes(item.status)" x-cloak>Deleted</span>
                                                <span class="ui-pill ui-pill-active" x-show="item.status === 'delete_failed'" x-cloak>Delete failed</span>
                                                <span class="ui-pill" x-show="! item.is_active && ! ['deleted', 'deleting', 'delete_failed', 'reverted'].includes(item.status)" x-cloak>
                                                    <span class="capitalize" x-text="item.status_label">{{ $import->statusLabel() }}</span>
                                                </span>

                                                @if ($import->failed_rows > 0)
                                                    <a href="{{ route('modules.whatsapp.imports.show', $import) }}" class="ui-pill text-red-600">View errors</a>
                                                @endif

                                                @if (! in_array($import->status, ['pending', 'processing', 'deleting', 'deleted', 'reverted'], true) && $import->reverted_at === null)
                                                    <form method="POST" action="{{ route('modules.whatsapp.imports.destroy', $import) }}"
                                                          onsubmit="return confirm('Delete this import? Contacts added only by this import will be removed.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="ui-pill">Delete</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <div class="mb-1 flex items-center justify-between gap-3 text-xs font-medium text-theme-secondary">
                                                <span x-text="item.progress_label">{{ $import->processed_rows }} / {{ $import->total_rows ?: '-' }}</span>
                                                <span x-text="`${item.progress}%`">{{ $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0 }}%</span>
                                            </div>
                                            <div class="ui-progress">
                                                <div
                                                    class="ui-progress-bar"
                                                    style="width: {{ $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0 }}%"
                                                    :style="`width: ${item.progress}%`"
                                                ></div>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-3 text-xs">
                                                <span class="ui-muted"><span class="font-medium text-green-600" x-text="item.successful_rows">{{ $import->successful_rows }}</span> imported</span>
                                                <span class="ui-muted"><span class="font-medium text-red-600" x-text="item.failed_rows">{{ $import->failed_rows }}</span> failed</span>
                                                <span class="ui-muted"><span class="font-medium text-theme-secondary" x-text="item.duplicate_rows">{{ $import->duplicate_rows }}</span> duplicates</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div class="ui-empty">No imports yet.</div>
                            @endforelse
                        </div>

                        <div class="px-5 py-4">
                            {{ $imports->links() }}
                        </div>
                    </section>
                </div>

            @else
                {{-- ── CAMPAIGN RESULTS TAB ─────────────────────────────────── --}}
                <div
                    class="grid gap-6"
                    x-data="importProgress({
                        endpoint: '{{ route('modules.whatsapp.imports.status') }}',
                        wsChannel: '',
                        imports: @js($campaignImports->map(fn ($import) => \App\Modules\WhatsApp\Support\WhatsAppImportStatusPayload::make($import))->values())
                    })"
                >
                    <article class="ui-card ui-card-pad">
                        <h3 class="ui-title">Import campaign results</h3>
                        <p class="mt-2 text-sm ui-muted">
                            Upload the CSV export from your WhatsApp campaign platform. Expected columns:
                            <code class="text-xs">ScheduleAt, PhoneNumber, CampaignName, TemplateName, Status, Failure reason, Quick replies, Quick reply 1–3, Clicked, Retried</code>
                        </p>
                        <form method="POST" action="{{ route('modules.whatsapp.imports.campaign-results.store') }}" enctype="multipart/form-data" class="mt-6">
                            @csrf
                            <div class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                <div>
                                    <x-input-label for="file" :value="__('Campaign CSV')" />
                                    <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                                    <x-input-error :messages="$errors->get('file')" class="mt-2" />
                                </div>
                                <x-primary-button>Queue Import</x-primary-button>
                            </div>
                        </form>
                    </article>

                    <article class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Import history</h3>
                        </div>

                        <div class="ui-divide max-h-[560px] overflow-y-auto">
                            @forelse ($campaignImports as $import)
                                <div class="px-5 py-4 text-sm" x-data="{ item: get({{ $import->id }}) }">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                            <p class="ui-muted capitalize" x-text="item.status_label">{{ $import->statusLabel() }}</p>
                                            <p class="mt-1 text-xs text-theme-secondary" x-text="item.status_message">{{ $import->statusMessage() }}</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                            <span class="ui-pill ui-pill-active" x-show="item.is_active" x-cloak>Live</span>
                                            <span class="ui-pill" x-show="! item.is_active" x-cloak>
                                                <span class="capitalize" x-text="item.status_label">{{ $import->statusLabel() }}</span>
                                            </span>
                                            @if ($import->failed_rows > 0)
                                                <a href="{{ route('modules.whatsapp.imports.show', $import) }}" class="ui-pill text-red-600">View errors</a>
                                            @endif
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
                                        <div class="mb-1 flex items-center justify-between gap-3 text-xs font-medium text-theme-secondary">
                                            <span x-text="item.progress_label">{{ $import->processed_rows }} / {{ $import->total_rows ?: '-' }}</span>
                                            <span x-text="`${item.progress}%`">{{ $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0 }}%</span>
                                        </div>
                                        <div class="ui-progress">
                                            <div
                                                class="ui-progress-bar"
                                                style="width: {{ $import->total_rows > 0 ? min(100, round(($import->processed_rows / $import->total_rows) * 100)) : 0 }}%"
                                                :style="`width: ${item.progress}%`"
                                            ></div>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-3 text-xs">
                                            <span class="ui-muted"><span class="font-medium text-green-600" x-text="item.successful_rows">{{ $import->successful_rows }}</span> imported</span>
                                            <span class="ui-muted"><span class="font-medium text-red-600" x-text="item.failed_rows">{{ $import->failed_rows }}</span> failed</span>
                                            <span class="ui-muted"><span class="font-medium text-theme-secondary" x-text="item.duplicate_rows">{{ $import->duplicate_rows }}</span> duplicates</span>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="ui-empty">No campaign result imports yet.</div>
                            @endforelse
                        </div>

                        <div class="px-5 py-4">
                            {{ $campaignImports->links() }}
                        </div>
                    </article>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
