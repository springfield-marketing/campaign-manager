<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Numbers</h2>
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
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($numbers as $number)
                                <tr>
                                    <td>{{ $number->normalized_phone }}</td>
                                    <td>{{ $number->client?->full_name ?: '-' }}</td>
                                    <td>{{ $number->client?->city ?: '-' }}</td>
                                    <td>{{ $number->last_source_name ?: '-' }}</td>
                                    <td>{{ $number->whats_app_messages_count }}</td>
                                    <td>
                                        <a href="{{ route('modules.whatsapp.numbers.show', $number) }}" class="ui-link">View</a>
                                    </td>
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
