{{--
    The entitlement half of a plan form, for the admin screen and the reseller one alike.

    Rendered from App\Support\PlanFeatures rather than written out, because this list used to
    exist by hand in six places and had already begun to disagree with itself — the admin card
    showed 0 as "∞" while the public table showed it as "Unlimited", and neither could say
    "not included". Adding an entitlement is now one entry in the catalogue.

    @param \App\Models\SubscriptionPlan|null $plan  null when creating
    @param string $idPrefix                        unique per form on the page
--}}
@php
    $plan = $plan ?? null;
    $idPrefix = $idPrefix ?? 'plan-new';
    $inputClass = 'h-9 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:outline-hidden';
@endphp

@foreach (\App\Support\PlanFeatures::storedByGroup() as $group => $entries)
    <p class="mt-4 mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">
        {{ $group }}
        @if ($group === 'Coming')
            <span class="ml-1 font-normal">— priced now, not built yet. Hidden from customers until each one ships.</span>
        @endif
    </p>

    @php
        $numbers = array_filter($entries, fn ($d) => $d['type'] !== \App\Support\PlanFeatures::TYPE_BOOL);
        $flags = array_filter($entries, fn ($d) => $d['type'] === \App\Support\PlanFeatures::TYPE_BOOL);
    @endphp

    @if ($numbers)
        <div class="grid grid-cols-2 gap-3">
            @foreach ($numbers as $key => $definition)
                @php
                    $isDaily = $definition['type'] === \App\Support\PlanFeatures::TYPE_DAILY;
                    $value = old($key, $plan?->{$key} ?? $definition['default']);
                @endphp
                <div>
                    <label for="{{ $idPrefix }}-{{ $key }}" class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                        {{ $definition['label'] }}
                        @if ($definition['type'] === \App\Support\PlanFeatures::TYPE_STORAGE)
                            <span class="text-gray-400">(MB)</span>
                        @endif
                    </label>
                    <input type="number" id="{{ $idPrefix }}-{{ $key }}" name="{{ $key }}" value="{{ $value }}"
                        min="{{ $isDaily ? 0 : -1 }}"
                        {{-- Spelled out on every field. Getting this backwards is how a free
                             plan ends up granting unlimited video. --}}
                        placeholder="{{ $isDaily ? '0 = off' : '-1 = unlimited, 0 = none' }}"
                        class="{{ $inputClass }}" />
                    @if ($definition['help'])
                        <p class="mt-1 text-[11px] leading-snug text-gray-400 dark:text-gray-500">{{ $definition['help'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($flags)
        <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-2">
            @foreach ($flags as $key => $definition)
                <label class="flex items-start gap-2 text-xs text-gray-700 dark:text-gray-300" title="{{ $definition['help'] }}">
                    {{-- An unchecked checkbox sends nothing, so the hidden field is what lets
                         a feature be switched back off. --}}
                    <input type="hidden" name="{{ $key }}" value="0">
                    <input type="checkbox" name="{{ $key }}" value="1"
                        @checked(old($key, $plan?->{$key} ?? $definition['default']))
                        class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                    <span>{{ $definition['label'] }}</span>
                </label>
            @endforeach
        </div>
    @endif
@endforeach
