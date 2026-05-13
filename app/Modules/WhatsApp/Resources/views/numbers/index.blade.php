<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Numbers</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="ui-card ui-card-pad">
                <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                    <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Phone number" class="ui-control">
                    <input type="search" name="name" value="{{ request('name') }}" placeholder="Client name" class="ui-control">
                    <input type="search" name="origin" value="{{ request('origin') }}" placeholder="Origin (e.g. AE, GB, US)" class="ui-control">
                    <input type="search" name="city" value="{{ request('city') }}" placeholder="City" class="ui-control">
                    <button type="submit" class="ui-button sm:col-span-2 lg:col-span-1">Filter</button>
                </form>
            </div>

            <div class="ui-card mt-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Phone</th>
                                <th>Name</th>
                                <th>City</th>
                                <th>Origin</th>
                                <th>Source</th>
                                <th>Messages</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($numbers as $number)
                                <tr>
                                    <td>{{ $number->normalized_phone }}</td>
                                    <td>{{ $number->client?->full_name ?: '-' }}</td>
                                    <td>{{ $number->client?->city ?: '-' }}</td>
                                    <td>{{ $number->detected_country ?: '-' }}</td>
                                    <td>{{ $number->last_source_name ?: '-' }}</td>
                                    <td>{{ $number->whats_app_messages_count }}</td>
                                    <td>
                                        <a href="{{ route('modules.whatsapp.numbers.show', $number) }}" class="ui-link">View</a>
                                    </td>
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
