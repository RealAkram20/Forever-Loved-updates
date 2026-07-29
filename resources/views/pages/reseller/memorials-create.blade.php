@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create Memorial for a Client" />

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
    @endif

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
    <x-common.component-card title="Client &amp; Memorial Details" desc="We'll email the client an invitation so they can sign in and help finish the memorial.">
        @if ($remaining !== null && $remaining <= 5)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/25 dark:bg-amber-900/20">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    {{ $remaining }} of {{ number_format($reseller->memorialAllowance()) }} profiles left in your {{ $reseller->tier?->name }} tier.
                </p>
            </div>
        @endif

        <form action="{{ route('reseller.memorials.store') }}" method="POST" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Client Name</label>
                    <input type="text" name="client_name" value="{{ old('client_name') }}" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('client_name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Client Email</label>
                    <input type="email" name="client_email" value="{{ old('client_email') }}" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('client_email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name of the Deceased</label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" required
                    class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                @error('full_name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Date of Passing</label>
                    <input type="date" name="date_of_passing" value="{{ old('date_of_passing') }}"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Short Description</label>
                <textarea name="short_description" rows="3"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">{{ old('short_description') }}</textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="hidden" name="is_public" value="0">
                <input type="checkbox" name="is_public" value="1" checked class="rounded border-gray-300 dark:border-gray-700 text-brand-500 focus:ring-brand-500" />
                Public memorial
            </label>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary btn-md">Create Memorial</button>
            </div>
        </form>
    </x-common.component-card>
    @endif
@endsection
