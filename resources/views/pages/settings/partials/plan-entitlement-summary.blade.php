{{--
    What a plan grants, as a read-only list on its card. Admin and reseller screens both.

    Formatting goes through PlanFeatures::format() so "Unlimited", "—" and "12 GB" read the
    same here as they do on the public pricing table.

    @param \App\Models\SubscriptionPlan $plan
--}}
<div class="mb-4 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
    @foreach (\App\Support\PlanFeatures::stored() as $key => $definition)
        @php
            $isBool = $definition['type'] === \App\Support\PlanFeatures::TYPE_BOOL;
            $value = $plan->{$key};
            $on = $isBool ? (bool) $value : (int) $value !== \App\Support\PlanFeatures::NONE;
        @endphp
        <div class="flex items-center gap-2 {{ $definition['available'] ? '' : 'opacity-60' }}">
            @if ($on)
                <svg class="h-4 w-4 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            @else
                <svg class="h-4 w-4 shrink-0 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            @endif
            <span class="{{ $on ? '' : 'text-gray-400' }}">
                {{ $definition['label'] }}@unless ($isBool): {{ \App\Support\PlanFeatures::format($plan, $key) }}@endunless
            </span>
            @unless ($definition['available'])
                <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">not built</span>
            @endunless
        </div>
    @endforeach
</div>
