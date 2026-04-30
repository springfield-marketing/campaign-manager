<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-[4px] border border-[#262526] bg-[#262526] px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white focus:outline-none']) }}>
    {{ $slot }}
</button>
