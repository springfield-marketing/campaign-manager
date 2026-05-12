<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Campaign Results</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6">
                <section class="grid gap-6">
                    <article class="ui-card ui-card-pad">
                        <h3 class="ui-title">Import campaign results</h3>
                        <p class="mt-2 text-sm ui-muted">
                            Upload the CSV export from your WhatsApp campaign platform. Expected columns:
                            <code class="text-xs">ScheduleAt, PhoneNumber, CampaignName, TemplateName, Status, Failure reason, Quick replies, Quick reply 1–3, Clicked, Retried</code>
                        </p>
                        <form method="POST" action="{{ route('modules.whatsapp.imports.store') }}" enctype="multipart/form-data" class="mt-6">
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
                    </article>
                </section>

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">Campaigns</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Campaign Name</th>
                                    <th>Started</th>
                                    <th>Total</th>
                                    <th>Delivered</th>
                                    <th>Read</th>
                                    <th>Failed</th>
                                    <th>Clicked</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($campaigns as $campaign)
                                    <tr>
                                        <td class="font-medium">{{ $campaign->name }}</td>
                                        <td>{{ optional($campaign->started_at)->format('Y-m-d') ?: '-' }}</td>
                                        <td>{{ number_format($campaign->total_messages) }}</td>
                                        <td>{{ number_format($campaign->delivered_count) }}</td>
                                        <td>{{ number_format($campaign->read_count) }}</td>
                                        <td>{{ number_format($campaign->failed_count) }}</td>
                                        <td>{{ number_format($campaign->clicked_count) }}</td>
                                        <td>
                                            <a href="{{ route('modules.whatsapp.campaigns.show', $campaign) }}" class="ui-link">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="ui-empty">No campaigns imported yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4">
                        {{ $campaigns->links() }}
                    </div>
                </section>

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">
                            Imported messages
                            @if ($latestCampaign)
                                &mdash; <span class="font-normal text-sm ui-muted">{{ $latestCampaign->name }}</span>
                            @endif
                        </h3>

                        @if ($latestCampaign)
                            <p class="mt-1 text-sm ui-muted">
                                Showing latest imported campaign.
                                <a href="{{ route('modules.whatsapp.campaigns.show', $latestCampaign) }}" class="ui-link">View campaign</a>
                            </p>
                        @endif

                        <form method="GET" class="mt-4 grid gap-3 md:grid-cols-4">
                            <select name="status" class="ui-control">
                                <option value="">All statuses</option>
                                @foreach (['DELIVERED', 'READ', 'SENT', 'FAILED'] as $status)
                                    <option value="{{ $status }}" @selected(request('status') == $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="template" value="{{ request('template') }}" placeholder="Template name" class="ui-control">
                            <input type="date" name="date" value="{{ request('date') }}" class="ui-control">
                            <button type="submit" class="ui-button">Filter</button>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Scheduled</th>
                                    <th>Phone</th>
                                    <th>Template</th>
                                    <th>Status</th>
                                    <th>Clicked</th>
                                    <th>Quick Reply</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($messages as $message)
                                    <tr>
                                        <td>{{ optional($message->scheduled_at)->format('Y-m-d H:i') }}</td>
                                        <td>{{ $message->phoneNumber?->normalized_phone ?: $message->raw_payload['PhoneNumber'] ?? '-' }}</td>
                                        <td>{{ $message->template_name ?: '-' }}</td>
                                        <td>{{ $message->delivery_status ?: '-' }}</td>
                                        <td>{{ $message->clicked ? 'Yes' : 'No' }}</td>
                                        <td>
                                            @if ($message->quick_reply_1)
                                                {{ $message->quick_reply_1 }}
                                                @if ($message->quick_reply_2) / {{ $message->quick_reply_2 }} @endif
                                                @if ($message->quick_reply_3) / {{ $message->quick_reply_3 }} @endif
                                            @else
                                                &ndash;
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="ui-empty">No messages found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4">
                        {{ $messages->links() }}
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
