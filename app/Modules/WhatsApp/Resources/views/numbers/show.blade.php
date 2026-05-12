<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Number History</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">

                {{-- Left: number details --}}
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">{{ $number->normalized_phone }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="ui-muted">Client</dt>
                            <dd class="ui-strong">{{ $number->client?->full_name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">City</dt>
                            <dd class="ui-strong">{{ $number->client?->city ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">WhatsApp unsubscribed</dt>
                            <dd class="ui-strong">
                                @if ($number->suppressions->isNotEmpty())
                                    {{ optional($number->suppressions->first()->suppressed_at)->format('Y-m-d H:i') }}
                                @else
                                    No
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Messages sent</dt>
                            <dd class="ui-strong">{{ $number->whatsAppMessages->count() }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Last messaged</dt>
                            <dd class="ui-strong">{{ optional($number->whatsAppMessages->first()?->scheduled_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                        </div>
                    </dl>
                </section>

                {{-- Right: tables --}}
                <section class="space-y-6">

                    {{-- Client numbers --}}
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Client numbers</h3>
                            <p class="mt-1 text-sm ui-muted">
                                @if ($number->client)
                                    {{ $number->client->phoneNumbers->count() }} number{{ $number->client->phoneNumbers->count() === 1 ? '' : 's' }} linked to this client.
                                @else
                                    No linked client.
                                @endif
                            </p>
                        </div>

                        @if ($number->client)
                            <div class="overflow-x-auto">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Label</th>
                                            <th>Messages</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($number->client->phoneNumbers as $clientNumber)
                                            <tr>
                                                <td>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        @if ($clientNumber->id === $number->id)
                                                            <span class="ui-pill ui-pill-active">Current</span>
                                                        @endif
                                                        @if ($clientNumber->is_primary)
                                                            <span class="ui-pill">Primary</span>
                                                        @endif
                                                        @if ($clientNumber->whats_app_messages_count > 0)
                                                            <a href="{{ route('modules.whatsapp.numbers.show', $clientNumber) }}" class="ui-link">
                                                                {{ $clientNumber->normalized_phone }}
                                                            </a>
                                                        @else
                                                            {{ $clientNumber->normalized_phone }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $clientNumber->label ?: '-' }}</td>
                                                <td>{{ $clientNumber->whats_app_messages_count }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="ui-empty">No linked client numbers available.</div>
                        @endif
                    </div>

                    {{-- Message history --}}
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Message history</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Scheduled</th>
                                        <th>Campaign</th>
                                        <th>Template</th>
                                        <th>Status</th>
                                        <th>Clicked</th>
                                        <th>Quick Reply</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($number->whatsAppMessages as $message)
                                        <tr>
                                            <td>{{ optional($message->scheduled_at)->format('Y-m-d H:i') }}</td>
                                            <td>
                                                @if ($message->campaign)
                                                    <a href="{{ route('modules.whatsapp.campaigns.show', $message->campaign) }}" class="ui-link">
                                                        {{ $message->campaign->name }}
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
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
                                            <td colspan="6" class="ui-empty">No message history.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Source history --}}
                    <div class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Source history</h3>
                        </div>
                        <div class="ui-divide">
                            @forelse ($number->sources as $source)
                                <div class="px-5 py-4 text-sm">
                                    <p class="font-medium ui-strong">{{ $source->source_name ?: $source->source_type }}</p>
                                    <p class="ui-muted">{{ $source->source_type }} &ndash; {{ $source->created_at->format('Y-m-d H:i') }}</p>
                                </div>
                            @empty
                                <div class="ui-empty">No source history available.</div>
                            @endforelse
                        </div>
                    </div>

                </section>
            </div>
        </div>
    </div>
</x-app-layout>
