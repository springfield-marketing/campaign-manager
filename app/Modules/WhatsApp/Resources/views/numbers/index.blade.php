<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Numbers</h2>
            <div class="mt-3">@include('whatsapp::partials.section-nav')</div>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="ui-card ui-card-pad">
                <form method="GET" class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <input type="search" name="phone" value="{{ request('phone') }}" placeholder="Search phone number" class="ui-control">
                    <button type="submit" class="ui-button">Filter</button>
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
                                <th>Source</th>
                                <th>Messages</th>
                                <th>Unsubscribed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($numbers as $number)
                                <tr>
                                    <td>{{ $number->normalized_phone }}</td>
                                    <td>{{ $number->client?->full_name ?: '-' }}</td>
                                    <td>{{ $number->client?->city ?: '-' }}</td>
                                    <td>{{ $number->last_source_name ?: '-' }}</td>
                                    <td>{{ $number->whatsAppMessages()->count() }}</td>
                                    <td>{{ $number->unsubscribed_at ? optional($number->unsubscribed_at)->format('Y-m-d') : '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="ui-empty">No numbers found.</td>
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
