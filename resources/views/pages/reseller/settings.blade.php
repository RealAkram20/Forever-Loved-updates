@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Settings" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</div>
    @endif

    <div class="space-y-6">
        <x-common.component-card title="Business Name">
            <form action="{{ route('reseller.settings.update') }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div class="max-w-md">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Business Name</label>
                    <input type="text" name="name" value="{{ $reseller->name }}" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-md">Save</button>
            </form>
        </x-common.component-card>

        <x-common.component-card title="Account Details" desc="Your subdomain and plan tier are managed by the platform admin.">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Subdomain</dt>
                    <dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $reseller->slug }}.{{ config('reseller.domain') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Tier</dt>
                    <dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ $reseller->tier?->name ?? 'Not assigned' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ ucfirst($reseller->status) }}</dd>
                </div>
            </dl>
        </x-common.component-card>

        <x-common.component-card title="Custom Domain" desc="Use your own domain for your memorial pages instead of your {{ $reseller->slug }}.{{ config('reseller.domain') }} subdomain.">
            @if (! $domainsEnabled)
                <p class="text-sm text-gray-500 dark:text-gray-400">This isn't turned on yet — ask your platform admin to enable custom domains.</p>
            @elseif (! $domainRoutingInTier)
                {{-- Distinct from the message above on purpose: "not available yet" and
                     "not in what you bought" call for different next steps. --}}
                <div class="flex items-start gap-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-white/[0.03] p-4">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><path d="M7 10.5V7.5a5 5 0 0 1 10 0v3M5.75 10.5h12.5a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5H5.75a1.5 1.5 0 0 1-1.5-1.5v-7a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">Not included in your {{ $reseller->tier?->name ?? 'current' }} tier</p>
                        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Your memorials stay available at
                            <span class="font-mono">{{ $reseller->slug }}.{{ config('reseller.domain') }}</span>.
                            Get in touch if you'd like to use your own domain.
                        </p>
                    </div>
                </div>
            @else
                <form action="{{ route('reseller.settings.domain.update') }}" method="POST" class="max-w-md space-y-4">
                    @csrf @method('PUT')
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
                        <input type="text" name="custom_domain" value="{{ old('custom_domain', $reseller->custom_domain) }}"
                            placeholder="memorials.yourbusiness.com"
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('custom_domain') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-md">{{ $reseller->custom_domain ? 'Update Domain' : 'Save Domain' }}</button>
                </form>

                @if ($reseller->custom_domain)
                    <div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-6 space-y-5">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                {{ match ($reseller->custom_domain_status) {
                                    'verified' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                    'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                } }}">
                                {{ ucfirst($reseller->custom_domain_status) }}
                            </span>
                        </div>

                        @unless ($reseller->hasVerifiedCustomDomain())
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Step 1 — prove you own this domain</p>
                                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Add this TXT record at your domain registrar or DNS provider, then verify:</p>
                                <div class="overflow-x-auto rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs text-gray-700 dark:text-gray-300 space-y-1">
                                    <p><span class="text-gray-500 dark:text-gray-400">Type:</span> TXT</p>
                                    <p><span class="text-gray-500 dark:text-gray-400">Host:</span> _foreverloved-verify.{{ $reseller->custom_domain }}</p>
                                    <p><span class="text-gray-500 dark:text-gray-400">Value:</span> {{ $reseller->custom_domain_token }}</p>
                                </div>
                                <form action="{{ route('reseller.settings.domain.verify') }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Verify Now</button>
                                </form>
                                @if ($reseller->custom_domain_status === 'failed')
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">DNS changes can take a while to propagate — if you just added the record, try again shortly.</p>
                                @endif
                            </div>
                        @else
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Step 2 — point your domain here</p>
                                @if ($domainTargetHost)
                                    <div class="overflow-x-auto rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs text-gray-700 dark:text-gray-300 space-y-1">
                                        <p><span class="text-gray-500 dark:text-gray-400">Type:</span> CNAME</p>
                                        <p><span class="text-gray-500 dark:text-gray-400">Host:</span> {{ $reseller->custom_domain }}</p>
                                        <p><span class="text-gray-500 dark:text-gray-400">Value:</span> {{ $domainTargetHost }}</p>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">SSL for your domain is set up separately once this is pointed correctly — your platform admin will confirm once it's live.</p>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Ownership verified — waiting on your platform admin to finish setting up where domains should point. Check back soon.</p>
                                @endif
                            </div>
                        @endunless
                    </div>
                @endif
            @endif
        </x-common.component-card>

        <x-common.component-card title="More Settings">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <a href="{{ route('reseller.branding') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <p class="font-medium text-gray-800 dark:text-white/90">Branding</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Logo, favicon, and primary color</p>
                </a>
                <a href="{{ route('reseller.payments') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <p class="font-medium text-gray-800 dark:text-white/90">Payment Settings</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your own Pesapal account</p>
                </a>
                <a href="{{ route('profile.edit') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <p class="font-medium text-gray-800 dark:text-white/90">Your Profile</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Personal name, email, password, notifications</p>
                </a>
            </div>
        </x-common.component-card>
    </div>
@endsection
