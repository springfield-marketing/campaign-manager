@php
    $items = [
        ['label' => 'Import', 'route' => 'modules.whatsapp.imports.index', 'active' => request()->routeIs('modules.whatsapp.imports.*')],
        ['label' => 'Campaign Results', 'route' => 'modules.whatsapp.campaigns.index', 'active' => request()->routeIs('modules.whatsapp.campaigns.*')],
        ['label' => 'Numbers', 'route' => 'modules.whatsapp.numbers.index', 'active' => request()->routeIs('modules.whatsapp.numbers.*')],
        ['label' => 'Unsubscribers', 'route' => 'modules.whatsapp.unsubscribers.index', 'active' => request()->routeIs('modules.whatsapp.unsubscribers.*')],
        ['label' => 'Reports', 'route' => 'modules.whatsapp.reports.index', 'active' => request()->routeIs('modules.whatsapp.index', 'modules.whatsapp.reports.*')],
    ];
@endphp

<nav aria-label="WhatsApp sections">
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                class="ui-pill px-3 py-2 {{ $item['active'] ? 'ui-pill-active' : '' }}"
                @if ($item['active']) aria-current="page" @endif
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
