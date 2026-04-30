<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ivr-btn-accent inline-flex items-center px-4 py-2 text-xs font-semibold uppercase tracking-widest focus:outline-none']) }}>
    {{ $slot }}
</button>
