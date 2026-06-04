<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Numbers</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap space-y-6">

            {{-- Stats --}}
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Total numbers</p>
                    <p class="mt-2 text-3xl font-bold text-theme-primary">{{ number_format($stats['total']) }}</p>
                    <p class="mt-1 text-xs ui-muted">All imported contacts</p>
                </article>
                <article class="ui-card ui-card-pad border-t-4 border-t-green-500">
                    <p class="text-sm font-medium text-green-700">Active</p>
                    <p class="mt-2 text-3xl font-bold text-green-600">{{ number_format($stats['active']) }}</p>
                    <p class="mt-1 text-xs text-green-600/70">Ready to contact</p>
                </article>
                <article class="ui-card ui-card-pad border-t-4 border-t-orange-400">
                    <p class="text-sm font-medium text-orange-700">Unsubscribed</p>
                    <p class="mt-2 text-3xl font-bold text-orange-500">{{ number_format($stats['unsubscribed']) }}</p>
                    <p class="mt-1 text-xs text-orange-500/70">Dead or opted out</p>
                </article>
                <article class="ui-card ui-card-pad border-t-4 border-t-amber-400">
                    <p class="text-sm font-medium text-amber-700">Cooldown</p>
                    <p class="mt-2 text-3xl font-bold text-amber-500">{{ number_format($stats['cooldown']) }}</p>
                    <p class="mt-1 text-xs text-amber-500/70">Temporarily paused</p>
                </article>
            </section>

            {{-- Filters + Export --}}
            <div class="ui-card ui-card-pad">
                <form method="GET" class="grid gap-4">
                    {{-- Primary filters --}}
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="ui-label">Phone</label>
                            <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Search number" class="ui-control mt-1 w-full">
                        </div>
                        <div>
                            <label class="ui-label">Name</label>
                            <input type="search" name="name" value="{{ request('name') }}" placeholder="Search name" class="ui-control mt-1 w-full">
                        </div>
                        <div>
                            <label class="ui-label">Origin</label>
                            <select name="origin" class="ui-control mt-1 w-full">
                                <option value="">All origins</option>
                                @foreach ($origins as $origin)
                                    <option value="{{ $origin }}" @selected(request('origin') === $origin)>{{ $origin }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Emirate</label>
                            <select name="region" class="ui-control mt-1 w-full">
                                <option value="">All emirates</option>
                                @foreach ($regions as $region)
                                    <option value="{{ $region->id }}" @selected(request('region') == $region->id)>{{ $region->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Community</label>
                            <select name="community" class="ui-control mt-1 w-full">
                                <option value="">All communities</option>
                                @foreach ($communities->groupBy(fn ($c) => $c->region?->name) as $emirate => $group)
                                    <optgroup label="{{ $emirate }}">
                                        @foreach ($group->sortBy('name') as $community)
                                            <option value="{{ $community->id }}" @selected(request('community') == $community->id)>{{ $community->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Status</label>
                            <select name="status" class="ui-control mt-1 w-full">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex gap-2">
                            <button type="submit" class="ui-button-subtle">Filter</button>
                            <a href="{{ route('modules.whatsapp.numbers.index') }}" class="ui-button-subtle text-center">Clear</a>
                        </div>
                        <div class="ml-auto">
                            <label class="ui-label">Export limit <span class="font-normal ui-muted">(max rows)</span></label>
                            <div class="mt-1 flex gap-2">
                                <input type="number" name="export_limit" min="1" max="50000" value="{{ request('export_limit', 5000) }}" class="ui-control w-28">
                                <button type="submit" formaction="{{ route('modules.whatsapp.numbers.export') }}" class="ui-button">Export</button>
                            </div>
                        </div>
                    </div>
                </form>

                <p class="mt-4 text-xs ui-muted">
                    Status: <strong>Active</strong> = receiving messages normally &nbsp;&middot;&nbsp;
                    <strong>Cooldown</strong> = temporarily paused after delivery failures &nbsp;&middot;&nbsp;
                    <strong>Dead</strong> = permanently marked invalid
                </p>
            </div>

            {{-- Results count --}}
            <div class="flex items-center justify-between text-sm ui-muted -mt-2">
                @if ($numbers->total() > 0)
                    <span>Showing {{ number_format($numbers->firstItem()) }}–{{ number_format($numbers->lastItem()) }} of {{ number_format($numbers->total()) }} numbers</span>
                @else
                    <span>No numbers found matching your filters.</span>
                @endif
            </div>

            {{-- Table --}}
            <div class="ui-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Phone</th>
                                <th>Name</th>
                                <th>Emirate</th>
                                <th>Origin</th>
                                <th>Source</th>
                                <th>Messages</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($numbers as $number)
                                @php
                                    $wpStatus = $number->wp_usage_status ?? 'active';
                                    $statusColour = match ($wpStatus) {
                                        'dead'     => 'text-red-600',
                                        'cooldown' => 'text-amber-600',
                                        default    => 'text-green-600',
                                    };
                                @endphp
                                <tr
                                    class="cursor-pointer hover:bg-theme-subtle transition-colors"
                                    onclick="window.location.href='{{ route('modules.whatsapp.numbers.show', $number) }}'"
                                >
                                    <td>{{ $number->normalized_phone }}</td>
                                    <td>{{ $number->client?->full_name ?: '-' }}</td>
                                    <td>{{ $number->client?->region?->name ?: '-' }}</td>
                                    <td>{{ $number->detected_country ?: '-' }}</td>
                                    <td>{{ $number->last_source_name ?: '-' }}</td>
                                    <td>{{ $number->whats_app_messages_count }}</td>
                                    <td class="font-medium {{ $statusColour }}">{{ ucfirst($wpStatus) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="ui-empty">No numbers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4">
                    {{ $numbers->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
