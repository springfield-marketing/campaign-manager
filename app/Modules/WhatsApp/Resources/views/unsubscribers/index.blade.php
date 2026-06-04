<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Unsubscribers</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 md:grid-cols-2 mb-6">
                {{-- Bulk upload --}}
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">Import from CSV</h3>
                    <p class="mt-2 text-sm ui-muted">
                        Upload a CSV with two columns: <strong>phone number</strong> then <strong>name</strong>.
                        Imported numbers will be suppressed from WhatsApp campaigns.
                    </p>

                    <form method="POST" action="{{ route('modules.whatsapp.unsubscribers.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                        @csrf
                        <div>
                            <label class="ui-label" for="file">CSV file</label>
                            <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>
                        <button type="submit" class="ui-button">Import</button>
                    </form>
                </section>

                {{-- Add single --}}
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">Add number manually</h3>
                    <p class="mt-2 text-sm ui-muted">
                        Suppress a single phone number immediately. The number must already exist in the database.
                        Enter in any format: <code class="text-xs">0501234567</code>, <code class="text-xs">+971501234567</code>, etc.
                    </p>

                    <form method="POST" action="{{ route('modules.whatsapp.unsubscribers.add') }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                        @csrf
                        <div>
                            <label class="ui-label" for="phone">Phone number</label>
                            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" placeholder="+971501234567" value="{{ old('phone') }}" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <button type="submit" class="ui-button">Suppress</button>
                    </form>
                </section>
            </div>

            <div
                class="ui-card mt-6 overflow-hidden"
                x-data="importProgress({
                    endpoint: '{{ route('modules.whatsapp.imports.status') }}',
                    wsChannel: '',
                    imports: @js($imports->map(fn ($import) => \App\Modules\WhatsApp\Support\WhatsAppImportStatusPayload::make($import))->values())
                })"
            >
                <div class="ui-section-head">
                    <h3 class="ui-title">Import history</h3>
                </div>

                <div class="ui-divide max-h-[360px] overflow-y-auto">
                    @forelse ($imports as $import)
                        <div class="px-5 py-4 text-sm" x-data="{ item: get({{ $import->id }}) }">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                    <p class="capitalize ui-muted" x-text="item.status_label">{{ $import->statusLabel() }}</p>
                                    <p class="mt-1 text-xs text-theme-secondary" x-text="item.status_message">{{ $import->statusMessage() }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <span class="ui-pill ui-pill-active" x-show="item.is_active" x-cloak>Live</span>
                                    <span class="ui-pill" x-show="! item.is_active" x-cloak>
                                        <span class="capitalize" x-text="item.status_label">{{ $import->statusLabel() }}</span>
                                    </span>
                                    @if ($import->failed_rows > 0)
                                        <a href="{{ route('modules.whatsapp.unsubscribers.imports.show', $import) }}" class="ui-pill text-red-600">View errors</a>
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
                                    <span class="ui-muted"><span class="font-medium text-green-600" x-text="item.successful_rows">{{ $import->successful_rows }}</span> suppressed</span>
                                    <span class="ui-muted"><span class="font-medium text-theme-secondary" x-text="item.duplicate_rows">{{ $import->duplicate_rows }}</span> already existed</span>
                                    <span class="ui-muted"><span class="font-medium text-red-600" x-text="item.failed_rows">{{ $import->failed_rows }}</span> failed</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="ui-empty">No unsubscriber imports yet.</div>
                    @endforelse
                </div>

                <div class="px-5 py-4">
                    {{ $imports->links('pagination::tailwind', ['pageName' => 'imports_page']) }}
                </div>
            </div>

            <section class="ui-card overflow-hidden mt-6">
                <div class="ui-section-head">
                    <div>
                        <h3 class="ui-title">Suppressed numbers</h3>
                        @if ($unsubscribers->total() > 0)
                            <p class="mt-1 text-sm ui-muted">{{ number_format($unsubscribers->total()) }} total</p>
                        @endif
                    </div>
                </div>

                <div class="px-5 py-4 border-b border-[var(--line)]">
                    <form method="GET" class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
                        <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Search phone" class="ui-control">
                        <input type="search" name="name" value="{{ request('name') }}" placeholder="Search name" class="ui-control">
                        <button type="submit" class="ui-button">Filter</button>
                        <a href="{{ route('modules.whatsapp.unsubscribers.index') }}" class="ui-button text-center">Clear</a>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Suppressed at</th>
                                <th>Source</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($unsubscribers as $suppression)
                                <tr>
                                    <td>{{ $suppression->phoneNumber?->client?->full_name ?: '-' }}</td>
                                    <td>{{ $suppression->phoneNumber?->normalized_phone ?: '-' }}</td>
                                    <td>{{ optional($suppression->suppressed_at)->format('Y-m-d H:i') ?: '-' }}</td>
                                    <td>
                                        @if (($suppression->context['source'] ?? null) === 'manual')
                                            Manual entry
                                        @elseif ($suppression->context['source_file'] ?? null)
                                            {{ $suppression->context['source_file'] }}
                                        @elseif ($suppression->context['campaign_name'] ?? null)
                                            Campaign: {{ $suppression->context['campaign_name'] }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('modules.whatsapp.unsubscribers.destroy', $suppression) }}" onsubmit="return confirm('Remove this number from WhatsApp unsubscribers?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-pill">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="ui-empty">No active WhatsApp unsubscribers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4">
                    {{ $unsubscribers->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
