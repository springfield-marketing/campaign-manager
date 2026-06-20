<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Script performance (all campaigns)</x-slot>
        <x-slot name="description">
            Answer, interest and lead rates per IVR script, aggregated across every campaign that
            used it. <strong>Answer %</strong> is of all calls; <strong>Interested %</strong> and
            <strong>Lead %</strong> (interested + more info) are of answered calls. Sorted by call volume.
        </x-slot>

        @php($rows = $this->rows())

        @if (empty($rows))
            <p class="text-sm text-gray-500 dark:text-gray-400">No call records are linked to a script yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4 text-left font-medium">Script</th>
                            <th class="py-2 px-2 text-right font-medium">Campaigns</th>
                            <th class="py-2 px-2 text-right font-medium">Calls</th>
                            <th class="py-2 px-2 text-right font-medium">Answered</th>
                            <th class="py-2 px-2 text-right font-medium">Answer %</th>
                            <th class="py-2 px-2 text-right font-medium">Interested</th>
                            <th class="py-2 px-2 text-right font-medium">Interested %</th>
                            <th class="py-2 px-2 text-right font-medium">More info</th>
                            <th class="py-2 px-2 text-right font-medium">Unsub</th>
                            <th class="py-2 pl-2 text-right font-medium">Lead %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($row['campaigns']) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($row['total_calls']) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($row['answered']) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ $row['answer_rate'] }}%</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($row['interested']) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums font-semibold text-primary-600 dark:text-primary-400">{{ $row['interested_rate'] }}%</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($row['more_info']) }}</td>
                                <td class="py-2 px-2 text-right tabular-nums">{{ number_format($row['unsubscribed']) }}</td>
                                <td class="py-2 pl-2 text-right tabular-nums font-semibold">{{ $row['lead_rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
