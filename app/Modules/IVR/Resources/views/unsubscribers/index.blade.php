<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Unsubscribers</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">
                    {{ session('status') }}
                </div>
            @endif

            <section class="ui-card ui-card-pad">
                <h3 class="ui-title">Import unsubscribers</h3>
                <p class="mt-2 text-sm ui-muted">
                    Upload a CSV with two columns in this order: <strong>phone number</strong>, then <strong>name</strong>.
                    A header row is optional. Imported numbers are excluded from IVR exports.
                </p>

                <form method="POST" action="{{ route('modules.ivr.unsubscribers.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    @csrf

                    <div>
                        <label class="ui-label" for="file">CSV file</label>
                        <input id="file" name="file" type="file" class="ui-control mt-1 block w-full">
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <button type="submit" class="ui-button">Import</button>
                </form>
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
                                    <td>{{ $suppression->context['source_file'] ?? '-' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('modules.ivr.unsubscribers.destroy', $suppression) }}" onsubmit="return confirm('Remove this number from unsubscribers?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-pill">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="ui-empty">No active unsubscribers found.</td>
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
