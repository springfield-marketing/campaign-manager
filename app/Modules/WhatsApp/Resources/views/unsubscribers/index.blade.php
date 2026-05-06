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

            <section class="ui-card ui-card-pad">
                <h3 class="ui-title">Import unsubscribers</h3>
                <p class="mt-2 text-sm ui-muted">
                    Upload a CSV with two columns: <strong>phone number</strong> then <strong>name</strong>.
                    Imported numbers will be suppressed from WhatsApp campaigns.
                </p>

                <form method="POST" action="{{ route('modules.whatsapp.unsubscribers.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    @csrf
                    <div>
                        <label class="ui-label" for="file">CSV file</label>
                        <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>
                    <button type="submit" class="ui-button">Import</button>
                </form>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head">
                    <h3 class="ui-title">Import history</h3>
                </div>

                <div class="ui-divide max-h-[360px] overflow-y-auto">
                    @forelse ($imports as $import)
                        <div class="px-5 py-4 text-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="break-all font-medium text-theme-primary">{{ $import->original_file_name }}</p>
                                    <p class="capitalize ui-muted">{{ $import->statusLabel() }}</p>
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
                                    {{ $import->successful_rows }} added &ndash; {{ $import->duplicate_rows }} already existed &ndash; {{ $import->failed_rows }} failed
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="ui-empty">No unsubscriber imports yet.</div>
                    @endforelse
                </div>

                <div class="px-5 py-4">
                    {{ $imports->links('pagination::tailwind', ['pageName' => 'imports_page']) }}
                </div>
            </section>

            <section class="ui-card ui-card-pad mt-6">
                <h3 class="ui-title">Filter unsubscribers</h3>
                <form method="GET" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                    <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Search phone" class="ui-control">
                    <input type="search" name="name" value="{{ request('name') }}" placeholder="Search name" class="ui-control">
                    <button type="submit" class="ui-button">Filter</button>
                </form>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
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
                                        @if ($suppression->context['source_file'] ?? null)
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
