@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full border-l-4 border-[#262526] bg-[#D9D9D9] py-2 pe-4 ps-3 text-start text-base font-medium text-[#0D0D0D] focus:outline-none'
            : 'block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-start text-base font-medium text-[#595859] focus:outline-none';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
