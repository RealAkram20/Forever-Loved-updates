{{--
    The body of the plan comparison table — one row per feature, one cell per plan.

    Shared by the visitor pricing page and the pricing page-builder widget, which each carried
    their own copy of the feature list.

    @param \Illuminate\Support\Collection<\App\Models\SubscriptionPlan> $plans
--}}
@foreach (\App\Support\PlanFeatures::publicRows() as $key => $definition)
    <tr class="transition hover:bg-gray-50/50 dark:hover:bg-gray-800/50">
        <td class="px-6 py-3.5 text-sm text-gray-700 dark:text-gray-300">{{ $definition['label'] }}</td>
        @foreach ($plans as $plan)
            @php
                $isAlways = $definition['type'] === \App\Support\PlanFeatures::TYPE_ALWAYS;
                $isBool = $definition['type'] === \App\Support\PlanFeatures::TYPE_BOOL;
                $formatted = \App\Support\PlanFeatures::format($plan, $key);
            @endphp
            <td class="px-6 py-3.5 text-center text-sm">
                {{-- A tick, a cross, or a figure. format() decides, so "Unlimited", "—" and
                     "12 GB" read the same here as they do on the admin screen. --}}
                @if ($isAlways || $isBool)
                    @if ($formatted === '✓')
                        <svg class="inline h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        <svg class="inline h-5 w-5 text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    @endif
                @elseif ($formatted === '—')
                    <svg class="inline h-5 w-5 text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                @else
                    <span class="font-medium text-gray-900 dark:text-white">{{ $formatted }}</span>
                @endif
            </td>
        @endforeach
    </tr>
@endforeach
