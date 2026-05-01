<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Import Details</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <section class="ui-card ui-card-pad">
                    <h3 class="ui-title">{{ $import->original_file_name }}</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="ui-muted">Type</dt>
                            <dd class="ui-strong">{{ str_replace('_', ' ', $import->type) }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Status</dt>
                            <dd class="ui-strong">{{ str_replace('_', ' ', $import->status) }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Processed</dt>
                            <dd class="ui-strong">{{ $import->processed_rows }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Successful</dt>
                            <dd class="ui-strong">{{ $import->successful_rows }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Duplicates</dt>
                            <dd class="ui-strong">{{ $import->duplicate_rows }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Failed</dt>
                            <dd class="ui-strong">{{ $import->failed_rows }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">Error report</h3>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Type</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($import->errors as $error)
                                    <tr class="align-top">
                                        <td>{{ $error->row_number }}</td>
                                        <td>{{ $error->error_type }}</td>
                                        <td>{{ $error->error_message }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="ui-empty">No errors recorded for this import.</td>
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
