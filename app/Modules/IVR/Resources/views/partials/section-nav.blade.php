@php
    $items = [
        ['label' => 'Import', 'route' => 'modules.ivr.imports.index', 'active' => request()->routeIs('modules.ivr.imports.*')],
        ['label' => 'Campaign Results', 'route' => 'modules.ivr.results.index', 'active' => request()->routeIs('modules.ivr.results.*')],
        ['label' => 'Numbers', 'route' => 'modules.ivr.numbers.index', 'active' => request()->routeIs('modules.ivr.numbers.*')],
        ['label' => 'Reports', 'route' => 'modules.ivr.index', 'active' => request()->routeIs('modules.ivr.index', 'modules.ivr.reports.*')],
    ];
@endphp

<nav aria-label="IVR sections">
    <div class="flex flex-wrap gap-2 text-sm">
        @foreach ($items as $item)
            <a
                href="{{ route($item['route']) }}"
                class="rounded-[4px] border px-3 py-2 {{ $item['active'] ? 'border-[#D3D1CB] bg-[#F1F1EF] text-[#191919]' : 'border-[#E9E9E7] bg-white text-[#6B6A67]' }}"
                @if ($item['active']) aria-current="page" @endif
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
