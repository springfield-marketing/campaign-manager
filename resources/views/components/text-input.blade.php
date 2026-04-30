@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-[4px] border-[#8C8C8C] focus:border-[#595859] focus:ring-0']) }}>
