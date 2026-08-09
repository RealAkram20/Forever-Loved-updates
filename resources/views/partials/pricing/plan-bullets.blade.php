{{--
    What one plan gives you, as the bullet list on its pricing card.

    Shared by the visitor pricing page and the pricing page-builder widget, which held two
    near-copies of this list and had already drifted apart on which features they mentioned.

    Only PlanFeatures::publicRows() is rendered, so a feature that has been priced but not
    yet built never appears here — selling a QR code the product cannot produce is a refund
    conversation, and on a lifetime plan a promise with no end date.

    @param \App\Models\SubscriptionPlan $plan
--}}
<ul class="mb-8 flex-1 space-y-3">
    @foreach (\App\Support\PlanFeatures::publicRows() as $key => $definition)
        @php
            $isAlways = $definition['type'] === \App\Support\PlanFeatures::TYPE_ALWAYS;
            $isBool = $definition['type'] === \App\Support\PlanFeatures::TYPE_BOOL;
            $value = $isAlways ? true : $plan->{$key};
            $included = $isAlways || ($isBool ? (bool) $value : (int) $value !== \App\Support\PlanFeatures::NONE);
            $formatted = \App\Support\PlanFeatures::format($plan, $key);
        @endphp
        <li class="flex items-start gap-2.5 text-sm {{ $included ? 'text-gray-700 dark:text-gray-300' : 'text-gray-400 dark:text-gray-500' }}">
            @if ($included)
                <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            @else
                <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            @endif
            <span>
                {{ $definition['label'] }}@unless ($isAlways || $isBool || ! $included) — {{ $formatted }}@endunless
            </span>
        </li>
    @endforeach
</ul>
