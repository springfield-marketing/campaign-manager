@props(['active'])

@php
$classes = ($active ?? false)
            ? 'ui-nav-active inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 focus:outline-none'
            : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-theme-muted focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
