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
                            <dd class="ui-strong capitalize">{{ $import->statusLabel() }}</dd>
                        </div>
                        @if ($import->source_name)
                            <div>
                                <dt class="ui-muted">Source</dt>
                                <dd class="ui-strong">{{ $import->source_name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="ui-muted">Processed</dt>
                            <dd class="ui-strong">{{ number_format($import->processed_rows) }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Successful</dt>
                            <dd class="ui-strong">{{ number_format($import->successful_rows) }}</dd>
                        </div>
                        <div>
                            <dt class="ui-muted">Failed</dt>
                            <dd class="ui-strong {{ $import->failed_rows > 0 ? 'text-red-600' : '' }}">{{ number_format($import->failed_rows) }}</dd>
                        </div>
                        @if ($import->created_at)
                            <div>
                                <dt class="ui-muted">Uploaded</dt>
                                <dd class="ui-strong">{{ $import->created_at->format('M j, Y g:i A') }}</dd>
                            </div>
                        @endif
                        @if ($import->completed_at)
                            <div>
                                <dt class="ui-muted">Completed</dt>
                                <dd class="ui-strong">{{ $import->completed_at->format('M j, Y g:i A') }}</dd>
                            </div>
                        @endif
                    </dl>

                    <div class="mt-6">
                        <a href="{{ url()->previous(route('modules.whatsapp.imports.index')) }}" class="ui-button-subtle text-sm">← Back</a>
                    </div>
                </section>

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <h3 class="ui-title">Error report</h3>
                        @if ($import->failed_rows > 500)
                            <p class="text-xs ui-muted">Showing first 500 of {{ number_format($import->failed_rows) }} errors.</p>
                        @endif
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
                                        <td class="whitespace-nowrap">{{ $error->row_number }}</td>
                                        <td class="whitespace-nowrap">{{ $error->error_type }}</td>
                                        <td class="text-xs text-theme-secondary">{{ $error->error_message }}</td>
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
