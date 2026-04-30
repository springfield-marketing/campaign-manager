<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center rounded-[4px] border border-[#8C8C8C] bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-[#262526] focus:outline-none disabled:opacity-25']) }}>
    {{ $slot }}
</button>
