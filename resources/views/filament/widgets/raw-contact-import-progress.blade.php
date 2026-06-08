<x-filament-widgets::widget>
    <x-filament::section heading="Recent Imports">

        @if ($imports->isEmpty())
            <p class="text-sm text-gray-400 italic">No imports yet. Use the Upload button above to get started.</p>
        @else
            <div class="space-y-3">
                @foreach ($imports as $import)
                    @php
                        $staged  = (int) ($import->summary['staged_rows'] ?? 0);
                        $pct     = ($import->total_rows > 0)
                                    ? min(100, (int) round($import->processed_rows / $import->total_rows * 100))
                                    : 0;
                        $label   = $import->source_name ?: $import->original_file_name;
                        $subline = $import->source_name ? $import->original_file_name : null;
                        $age     = $import->created_at?->diffForHumans();
                    @endphp

                    {{-- ── PENDING ──────────────────────────────────────── --}}
                    @if ($import->status === 'pending')
                        <div class="flex items-start gap-4 rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900 p-4">
                            <div class="mt-0.5 shrink-0">
                                <svg class="h-5 w-5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $label }}</p>
                                @if ($subline)<p class="text-xs text-gray-500 truncate">{{ $subline }}</p>@endif
                                <p class="mt-1 text-sm text-gray-500">Queued — waiting for the queue worker to start…</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-400">{{ $age }}</span>
                        </div>

                    {{-- ── PROCESSING ───────────────────────────────────── --}}
                    @elseif ($import->status === 'processing')
                        <div class="rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950 p-4">
                            <div class="flex items-start gap-4">
                                <div class="mt-0.5 shrink-0">
                                    <svg class="h-5 w-5 animate-spin text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-blue-900 dark:text-blue-100 truncate">{{ $label }}</p>
                                    @if ($subline)<p class="text-xs text-blue-700 dark:text-blue-400 truncate">{{ $subline }}</p>@endif
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $pct }}%</span>
                            </div>

                            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-blue-200 dark:bg-blue-900">
                                <div class="h-2 rounded-full bg-blue-500 transition-all duration-500" style="width: {{ $pct }}%"></div>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-4 text-xs text-blue-700 dark:text-blue-400">
                                <span>{{ number_format($import->processed_rows) }} / {{ number_format($import->total_rows) }} rows</span>
                                @if ($import->successful_rows > 0)
                                    <span>✓ {{ number_format($import->successful_rows) }} imported</span>
                                @endif
                                @if ($import->failed_rows > 0)
                                    <span>✗ {{ number_format($import->failed_rows) }} failed</span>
                                @endif
                                @if ($staged > 0)
                                    <span>⚑ {{ number_format($staged) }} staged for review</span>
                                @endif
                            </div>
                        </div>

                    {{-- ── COMPLETED ────────────────────────────────────── --}}
                    @elseif ($import->status === 'completed')
                        <div class="rounded-xl border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950 p-4">
                            <div class="flex items-start gap-4">
                                <div class="mt-0.5 shrink-0">
                                    <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-green-900 dark:text-green-100 truncate">{{ $label }}</p>
                                    @if ($subline)<p class="text-xs text-green-700 dark:text-green-400 truncate">{{ $subline }}</p>@endif
                                </div>
                                <span class="shrink-0 text-xs text-green-600 dark:text-green-400">{{ $age }}</span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-5 text-sm">
                                <div class="text-center">
                                    <p class="text-xl font-bold text-green-700 dark:text-green-300">{{ number_format($import->successful_rows) }}</p>
                                    <p class="text-xs text-green-600 dark:text-green-500">Contacts added</p>
                                </div>
                                @if ($import->duplicate_rows > 0)
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-500">{{ number_format($import->duplicate_rows) }}</p>
                                        <p class="text-xs text-gray-400">Already existed</p>
                                    </div>
                                @endif
                                @if ($staged > 0)
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($staged) }}</p>
                                        <p class="text-xs text-amber-500">Staged for review</p>
                                    </div>
                                @endif
                            </div>

                            @if ($staged > 0)
                                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                    ↓ These contacts had a name but no phone or email — they appear in the table below for manual review.
                                </p>
                            @endif
                        </div>

                    {{-- ── COMPLETED WITH ERRORS ────────────────────────── --}}
                    @elseif ($import->status === 'completed_with_errors')
                        <div class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950 p-4">
                            <div class="flex items-start gap-4">
                                <div class="mt-0.5 shrink-0">
                                    <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-amber-900 dark:text-amber-100 truncate">{{ $label }}</p>
                                    @if ($subline)<p class="text-xs text-amber-700 dark:text-amber-400 truncate">{{ $subline }}</p>@endif
                                </div>
                                <span class="shrink-0 text-xs text-amber-600 dark:text-amber-400">{{ $age }}</span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-5 text-sm">
                                <div class="text-center">
                                    <p class="text-xl font-bold text-green-700 dark:text-green-400">{{ number_format($import->successful_rows) }}</p>
                                    <p class="text-xs text-gray-500">Contacts added</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-xl font-bold text-red-600 dark:text-red-400">{{ number_format($import->failed_rows) }}</p>
                                    <p class="text-xs text-gray-500">Rows failed</p>
                                </div>
                                @if ($import->duplicate_rows > 0)
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-gray-500">{{ number_format($import->duplicate_rows) }}</p>
                                        <p class="text-xs text-gray-500">Already existed</p>
                                    </div>
                                @endif
                                @if ($staged > 0)
                                    <div class="text-center">
                                        <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($staged) }}</p>
                                        <p class="text-xs text-gray-500">Staged for review</p>
                                    </div>
                                @endif
                            </div>

                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                Most rows imported successfully. Click <strong>Details</strong> on the row above to see exactly which rows failed and why.
                            </p>
                        </div>

                    {{-- ── FAILED ───────────────────────────────────────── --}}
                    @elseif ($import->status === 'failed')
                        <div class="rounded-xl border border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950 p-4">
                            <div class="flex items-start gap-4">
                                <div class="mt-0.5 shrink-0">
                                    <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-red-900 dark:text-red-100 truncate">{{ $label }}</p>
                                    @if ($subline)<p class="text-xs text-red-700 dark:text-red-400 truncate">{{ $subline }}</p>@endif
                                    @if ($import->error_message)
                                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $import->error_message }}</p>
                                    @endif
                                </div>
                                <span class="shrink-0 text-xs text-red-400">{{ $age }}</span>
                            </div>

                            <div class="mt-3 flex items-center gap-3">
                                <button
                                    wire:click="retryImport({{ $import->id }})"
                                    wire:confirm="Reset this import and try again?"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 active:bg-red-800 disabled:opacity-50 transition"
                                >
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H5.498a.75.75 0 00-.75.75v3.766a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V3.204a.75.75 0 00-1.5 0v2.433l-.31-.31a7 7 0 00-11.712 3.138.75.75 0 001.449.39A5.5 5.5 0 0113.89 5.34l.311.31h-2.432a.75.75 0 000 1.5H15.5a.75.75 0 00.531-.222l.511-.505z" clip-rule="evenodd"/>
                                    </svg>
                                    Try Again
                                </button>
                                <span class="text-xs text-red-500">The file is still on disk — no need to re-upload.</span>
                            </div>
                        </div>

                    {{-- ── OTHER STATES (reverted, deleted, etc.) ───────── --}}
                    @else
                        <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900 px-4 py-3">
                            <p class="flex-1 truncate text-sm text-gray-500">{{ $label }}</p>
                            <span class="text-xs capitalize text-gray-400">{{ str_replace('_', ' ', $import->status) }}</span>
                            <span class="text-xs text-gray-300">{{ $age }}</span>
                        </div>
                    @endif

                @endforeach
            </div>
        @endif

    </x-filament::section>
</x-filament-widgets::widget>
