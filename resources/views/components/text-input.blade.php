@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'ui-control focus:ring-0']) }}>
