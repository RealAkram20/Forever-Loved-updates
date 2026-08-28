{{--
    The section label: a short gold rule, then the words, very small and widely tracked.

    @param string $text
    @param bool   $centered  Centres the pair and puts a rule on both sides.
    @param bool   $light     For use on the dark sections.
--}}
@php
    $centered = $centered ?? false;
    $light = $light ?? false;
@endphp

<div class="flex items-center gap-4 {{ $centered ? 'justify-center' : '' }}">
    <span aria-hidden="true" class="h-px w-10 shrink-0 bg-[var(--dg-gold)]"></span>
    <span class="text-[10px] font-bold uppercase tracking-[0.22em] {{ $light ? 'text-white/80' : 'text-[var(--dg-ink-soft)]' }}">{{ $text }}</span>
    @if ($centered)
        <span aria-hidden="true" class="h-px w-10 shrink-0 bg-[var(--dg-gold)]"></span>
    @endif
</div>
