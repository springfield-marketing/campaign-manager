{{-- Dry-run preview for the "Split numbers" action. $plan comes from ClientSplitter::preview(). --}}
<div class="space-y-3 text-sm">
    <p class="text-gray-600 dark:text-gray-400">
        The <strong>anchor</strong> number stays on this contact (keeping its name, history and
        client-level data). Each other number moves to its own contact, named from its own source
        history — or left blank when no real name is recoverable. All campaign history follows each
        number. A snapshot is saved first, so this is reversible.
    </p>

    <table class="w-full border-collapse">
        <thead>
            <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                <th class="py-1 pr-3 font-medium">Number</th>
                <th class="py-1 pr-3 font-medium">Outcome</th>
                <th class="py-1 pr-3 font-medium">Becomes contact</th>
                <th class="py-1 pr-3 font-medium text-right">WA msgs</th>
                <th class="py-1 font-medium text-right">IVR calls</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plan['rows'] as $row)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-1 pr-3 font-mono">{{ $row['phone'] }}</td>
                    <td class="py-1 pr-3">
                        @if ($row['role'] === 'anchor')
                            <span class="text-primary-600 dark:text-primary-400 font-medium">Stays (anchor)</span>
                        @else
                            <span class="text-warning-600 dark:text-warning-400">Moves out</span>
                        @endif
                    </td>
                    <td class="py-1 pr-3">
                        @if ($row['name'])
                            {{ $row['name'] }}
                        @else
                            <span class="italic text-gray-400">(no name recoverable)</span>
                        @endif
                    </td>
                    <td class="py-1 pr-3 text-right tabular-nums">{{ number_format($row['messages']) }}</td>
                    <td class="py-1 text-right tabular-nums">{{ number_format($row['calls']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
