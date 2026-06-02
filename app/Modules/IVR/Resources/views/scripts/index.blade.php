<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Scripts</h2>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">
            @if (session('status'))
                <div class="ui-alert mb-6">{{ session('status') }}</div>
            @endif

            <section class="ui-card ui-card-pad">
                <h3 class="ui-title">Upload script</h3>
                <p class="mt-2 text-sm ui-muted">Upload an audio file and paste the script text. Give it a name so you can pick it when importing campaign results.</p>

                <form method="POST" action="{{ route('modules.ivr.scripts.store') }}" enctype="multipart/form-data" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" :value="__('Script name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" placeholder="e.g. May 2026 — Offer A" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="audio_file" :value="__('Audio file (optional)')" />
                        <input id="audio_file" name="audio_file" type="file" accept="audio/*" class="ui-control mt-1 block w-full">
                        <x-input-error :messages="$errors->get('audio_file')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="audio_script" :value="__('Script text (optional)')" />
                        <textarea id="audio_script" name="audio_script" rows="6" class="ui-control mt-1 block w-full" placeholder="Paste the IVR script here…">{{ old('audio_script') }}</textarea>
                        <x-input-error :messages="$errors->get('audio_script')" class="mt-2" />
                    </div>
                    <x-primary-button>Save script</x-primary-button>
                </form>
            </section>

            <section class="ui-card mt-6 overflow-hidden">
                <div class="ui-section-head">
                    <h3 class="ui-title">Script library</h3>

                    <form method="GET" class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
                        <input type="text" name="name" value="{{ request('name') }}" placeholder="Search by name…" class="ui-control">
                        <input type="date" name="date" value="{{ request('date') }}" class="ui-control">
                        <button type="submit" class="ui-button">Filter</button>
                        <a href="{{ route('modules.ivr.scripts.index') }}" class="ui-button text-center">Clear</a>
                    </form>
                </div>

                @forelse ($scripts as $script)
                    <div class="border-b border-[var(--line)] px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-theme-primary">{{ $script->name }}</p>
                                <p class="mt-1 text-xs ui-muted">
                                    {{ $script->audio_original_name ?: 'No audio file' }}
                                    &mdash; {{ $script->campaigns()->count() }} campaign{{ $script->campaigns()->count() === 1 ? '' : 's' }}
                                    &mdash; {{ $script->created_at->format('Y-m-d') }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <form method="POST" action="{{ route('modules.ivr.scripts.destroy', $script) }}"
                                      onsubmit="return confirm('Delete this script? Campaigns using it will lose the reference.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-pill text-red-600">Delete</button>
                                </form>
                            </div>
                        </div>

                        @if ($script->audio_file_path)
                            <audio controls class="mt-3 w-full" src="{{ route('modules.ivr.scripts.audio', $script) }}">
                                Your browser does not support audio playback.
                            </audio>
                        @endif

                        @if ($script->audio_script)
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm ui-muted hover:underline">Show script text</summary>
                                <p class="mt-2 whitespace-pre-wrap text-sm ui-muted">{{ $script->audio_script }}</p>
                            </details>
                        @endif
                    </div>
                @empty
                    <div class="ui-empty">No scripts found.</div>
                @endforelse

                <div class="px-5 py-4">
                    {{ $scripts->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
