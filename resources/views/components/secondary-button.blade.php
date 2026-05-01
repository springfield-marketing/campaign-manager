<button {{ $attributes->merge(['type' => 'button', 'class' => 'ui-button-subtle px-4 text-xs uppercase tracking-widest focus:outline-none disabled:opacity-25']) }}>
    {{ $slot }}
</button>
