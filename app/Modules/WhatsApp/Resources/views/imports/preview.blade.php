<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Import</h2>
            <p class="mt-1 text-sm ui-muted">
                Step 3 of 3 &mdash; Preview &amp; confirm
            </p>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">

            {{-- Step indicator --}}
            <div class="mb-6 flex items-center gap-3 text-sm">
                <span class="ui-muted line-through">1. Upload</span>
                <span class="ui-muted">&rarr;</span>
                <span class="ui-muted line-through">2. Map columns</span>
                <span class="ui-muted">&rarr;</span>
                <span class="font-medium text-theme-primary">3. Preview &amp; confirm</span>
            </div>

            {{-- Summary card --}}
            <div class="grid gap-4 sm:grid-cols-3 mb-6">
                <div class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">File</p>
                    <p class="mt-1 text-sm font-medium text-theme-primary break-all">{{ $import->original_file_name }}</p>
                </div>
                <div class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Source name</p>
                    <p class="mt-1 text-sm font-medium text-theme-primary">{{ $import->source_name ?: '—' }}</p>
                </div>
                <div class="ui-card ui-card-pad">
                    <p class="text-sm ui-muted">Total rows to import</p>
                    <p class="mt-1 text-3xl font-semibold text-theme-primary">{{ number_format($totalRows) }}</p>
                </div>
            </div>

            {{-- Mapped fields summary --}}
            <div class="ui-card ui-card-pad mb-6">
                <p class="text-sm font-medium text-theme-primary mb-2">Mapped fields</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($mapping as $field => $colIndex)
                        <span class="ui-pill @if(in_array($field, $required)) ui-pill-active @endif">
                            {{ $systemFields[$field] ?? $field }}
                            @if(in_array($field, $required))
                                <span class="ml-1 text-xs opacity-75">(required)</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Data preview table --}}
            <section class="ui-card overflow-hidden mb-6">
                <div class="ui-section-head">
                    <div>
                        <h3 class="ui-title">Data preview</h3>
                        <p class="mt-1 text-sm ui-muted">
                            Showing first {{ count($mappedRows) }} of {{ number_format($totalRows) }} rows.
                        </p>
                    </div>
                    <a href="{{ route('modules.whatsapp.imports.map', $import) }}" class="text-sm ui-muted hover:underline">
                        &larr; Back to mapping
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="w-8 text-center">#</th>
                                @foreach ($mapping as $field => $colIndex)
                                    <th @class(['text-theme-accent font-semibold' => in_array($field, $required)])>
                                        {{ $systemFields[$field] ?? $field }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mappedRows as $i => $row)
                                <tr>
                                    <td class="text-center text-xs ui-muted">{{ $i + 1 }}</td>
                                    @foreach ($mapping as $field => $colIndex)
                                        <td @class(['font-medium' => in_array($field, $required)])>
                                            {{ $row[$field] ?? '' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($mapping) + 1 }}" class="ui-empty">No data rows found in this file.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Confirm form --}}
            <form method="POST" action="{{ route('modules.whatsapp.imports.confirm', $import) }}">
                @csrf
                @if ($errors->any())
                    <div class="ui-alert mb-4 text-red-600">{{ $errors->first() }}</div>
                @endif
                <div class="flex items-center gap-3">
                    <x-primary-button>Confirm &amp; queue import</x-primary-button>
                    <a href="{{ route('modules.whatsapp.imports.map', $import) }}" class="text-sm ui-muted hover:underline">&larr; Back</a>
                    <a href="{{ route('modules.whatsapp.imports.index') }}" class="text-sm ui-muted hover:underline">Cancel</a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
