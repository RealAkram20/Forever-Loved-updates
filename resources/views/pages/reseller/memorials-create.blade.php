@extends('layouts.app')

@section('content')
    {{--
        Reseller intake. The memorial sections below are the same partials the platform's
        own /memorials/create renders — staff sitting with a family can record everything
        they're told in one pass, instead of filling a seven-field stub and leaving the
        rest to a screen the family may never open.

        Only the client and the deceased's name are required. Everything else can be
        finished later on the memorial's edit screen.
    --}}
    <x-common.page-breadcrumb pageTitle="Create Memorial for a Client" />

    <x-common.flash />

    @php $remaining = $reseller->memorialsRemaining(); @endphp

    @if ($remaining === 0)
        {{-- Out of quota: replace the form rather than disable it. A form you can fill in
             but never submit is worse than no form. --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-6 py-12 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.75v5M12 16h.01"/></svg>
            <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">You've used all {{ number_format($reseller->memorialAllowance()) }} profiles in your {{ $reseller->tier?->name ?? 'current' }} tier</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                Your existing memorials are unaffected. Get in touch to raise your allowance
                @if ($reseller->tier?->price_per_additional_profile > 0)
                    — additional profiles are {{ \App\Helpers\PriceHelper::format($reseller->tier->price_per_additional_profile) }} each.
                @else
                    .
                @endif
            </p>
            <a href="{{ route('reseller.memorials') }}" class="btn btn-secondary btn-md mt-5">Back to memorials</a>
        </div>
    @else
        <form method="POST" action="{{ route('reseller.memorials.store') }}" x-data="memorialCreateForm()">
            @csrf

            @if ($remaining !== null && $remaining <= 5)
                <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/25 dark:bg-amber-900/20">
                    <p class="text-sm text-amber-800 dark:text-amber-300">
                        {{ $remaining }} of {{ number_format($reseller->memorialAllowance()) }} profiles left in your {{ $reseller->tier?->name }} tier.
                    </p>
                </div>
            @endif

            <div class="space-y-6">
                @include('pages.reseller.partials.client-card', ['step' => 1])
                @include('pages.memorials.partials.form-identity', ['step' => 2])
                @include('pages.memorials.partials.form-biography-summary', ['step' => 3])
                @include('pages.memorials.partials.form-birth', ['step' => 4])
                @include('pages.memorials.partials.form-passed-away', ['step' => 5])
                @include('pages.memorials.partials.form-family', ['step' => 6])
                @include('pages.memorials.partials.form-education', ['step' => 7])
                @include('pages.memorials.partials.form-biography-editor', ['step' => null])
                @include('pages.memorials.partials.form-settings', [
                    'step' => null,
                    'plans' => $plans,
                    'defaultPlanId' => $defaultPlanId,
                ])

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn btn-primary btn-md">Create Memorial</button>
                    <a href="{{ route('reseller.memorials') }}" class="btn btn-secondary btn-md">Cancel</a>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Anything left blank can be filled in later — you'll be able to keep editing after this.
                    </p>
                </div>
            </div>
        </form>

        @include('pages.memorials.partials.form-scripts')
    @endif
@endsection
