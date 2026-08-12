{{--
    Settings: theme, visibility, and — where the caller offers a catalogue — which plan
    covers the memorial.

    $plans is optional. Without it (the platform's own /memorials/create) the plan is
    fixed to free with an Upgrade link, exactly as before. With it (reseller intake)
    staff pick from that reseller's own plans, so a memorial is created already carrying
    the entitlements the family is paying that reseller for.
--}}
@php
    $planOptions = $plans ?? null;
    $currency = $currency ?? \App\Models\SystemSetting::get('payments.currency', 'USD');
    $selectedPlanId = old('plan_id', $defaultPlanId ?? null);
@endphp

<x-common.component-card :title="($step ?? null) ? $step.'. Settings' : 'Settings'">
    <div class="space-y-5">
        <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Theme</label>
            <input type="hidden" name="theme" value="free" />
            <div class="flex h-11 w-full items-center rounded-lg border border-gray-300 bg-gray-50 px-4 text-sm text-gray-700 dark:border-gray-600 dark:bg-white/5 dark:text-gray-300">
                Classic
            </div>
        </div>

        @if ($planOptions && $planOptions->isNotEmpty())
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($planOptions as $plan)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-3.5 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/60 dark:border-gray-700 dark:has-[:checked]:bg-brand-500/10">
                            <input type="radio" name="plan_id" value="{{ $plan->id }}"
                                {{ (int) $selectedPlanId === (int) $plan->id ? 'checked' : '' }}
                                class="mt-0.5 border-gray-300 text-brand-600 focus:ring-brand-500" />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ $plan->name }}</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    @if ($plan->isFree())
                                        Free
                                    @else
                                        {{ $currency }} {{ \App\Helpers\PriceHelper::format($plan->price) }}
                                        @if ($plan->interval) / {{ $plan->interval }} @endif
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('plan_id')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Paid plans are recorded as collected by you directly — the family is not asked to pay here.
                </p>
            </div>
        @else
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                <input type="hidden" name="plan" value="free" />
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        Free
                    </span>
                    <a href="{{ route('pricing') }}"
                        class="btn btn-primary btn-sm">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        Upgrade
                    </a>
                </div>
            </div>
        @endif

        <div>
            <label class="flex cursor-pointer items-center gap-2">
                <input type="hidden" name="is_public" value="0" />
                <input type="checkbox" name="is_public" value="1" {{ old('is_public', true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Public memorial (visible to everyone)</span>
            </label>
        </div>
    </div>
</x-common.component-card>
