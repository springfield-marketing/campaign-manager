@props(['active'])

@php
$classes = ($active ?? false)
            ? 'ui-nav-active block w-full border-l-4 bg-theme-subtle py-2 pe-4 ps-3 text-start text-base font-medium focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-theme-muted focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
