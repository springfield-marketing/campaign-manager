@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-theme-secondary']) }}>
        {{ $status }}
    </div>
@endif
