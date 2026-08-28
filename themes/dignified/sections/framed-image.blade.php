{{--
    The photograph treatment this template uses everywhere: two thin rectangles the same size
    as the picture, one nudged up-left in gold and one down-right in crimson, with the picture
    itself sitting on top. Only an L of each is ever visible, which is what makes it read as a
    frame rather than as two stray boxes.

    Built from two elements rather than one bordered box with a gradient, because a gradient
    border blends gold into crimson through brown across the middle of every edge — and brown
    is the one colour this palette cannot afford.

    @param string $src
    @param string $alt
    @param string $ratio  Tailwind aspect class, e.g. 'aspect-[4/5]'
--}}
@php
    $ratio = $ratio ?? 'aspect-[5/6]';
    $alt = $alt ?? '';
@endphp

<div class="relative mx-auto w-full max-w-[365px] px-5 py-5 sm:px-0 sm:py-0">
    <div class="relative">
        <span aria-hidden="true" class="pointer-events-none absolute -left-4 -top-4 bottom-4 right-4 border border-[var(--dg-gold)] sm:-left-5 sm:-top-5 sm:bottom-5 sm:right-5"></span>
        <span aria-hidden="true" class="pointer-events-none absolute -bottom-4 -right-4 left-4 top-4 border border-[var(--dg-red)] sm:-bottom-5 sm:-right-5 sm:left-5 sm:top-5"></span>

        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy"
            class="relative block w-full {{ $ratio }} object-cover" />
    </div>
</div>
