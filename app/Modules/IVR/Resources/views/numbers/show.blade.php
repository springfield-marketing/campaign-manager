<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="page-title">{{ $number->normalized_phone }}</h2>
            @php
                $ivrStatus    = $number->ivrProfile?->usage_status ?? 'active';
                $statusColour = match ($ivrStatus) {
                    'dead'     => 'text-red-600',
                    'inactive' => 'text-amber-600',
                    'cooldown' => 'text-amber-600',
                    default    => 'text-green-600',
                };
                $isUnsubscribed = $number->suppressions->contains(
                    fn ($s) => ($s->channel === null || $s->channel === 'ivr')
                        && $s->reason === 'customer_unsubscribed'
                        && $s->released_at === null
                );
            @endphp
            <span class="font-medium {{ $statusColour }}">{{ ucfirst($ivrStatus) }}</span>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="mb-4 flex items-center justify-between">
                <a href="{{ url()->previous(route('modules.ivr.numbers.index')) }}" class="text-sm ui-muted hover:underline">&larr; Back to numbers</a>
                @if ($isUnsubscribed)
                    <form method="POST" action="{{ route('modules.ivr.numbers.unsuppress', $number) }}"
                          onsubmit="return confirm('Remove unsubscribe for this number?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="ui-button-subtle text-sm">Remove unsubscribe</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('modules.ivr.numbers.suppress', $number) }}"
                          onsubmit="return confirm('Mark this number as unsubscribed? It will be excluded from future campaigns.');">
                        @csrf
                        <button type="submit" class="ui-button-subtle text-sm text-red-600">Mark unsubscribed</button>
                    </form>
                @endif
            </div>

            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[1fr_1.6fr]">

                {{-- ── LEFT SIDEBAR ──────────────────────────────────────── --}}
                <div class="space-y-6">

                    {{-- IVR profile card --}}
                    <section class="ui-card ui-card-pad">
                        <h3 class="ui-title">IVR profile</h3>
                        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt class="ui-muted">Status</dt>
                                <dd class="font-medium {{ $statusColour }}">{{ ucfirst($ivrStatus) }}</dd>
                            </div>
                            <div>
                                <dt class="ui-muted">Total calls</dt>
                                <dd class="ui-strong">{{ $number->ivrCallRecords->count() }}</dd>
                            </div>
                            <div>
                                <dt class="ui-muted">Last called</dt>
                                <dd class="ui-strong">{{ optional($number->ivrProfile?->last_called_at)->format('Y-m-d') ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="ui-muted">Cooldown until</dt>
                                <dd class="ui-strong">{{ optional($number->ivrProfile?->cooldown_until)->format('Y-m-d') ?: '—' }}</dd>
                            </div>
                            <div>
                                <dt class="ui-muted">Unsubscribed</dt>
                                <dd class="ui-strong {{ $isUnsubscribed ? 'text-red-600' : '' }}">{{ $isUnsubscribed ? 'Yes' : 'No' }}</dd>
                            </div>
                            <div>
                                <dt class="ui-muted">Is primary</dt>
                                <dd class="ui-strong">{{ $number->is_primary ? 'Yes' : 'No' }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="ui-muted">Source</dt>
                                <dd class="ui-strong">{{ $number->last_source_name ?: '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    {{-- Client details card --}}
                    <section class="ui-card ui-card-pad">
                        @php $editingClient = request('edit') === 'client'; @endphp
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="ui-title">Client details</h3>
                            @if ($number->client)
                                @if ($editingClient)
                                    <a href="{{ route('modules.ivr.numbers.show', $number) }}" class="ui-pill">Cancel</a>
                                @else
                                    <a href="{{ route('modules.ivr.numbers.show', $number) }}?edit=client" class="ui-pill">Edit</a>
                                @endif
                            @endif
                        </div>

                        @if ($number->client && $editingClient)
                            <form method="POST" action="{{ route('modules.ivr.numbers.client.update', $number) }}" class="mt-4 space-y-3 text-sm">
                                @csrf @method('PATCH')

                                @if ($errors->any())
                                    <div class="ui-alert text-xs space-y-1">
                                        @foreach ($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                                    </div>
                                @endif

                                <div>
                                    <label class="ui-label">Full name</label>
                                    <input type="text" name="full_name" value="{{ old('full_name', $number->client->full_name) }}" class="ui-control mt-1 w-full">
                                </div>
                                <div>
                                    <label class="ui-label">Email</label>
                                    <input type="email" name="email" value="{{ old('email', $number->client->primary_email_address) }}" class="ui-control mt-1 w-full">
                                </div>
                                <div>
                                    <label class="ui-label">Nationality</label>
                                    <input type="text" name="nationality" value="{{ old('nationality', $number->client->nationality) }}" class="ui-control mt-1 w-full">
                                </div>
                                <div>
                                    <label class="ui-label">Gender</label>
                                    <select name="gender" class="ui-control mt-1 w-full">
                                        <option value="">— not set —</option>
                                        @foreach (['Male', 'Female', 'Other'] as $g)
                                            <option value="{{ $g }}" @selected(old('gender', $number->client->gender) === $g)>{{ $g }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Resident status</label>
                                    <input type="text" name="resident" value="{{ old('resident', $number->client->resident) }}" class="ui-control mt-1 w-full" placeholder="e.g. UAE Resident">
                                </div>
                                <div>
                                    <label class="ui-label">Interest</label>
                                    <input type="text" name="interest" value="{{ old('interest', $number->client->interest) }}" class="ui-control mt-1 w-full">
                                </div>
                                <div>
                                    <label class="ui-label">Country</label>
                                    <select name="country_id" class="ui-control mt-1 w-full">
                                        <option value="">— none —</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}" @selected(old('country_id', $number->client->country_id) == $country->id)>{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Emirate</label>
                                    <select name="region_id" class="ui-control mt-1 w-full">
                                        <option value="">— none —</option>
                                        @foreach ($regions as $region)
                                            <option value="{{ $region->id }}" @selected(old('region_id', $number->client->region_id) == $region->id)>{{ $region->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Community</label>
                                    <select name="community_id" class="ui-control mt-1 w-full">
                                        <option value="">— none —</option>
                                        @foreach ($regions as $region)
                                            @if ($region->communities->isNotEmpty())
                                                <optgroup label="{{ $region->name }}">
                                                    @foreach ($region->communities->sortBy('name') as $community)
                                                        <option value="{{ $community->id }}" @selected(old('community_id', $number->client->community_id) == $community->id)>{{ $community->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pt-1">
                                    <button type="submit" class="ui-button w-full">Save changes</button>
                                </div>
                            </form>
                        @elseif ($number->client)
                            <dl class="mt-4 space-y-3 text-sm">
                                <div>
                                    <dt class="ui-muted">Full name</dt>
                                    <dd class="ui-strong">{{ $number->client->full_name ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Email</dt>
                                    <dd class="ui-strong">{{ $number->client->primary_email_address ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Nationality</dt>
                                    <dd class="ui-strong">{{ $number->client->nationality ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Gender</dt>
                                    <dd class="ui-strong">{{ $number->client->gender ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Resident</dt>
                                    <dd class="ui-strong">{{ $number->client->resident ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Interest</dt>
                                    <dd class="ui-strong">{{ $number->client->interest ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Country</dt>
                                    <dd class="ui-strong">{{ $number->client->country?->name ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Emirate</dt>
                                    <dd class="ui-strong">{{ $number->client->region?->name ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="ui-muted">Community</dt>
                                    <dd class="ui-strong">{{ $number->client->community?->name ?: '—' }}</dd>
                                </div>
                            </dl>
                        @else
                            <p class="mt-3 text-sm ui-muted">No client linked to this number.</p>
                        @endif
                    </section>

                    {{-- Tags card --}}
                    @if ($number->client)
                        <section class="ui-card ui-card-pad">
                            <h3 class="ui-title">Tags</h3>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($number->client->tags as $tag)
                                    <span class="ui-pill ui-pill-active">{{ $tag->name }}</span>
                                @empty
                                    <p class="text-sm ui-muted">No tags assigned.</p>
                                @endforelse
                            </div>

                            @if ($allTags->isNotEmpty())
                                @php $editingTags = request('edit') === 'tags'; @endphp
                                <div class="mt-4">
                                    @if ($editingTags)
                                        <form method="POST" action="{{ route('modules.ivr.numbers.tags.update', $number) }}" class="space-y-2">
                                            @csrf @method('PATCH')
                                            <div class="grid grid-cols-2 gap-1 max-h-40 overflow-y-auto">
                                                @foreach ($allTags as $tag)
                                                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                                                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                                               @checked($number->client->tags->contains($tag->id))
                                                               class="rounded">
                                                        {{ $tag->name }}
                                                    </label>
                                                @endforeach
                                            </div>
                                            <div class="flex gap-2 pt-1">
                                                <button type="submit" class="ui-button flex-1">Save</button>
                                                <a href="{{ route('modules.ivr.numbers.show', $number) }}" class="ui-button-subtle flex-1 text-center">Cancel</a>
                                            </div>
                                        </form>
                                    @else
                                        <a href="{{ route('modules.ivr.numbers.show', $number) }}?edit=tags" class="ui-button-subtle w-full text-center text-sm">Manage tags</a>
                                    @endif
                                </div>
                            @endif
                        </section>
                    @endif

                    {{-- Other phone numbers for this client --}}
                    @if ($number->client && $number->client->phoneNumbers->count() > 1)
                        <section class="ui-card overflow-hidden">
                            <div class="ui-section-head">
                                <h3 class="ui-title">Other numbers</h3>
                                <p class="text-sm ui-muted mt-0.5">{{ $number->client->phoneNumbers->count() }} total</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Uses</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($number->client->phoneNumbers as $clientNumber)
                                            <tr class="{{ $clientNumber->id === $number->id ? 'opacity-50' : '' }}">
                                                <td>
                                                    <div class="flex flex-wrap items-center gap-1">
                                                        @if ($clientNumber->is_primary)
                                                            <span class="ui-pill text-xs">Primary</span>
                                                        @endif
                                                        @if ($clientNumber->id === $number->id)
                                                            <span class="ui-pill ui-pill-active text-xs">Current</span>
                                                        @else
                                                            <a href="{{ route('modules.ivr.numbers.show', $clientNumber) }}" class="ui-link text-xs">
                                                                {{ $clientNumber->normalized_phone }}
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $clientNumber->ivr_use_count }}</td>
                                                <td>{{ ucfirst($clientNumber->ivrProfile?->usage_status ?? 'active') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endif

                </div>

                {{-- ── RIGHT MAIN CONTENT ────────────────────────────────── --}}
                <div class="space-y-6">

                    {{-- Log a note --}}
                    @if ($number->client)
                        <section class="ui-card ui-card-pad">
                            <h3 class="ui-title">Log a note</h3>
                            <form method="POST" action="{{ route('modules.ivr.numbers.interactions.store', $number) }}" class="mt-4 space-y-3">
                                @csrf
                                <div>
                                    <textarea name="description" rows="3" placeholder="Add a note about this contact…" class="ui-control w-full" required>{{ old('description') }}</textarea>
                                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex gap-3 items-end">
                                    <div class="flex-1">
                                        <label class="ui-label">Source <span class="font-normal ui-muted">(optional)</span></label>
                                        <input type="text" name="source" value="{{ old('source') }}" placeholder="e.g. Inbound call, Agent" class="ui-control mt-1 w-full">
                                    </div>
                                    <button type="submit" class="ui-button">Log note</button>
                                </div>
                            </form>
                        </section>
                    @endif

                    {{-- Interaction timeline --}}
                    @if ($interactions->isNotEmpty())
                        <section class="ui-card overflow-hidden">
                            <div class="ui-section-head">
                                <h3 class="ui-title">Activity timeline</h3>
                                <p class="text-sm ui-muted mt-0.5">{{ $interactions->count() }} recent events</p>
                            </div>
                            <div class="ui-divide max-h-80 overflow-y-auto">
                                @foreach ($interactions as $interaction)
                                    <div class="px-5 py-3 text-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="ui-pill text-xs capitalize">{{ $interaction->type->label() }}</span>
                                                    @if ($interaction->source)
                                                        <span class="text-xs ui-muted">{{ $interaction->source }}</span>
                                                    @endif
                                                </div>
                                                @if ($interaction->description)
                                                    <p class="mt-1 ui-strong">{{ $interaction->description }}</p>
                                                @endif
                                            </div>
                                            <p class="shrink-0 text-xs ui-muted whitespace-nowrap">{{ $interaction->created_at->format('d M Y') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    {{-- Communities & interests --}}
                    @if ($number->client)
                        <section class="ui-card overflow-hidden">
                            <div class="ui-section-head">
                                <h3 class="ui-title">Communities &amp; interests</h3>
                            </div>
                            @if ($clientCommunities->isNotEmpty())
                                <div class="overflow-x-auto">
                                    <table class="ui-table">
                                        <thead>
                                            <tr>
                                                <th>Community</th>
                                                <th>Emirate</th>
                                                <th>Project</th>
                                                <th>Relationship</th>
                                                <th>Confidence</th>
                                                <th>Source</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($clientCommunities as $cc)
                                                <tr>
                                                    <td>{{ $cc->community?->name ?: '—' }}</td>
                                                    <td>{{ $cc->community?->region?->name ?: '—' }}</td>
                                                    <td>{{ $cc->project?->name ?: '—' }}</td>
                                                    <td>{{ $cc->relationship_type?->label() ?? '—' }}</td>
                                                    <td>{{ $cc->confidence_level?->label() ?? '—' }}</td>
                                                    <td class="text-xs ui-muted">{{ $cc->source ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="ui-empty">No community relationships recorded yet.</div>
                            @endif
                        </section>
                    @endif

                    {{-- Call history --}}
                    <section class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Call history</h3>
                            <p class="text-sm ui-muted mt-0.5">{{ $number->ivrCallRecords->count() }} calls</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Campaign</th>
                                        <th>Status</th>
                                        <th>Response</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($number->ivrCallRecords as $call)
                                        <tr>
                                            <td class="whitespace-nowrap">{{ optional($call->call_time)->format('Y-m-d H:i') }}</td>
                                            <td>
                                                @if ($call->campaign)
                                                    <a href="{{ route('modules.ivr.results.show', $call->campaign) }}" class="ui-link">
                                                        {{ $call->campaign->external_campaign_id }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $call->call_status ?: '—' }}</td>
                                            <td>{{ $call->dtmf_outcome ? ucfirst(str_replace('_', ' ', $call->dtmf_outcome)) : '—' }}</td>
                                            <td>
                                                @php $s = (int) $call->total_duration_seconds; @endphp
                                                {{ $s > 0 ? floor($s / 60).':'.str_pad($s % 60, 2, '0', STR_PAD_LEFT) : '—' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="ui-empty">No call history.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- Source history --}}
                    <section class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Source history</h3>
                        </div>
                        <div class="ui-divide">
                            @forelse ($number->sources as $source)
                                <div class="px-5 py-3 text-sm">
                                    <p class="font-medium ui-strong">{{ $source->source_name ?: $source->source_type }}</p>
                                    <p class="ui-muted text-xs mt-0.5">{{ $source->source_type }} &ndash; {{ $source->created_at->format('d M Y H:i') }}</p>
                                </div>
                            @empty
                                <div class="ui-empty">No source history.</div>
                            @endforelse
                        </div>
                    </section>

                    {{-- Suppression history --}}
                    <section class="ui-card overflow-hidden">
                        <div class="ui-section-head">
                            <h3 class="ui-title">Suppression history</h3>
                        </div>
                        @forelse ($number->suppressions as $suppression)
                            @php
                                $ctx      = $suppression->context ?? [];
                                $isActive = $suppression->released_at === null;
                            @endphp
                            <div class="border-b border-[var(--line)] px-5 py-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium ui-strong capitalize">{{ str_replace('_', ' ', $suppression->reason) }}</p>
                                        <p class="mt-0.5 text-xs ui-muted">
                                            {{ ucfirst($suppression->channel ?? 'All channels') }}
                                            &mdash; {{ \Carbon\Carbon::parse($suppression->suppressed_at)->format('d M Y H:i') }}
                                        </p>
                                        @if ($ctx['source'] ?? null === 'manual') <p class="mt-0.5 text-xs ui-muted">Added manually</p> @endif
                                        @if ($suppression->released_at)
                                            <p class="mt-0.5 text-xs text-green-600">Released {{ \Carbon\Carbon::parse($suppression->released_at)->format('d M Y H:i') }}</p>
                                        @endif
                                    </div>
                                    @if ($isActive) <span class="ui-pill shrink-0">Active</span> @endif
                                </div>
                            </div>
                        @empty
                            <div class="ui-empty">No suppression history.</div>
                        @endforelse
                    </section>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
