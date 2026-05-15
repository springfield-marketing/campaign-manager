<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Numbers</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap space-y-6">

            {{-- Stats --}}
            <section class="grid gap-4 sm:grid-cols-4">
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Total numbers</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($stats['total']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Dead</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($stats['dead']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Suppressed</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($stats['suppressed']) }}</p>
                </article>
                <article class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Unique origins</p>
                    <p class="mt-3 text-3xl font-semibold text-theme-primary">{{ number_format($stats['origins']) }}</p>
                </article>
            </section>

            {{-- Filters + Export --}}
            <div class="ui-card ui-card-pad">
                <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr_auto_auto]" id="numbers-filter-form">
                    <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Phone number" class="ui-control">

                    {{-- Name combobox --}}
                    <div
                        class="relative"
                        x-data="combobox({ options: @js($names->values()->all()), name: 'name', value: '{{ request('name') }}' })"
                    >
                        <div class="relative flex items-center">
                            <input
                                type="text"
                                class="ui-control w-full pr-7"
                                placeholder="Client name"
                                autocomplete="off"
                                x-model="query"
                                @focus="open = true"
                                @blur="onBlur()"
                                @keydown.escape="onBlur()"
                            >
                            <button
                                type="button"
                                class="absolute right-2 text-gray-400 hover:text-gray-600"
                                x-show="selected"
                                @mousedown.prevent="clear()"
                                tabindex="-1"
                            >&times;</button>
                        </div>
                        <input type="hidden" :name="name" :value="selected">
                        <ul
                            x-show="open && filtered.length > 0"
                            x-cloak
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded border border-[var(--line)] bg-theme-surface shadow-lg text-sm"
                        >
                            <template x-for="option in filtered" :key="option">
                                <li
                                    class="cursor-pointer px-3 py-2 hover:bg-theme-subtle"
                                    :class="{ 'bg-theme-subtle font-medium': option === selected }"
                                    @mousedown.prevent="select(option)"
                                    x-text="option"
                                ></li>
                            </template>
                        </ul>
                    </div>

                    {{-- Origin combobox --}}
                    <div
                        class="relative"
                        x-data="combobox({ options: @js($origins->values()->all()), name: 'origin', value: '{{ request('origin') }}' })"
                    >
                        <div class="relative flex items-center">
                            <input
                                type="text"
                                class="ui-control w-full pr-7"
                                placeholder="Origin (e.g. AE)"
                                autocomplete="off"
                                x-model="query"
                                @focus="open = true"
                                @blur="onBlur()"
                                @keydown.escape="onBlur()"
                            >
                            <button
                                type="button"
                                class="absolute right-2 text-gray-400 hover:text-gray-600"
                                x-show="selected"
                                @mousedown.prevent="clear()"
                                tabindex="-1"
                            >&times;</button>
                        </div>
                        <input type="hidden" :name="name" :value="selected">
                        <ul
                            x-show="open && filtered.length > 0"
                            x-cloak
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded border border-[var(--line)] bg-theme-surface shadow-lg text-sm"
                        >
                            <template x-for="option in filtered" :key="option">
                                <li
                                    class="cursor-pointer px-3 py-2 hover:bg-theme-subtle"
                                    :class="{ 'bg-theme-subtle font-medium': option === selected }"
                                    @mousedown.prevent="select(option)"
                                    x-text="option"
                                ></li>
                            </template>
                        </ul>
                    </div>

                    {{-- Emirate select --}}
                    <select name="region" class="ui-control">
                        <option value="">All emirates</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region->id }}" @selected(request('region') == $region->id)>{{ $region->name }}</option>
                        @endforeach
                    </select>

                    {{-- Community select --}}
                    <select name="community" class="ui-control">
                        <option value="">All communities</option>
                        @foreach ($communities->groupBy(fn ($c) => $c->region?->name) as $emirate => $group)
                            <optgroup label="{{ $emirate }}">
                                @foreach ($group->sortBy('name') as $community)
                                    <option value="{{ $community->id }}" @selected(request('community') == $community->id)>{{ $community->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>

                    {{-- Status select --}}
                    <select name="status" class="ui-control">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="ui-button">Filter</button>
                    <button
                        type="submit"
                        formaction="{{ route('modules.whatsapp.numbers.export') }}"
                        class="ui-button"
                    >Export</button>
                </form>
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
                                <th></th>
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
                                <tr>
                                    <td>{{ $number->normalized_phone }}</td>
                                    <td>{{ $number->client?->full_name ?: '-' }}</td>
                                    <td>{{ $number->client?->region?->name ?: '-' }}</td>
                                    <td>{{ $number->detected_country ?: '-' }}</td>
                                    <td>{{ $number->last_source_name ?: '-' }}</td>
                                    <td>{{ $number->whats_app_messages_count }}</td>
                                    <td class="font-medium {{ $statusColour }}">{{ ucfirst($wpStatus) }}</td>
                                    <td>
                                        <a href="{{ route('modules.whatsapp.numbers.show', $number) }}" class="ui-link">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="ui-empty">No numbers found.</td>
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
