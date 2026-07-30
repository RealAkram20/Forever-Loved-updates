@props(['href', 'label' => 'Back'])

{{-- Back navigation, as its own component so the three report screens cannot drift apart
     and a fourth cannot reinvent it.

     Three things make it read as *back* rather than as another action:
     the arrow points left, it sits above the title instead of in the actions row, and it
     is visually quieter than anything it sits near. The arrow nudges left on hover,
     mirroring the catalogue card's chevron nudging right — forward and back get opposite
     motion, so direction is legible before the label is read.

     No entrance animation on purpose: this appears on every report page, many times a day.
     Press feedback only. --}}
<a href="{{ $href }}"
    {{ $attributes->merge([
        'class' =>
            'group mb-4 inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 ' .
            'transition-[transform,background-color,border-color,color] duration-150 ease-out ' .
            'hover:border-gray-300 hover:bg-gray-50 hover:text-gray-900 active:scale-[0.97] ' .
            'dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:border-gray-600 dark:hover:bg-white/[0.08] dark:hover:text-white',
    ]) }}>
    <svg class="h-4 w-4 transition-transform duration-150 ease-out group-hover:-translate-x-0.5"
        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M15 18l-6-6 6-6" />
    </svg>
    {{ $label }}
</a>
