<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Number History</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[0.7fr_1.3fr]">
                <section class="ivr-panel bg-white p-5">
                    <h3 class="text-lg font-semibold text-[#0D0D0D]">{{ $number->normalized_phone }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-[#595859]">Client</dt>
                            <dd class="text-[#262526]">{{ $number->client?->full_name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">City</dt>
                            <dd class="text-[#262526]">{{ $number->client?->city ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Usage status</dt>
                            <dd class="text-[#262526]">{{ ucfirst($number->usage_status) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Cooldown until</dt>
                            <dd class="text-[#262526]">{{ optional($number->cooldown_until)->format('Y-m-d H:i') ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Unsubscribed</dt>
                            <dd class="text-[#262526]">{{ optional($number->unsubscribed_at)->format('Y-m-d H:i') ?: 'No' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-6">
                    <div class="ivr-panel overflow-hidden bg-white">
                        <div class="border-b border-[#D9D9D9] px-5 py-4">
                            <h3 class="text-lg font-semibold text-[#0D0D0D]">Call history</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="border-b border-[#D9D9D9] text-[#595859]">
                                    <tr>
                                        <th class="px-5 py-3 font-medium">Time</th>
                                        <th class="px-5 py-3 font-medium">Campaign</th>
                                        <th class="px-5 py-3 font-medium">Status</th>
                                        <th class="px-5 py-3 font-medium">Outcome</th>
                                        <th class="px-5 py-3 font-medium">Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($number->ivrCallRecords as $call)
                                        <tr class="border-b border-[#D9D9D9]">
                                            <td class="px-5 py-3">{{ optional($call->call_time)->format('Y-m-d H:i') }}</td>
                                            <td class="px-5 py-3">{{ $call->campaign?->external_campaign_id }}</td>
                                            <td class="px-5 py-3">{{ $call->call_status }}</td>
                                            <td class="px-5 py-3">{{ $call->dtmf_outcome ?: '-' }}</td>
                                            <td class="px-5 py-3">{{ $call->total_duration_seconds }}s</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-5 py-6 text-[#595859]">No call history available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="ivr-panel overflow-hidden bg-white">
                        <div class="border-b border-[#D9D9D9] px-5 py-4">
                            <h3 class="text-lg font-semibold text-[#0D0D0D]">Source history</h3>
                        </div>
                        <div class="divide-y divide-[#D9D9D9]">
                            @forelse ($number->sources as $source)
                                <div class="px-5 py-4 text-sm">
                                    <p class="font-medium text-[#262526]">{{ $source->source_name ?: $source->source_type }}</p>
                                    <p class="text-[#595859]">{{ $source->source_type }} • {{ $source->created_at->format('Y-m-d H:i') }}</p>
                                </div>
                            @empty
                                <div class="px-5 py-6 text-sm text-[#595859]">No source history available.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
