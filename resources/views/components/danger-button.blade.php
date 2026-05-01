<button {{ $attributes->merge(['type' => 'submit', 'class' => 'ui-button px-4 text-xs uppercase tracking-widest focus:outline-none']) }}>
    {{ $slot }}
</button>
