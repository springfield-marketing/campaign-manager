<x-filament-widgets::widget>
    <x-filament::section heading="Recent Imports">

        @if ($imports->isEmpty())
            <p class="text-sm text-gray-400 italic">No imports yet — use the Upload button above to get started.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($imports as $import)
                    @php
                        $staged  = (int) ($import->summary['staged_rows'] ?? 0);
                        $pct     = ($import->total_rows > 0)
                                    ? min(100, (int) round($import->processed_rows / $import->total_rows * 100))
                                    : 0;
                        $title   = $import->source_name ?: $import->original_file_name;
                        $sub     = $import->source_name ? $import->original_file_name : null;

                        [$icon, $iconClass, $badgeColor, $badgeLabel] = match ($import->status) {
                            'completed'             => ['heroicon-o-check-circle',      'text-green-500',  'success', 'Done'],
                            'completed_with_errors' => ['heroicon-o-exclamation-circle','text-amber-500',  'warning', 'Partial'],
                            'failed'                => ['heroicon-o-x-circle',          'text-red-500',    'danger',  'Failed'],
                            'processing'            => ['heroicon-o-arrow-path',        'text-blue-500',   'info',    'Processing'],
                            'pending'               => ['heroicon-o-clock',             'text-gray-400',   'gray',    'Queued'],
                            default                 => ['heroicon-o-ellipsis-horizontal','text-gray-300',  'gray',    ucfirst($import->status)],
                        };
                    @endphp

                    <li class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0">

                        {{-- Header row --}}
                        <div class="flex items-center gap-3">
                            <x-filament::icon
                                :icon="$icon"
                                :class="$iconClass . ' h-5 w-5 shrink-0' . ($import->status === 'processing' || $import->status === 'pending' ? ' animate-spin' : '')"
                            />

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $title }}
                                </p>
                                @if ($sub)
                                    <p class="truncate text-xs text-gray-500">{{ $sub }}</p>
                                @endif
                            </div>

                            <x-filament::badge :color="$badgeColor" size="sm">
                                {{ $badgeLabel }}
                            </x-filament::badge>

                            <span class="shrink-0 text-xs text-gray-400">
                                {{ $import->created_at?->diffForHumans() }}
                            </span>
                        </div>

                        {{-- Processing: progress bar + live counts --}}
                        @if ($import->status === 'processing')
                            <div class="pl-8">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                    <div
                                        class="h-1.5 rounded-full bg-blue-500 transition-all duration-500"
                                        style="width: {{ $pct }}%"
                                    ></div>
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-3 text-xs text-gray-500">
                                    <span>{{ $pct }}% &mdash; {{ number_format($import->processed_rows) }} / {{ number_format($import->total_rows) }} rows</span>
                                    @if ($import->successful_rows > 0)
                                        <span class="text-green-600">{{ number_format($import->successful_rows) }} imported</span>
                                    @endif
                                    @if ($import->failed_rows > 0)
                                        <span class="text-red-500">{{ number_format($import->failed_rows) }} failed</span>
                                    @endif
                                    @if ($staged > 0)
                                        <span class="text-amber-500">{{ number_format($staged) }} staged</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Completed: stats --}}
                        @if ($import->status === 'completed')
                            <div class="pl-8 flex flex-wrap gap-4 text-sm">
                                <span>
                                    <span class="font-semibold text-green-600">{{ number_format($import->successful_rows) }}</span>
                                    <span class="text-gray-500"> contacts added</span>
                                </span>
                                @if ($import->duplicate_rows > 0)
                                    <span>
                                        <span class="font-semibold text-gray-500">{{ number_format($import->duplicate_rows) }}</span>
                                        <span class="text-gray-400"> already existed</span>
                                    </span>
                                @endif
                                @if ($staged > 0)
                                    <span>
                                        <span class="font-semibold text-amber-500">{{ number_format($staged) }}</span>
                                        <span class="text-gray-400"> staged for review ↓</span>
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Completed with errors: stats + nudge --}}
                        @if ($import->status === 'completed_with_errors')
                            <div class="pl-8 flex flex-wrap gap-4 text-sm">
                                <span>
                                    <span class="font-semibold text-green-600">{{ number_format($import->successful_rows) }}</span>
                                    <span class="text-gray-500"> added</span>
                                </span>
                                <span>
                                    <span class="font-semibold text-red-500">{{ number_format($import->failed_rows) }}</span>
                                    <span class="text-gray-500"> failed</span>
                                </span>
                                @if ($import->duplicate_rows > 0)
                                    <span>
                                        <span class="font-semibold text-gray-500">{{ number_format($import->duplicate_rows) }}</span>
                                        <span class="text-gray-400"> already existed</span>
                                    </span>
                                @endif
                            </div>
                            <p class="pl-8 text-xs text-gray-400">
                                Click <strong>Details</strong> below to see which rows failed and why.
                            </p>
                        @endif

                        {{-- Failed: error message + retry --}}
                        @if ($import->status === 'failed')
                            <div class="pl-8 space-y-2">
                                @if ($import->error_message)
                                    <p class="text-sm text-red-600 dark:text-red-400">
                                        {{ $import->error_message }}
                                    </p>
                                @endif
                                <div class="flex items-center gap-3">
                                    <x-filament::button
                                        wire:click="retryImport({{ $import->id }})"
                                        wire:confirm="Reset this import and try again with the same file?"
                                        color="danger"
                                        size="sm"
                                        icon="heroicon-o-arrow-path"
                                    >
                                        Try Again
                                    </x-filament::button>
                                    <span class="text-xs text-gray-400">The file is still on disk — no need to re-upload.</span>
                                </div>
                            </div>
                        @endif

                    </li>
                @endforeach
            </ul>
        @endif

    </x-filament::section>
</x-filament-widgets::widget>
