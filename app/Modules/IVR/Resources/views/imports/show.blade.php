<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-[#0D0D0D]">Import Details</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <section class="ivr-panel bg-white p-5">
                    <h3 class="text-lg font-semibold text-[#0D0D0D]">{{ $import->original_file_name }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-[#595859]">Type</dt>
                            <dd class="text-[#262526]">{{ str_replace('_', ' ', $import->type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Status</dt>
                            <dd class="text-[#262526]">{{ str_replace('_', ' ', $import->status) }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Processed</dt>
                            <dd class="text-[#262526]">{{ $import->processed_rows }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Successful</dt>
                            <dd class="text-[#262526]">{{ $import->successful_rows }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Duplicates</dt>
                            <dd class="text-[#262526]">{{ $import->duplicate_rows }}</dd>
                        </div>
                        <div>
                            <dt class="text-[#595859]">Failed</dt>
                            <dd class="text-[#262526]">{{ $import->failed_rows }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="ivr-panel overflow-hidden bg-white">
                    <div class="border-b border-[#D9D9D9] px-5 py-4">
                        <h3 class="text-lg font-semibold text-[#0D0D0D]">Error report</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="border-b border-[#D9D9D9] text-[#595859]">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Row</th>
                                    <th class="px-5 py-3 font-medium">Type</th>
                                    <th class="px-5 py-3 font-medium">Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($import->errors as $error)
                                    <tr class="border-b border-[#D9D9D9] align-top">
                                        <td class="px-5 py-3">{{ $error->row_number }}</td>
                                        <td class="px-5 py-3">{{ $error->error_type }}</td>
                                        <td class="px-5 py-3">{{ $error->error_message }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-5 py-6 text-[#595859]">No errors recorded for this import.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
