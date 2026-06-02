<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="page-title">Import</h2>
            <p class="mt-1 text-sm ui-muted">
                Step 2 of 3 &mdash; Map columns
            </p>
        </div>
    </x-slot>

    <div class="page-section">
        <div class="page-wrap">

            {{-- Step indicator --}}
            <div class="mb-6 flex items-center gap-3 text-sm">
                <span class="ui-muted line-through">1. Upload</span>
                <span class="ui-muted">&rarr;</span>
                <span class="font-medium text-theme-primary">2. Map columns</span>
                <span class="ui-muted">&rarr;</span>
                <span class="ui-muted">3. Preview &amp; confirm</span>
            </div>

            <div class="ui-card ui-card-pad mb-4">
                <p class="text-sm ui-muted">
                    File: <span class="font-medium text-theme-primary">{{ $import->original_file_name }}</span>
                    @if ($import->source_name)
                        &mdash; Source: <span class="font-medium text-theme-primary">{{ $import->source_name }}</span>
                    @endif
                </p>
            </div>

            @if ($errors->has('mapping'))
                <div class="ui-alert mb-4 text-red-600">{{ $errors->first('mapping') }}</div>
            @endif

            <form
                method="POST"
                action="{{ route('modules.whatsapp.imports.map.store', $import) }}"
                x-data="{ validate() {
                    const required = @js($required);
                    const selects = this.$el.querySelectorAll('select[name^=mapping]');
                    const chosen = {};
                    selects.forEach(s => { if (s.value) chosen[s.value] = true; });
                    const missing = required.filter(f => !chosen[f]);
                    if (missing.length) {
                        alert('Please map the required field(s): ' + missing.join(', '));
                        return false;
                    }
                    return true;
                }}"
                @submit.prevent="validate() && $el.submit()"
            >
                @csrf

                <section class="ui-card overflow-hidden">
                    <div class="ui-section-head">
                        <div>
                            <h3 class="ui-title">Column mapping</h3>
                            <p class="mt-1 text-sm ui-muted">
                                Tell the system which column in your CSV corresponds to each contact field.
                                <strong>Name</strong> and <strong>Phone</strong> are required. Everything else is optional.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th class="w-1/4">CSV column</th>
                                    <th class="w-2/5">Sample values</th>
                                    <th class="w-1/3">Maps to <span class="text-red-500 font-normal">* required</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($columns as $col)
                                    @php $isRequired = in_array($col['mapped_to'], $required); @endphp
                                    <tr @class(['bg-theme-subtle/40' => $isRequired])>
                                        <td class="font-medium text-theme-primary">
                                            {{ $col['header'] ?: '(empty header)' }}
                                            @if ($isRequired)
                                                <span class="ml-1 text-xs text-red-500">*</span>
                                            @endif
                                        </td>
                                        <td class="text-xs ui-muted">
                                            @forelse (array_filter($col['samples'], fn ($v) => trim($v) !== '') as $sample)
                                                <span
                                                    class="block truncate max-w-xs"
                                                    title="{{ $sample }}"
                                                >{{ $sample }}</span>
                                            @empty
                                                <span class="italic">no data</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            <select
                                                name="mapping[{{ $col['index'] }}]"
                                                class="ui-control w-full @if($isRequired) border-theme-accent @endif"
                                            >
                                                <option value="">— Skip this column —</option>
                                                @foreach ($systemFields as $field => $label)
                                                    <option
                                                        value="{{ $field }}"
                                                        @selected($col['mapped_to'] === $field)
                                                        @if(in_array($field, $required)) data-required="1" @endif
                                                    >
                                                        {{ $label }}{{ in_array($field, $required) ? ' *' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-4 text-xs ui-muted">
                        * Required field — the import will be rejected if these are not mapped.
                    </div>
                </section>

                <div class="mt-4 flex items-center gap-3">
                    <x-primary-button>Next: Preview &rarr;</x-primary-button>
                    <a href="{{ route('modules.whatsapp.imports.index') }}" class="text-sm ui-muted hover:underline">Cancel</a>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
