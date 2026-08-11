@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Reseller Settings</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Configuration that applies to the whole reseller program. Per-reseller settings live on
            <a href="{{ route('settings.resellers') }}" class="text-brand-500 hover:underline">each reseller's page</a>.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <p class="mb-1 text-sm font-medium text-red-700 dark:text-red-400">Please fix the following:</p>
            <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('settings.reseller-settings.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- ─── Defaults for new resellers ──────────────────────────── --}}
        <x-common.component-card title="New reseller defaults" desc="Applied when a reseller is created without an explicit choice.">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Default tier</label>
                <select name="default_tier_id"
                    class="h-11 w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                    <option value="">None — assign a tier manually each time</option>
                    @foreach ($tiers as $tier)
                        <option value="{{ $tier->id }}" {{ (string) old('default_tier_id', $settings['reseller.default_tier_id'] ?? '') === (string) $tier->id ? 'selected' : '' }}>
                            {{ $tier->name }} — {{ \App\Helpers\PriceHelper::format($tier->annual_price) }}/yr{{ $tier->is_active ? '' : ' (inactive)' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    @if ($tiers->isEmpty())
                        No tiers exist yet — <a href="{{ route('settings.reseller-pricing') }}" class="text-brand-500 hover:underline">create one on the Pricing page</a> first.
                    @else
                        Existing resellers are never changed by this; it only affects new ones. Manage the tiers themselves on <a href="{{ route('settings.reseller-pricing') }}" class="text-brand-500 hover:underline">Pricing</a>.
                    @endif
                </p>
            </div>
        </x-common.component-card>

        {{-- ─── Custom domains ──────────────────────────────────────── --}}
        <x-common.component-card title="Custom domains" desc="Whether resellers may point their own domain at their tenant instead of using a {{ config('reseller.domain') }} subdomain.">
            <div class="flex items-start justify-between gap-6"
                x-data="{ enabled: {{ old('custom_domains_enabled', ($settings['domains.custom_domains_enabled'] ?? false) ? '1' : '0') ? 'true' : 'false' }} }">
                <div>
                    <p class="text-sm font-medium text-gray-800 dark:text-white/90">Allow custom domains</p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">While off, resellers see a "not available yet" note in place of the domain form.</p>
                </div>
                <input type="hidden" name="custom_domains_enabled" :value="enabled ? '1' : '0'">
                <button type="button" role="switch" :aria-checked="enabled" @click="enabled = !enabled"
                    class="relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors duration-200 ease-out focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-brand-500/30"
                    :class="enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-gray-700'">
                    <span class="sr-only">Allow custom domains</span>
                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-theme-sm transition-transform duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
                        :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                </button>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">CNAME target</label>
                <input type="text" name="target_host" value="{{ old('target_host', $settings['domains.target_host'] ?? '') }}" placeholder="e.g. edge.{{ config('reseller.domain') }}"
                    class="h-11 w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 font-mono text-sm text-gray-800 dark:text-white/90 placeholder:font-sans placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    The host resellers point a CNAME at once they've proved they own their domain. Leave blank until hosting is settled — they'll see a "check back soon" note instead of a wrong instruction.
                </p>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">A-record IP</label>
                <input type="text" name="target_ip" value="{{ old('target_ip', $settings['domains.target_ip'] ?? '') }}" placeholder="e.g. 203.0.113.10"
                    class="h-11 w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 font-mono text-sm text-gray-800 dark:text-white/90 placeholder:font-sans placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                @error('target_ip') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                    For root domains like <span class="font-mono">example.com</span>, which most DNS providers won't let point at a CNAME — the instructions show an A record to this IP instead. Usually this server's public address.
                </p>
            </div>

            <div class="rounded-xl bg-gray-50 dark:bg-white/[0.03] p-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">How a domain goes live</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Resellers prove control of a domain with a TXT-record challenge, then point it here with the record shown above. Once verified, the scheduler tells the proxy about the domain automatically — routing and its SSL certificate are provisioned within about a minute of the DNS pointing at this server, with nothing to click.
                </p>
            </div>
        </x-common.component-card>

        {{-- ─── Subdomains ──────────────────────────────────────────── --}}
        <x-common.component-card title="Subdomains" desc="Every reseller gets a {slug}.{{ config('reseller.domain') }} address at creation.">
            <div>
                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Base domain</p>
                <div class="flex flex-wrap items-center gap-3">
                    <code class="rounded-lg bg-gray-50 dark:bg-white/[0.03] px-3 py-2 font-mono text-sm text-gray-800 dark:text-white/90">{{ config('reseller.domain') }}</code>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Set with <code class="font-mono text-gray-700 dark:text-gray-300">RESELLER_APP_DOMAIN</code> in <code class="font-mono text-gray-700 dark:text-gray-300">.env</code>
                    </span>
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Read-only on purpose. Wildcard subdomain routing is registered when the app boots, so changing this needs a config reload and a matching DNS record — not something to flip from a form while {{ $resellerCount }} {{ \Illuminate\Support\Str::plural('reseller', $resellerCount) }} depend on it.
                </p>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800 pt-6">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Additional reserved subdomains</label>
                <textarea name="reserved_slugs" rows="3" placeholder="blog, status, help"
                    class="w-full max-w-md rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-3 font-mono text-sm text-gray-800 dark:text-white/90 placeholder:font-sans placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">{{ old('reserved_slugs', $settings['reseller.reserved_slugs'] ?? '') }}</textarea>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Comma or space separated. Use this to fence off brand terms and upcoming product names before someone claims them.</p>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Always reserved</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($baseReservedSlugs as $slug)
                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ $slug }}</span>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Infrastructure hostnames and reserved app routes. These can't be removed.</p>
                </div>
            </div>
        </x-common.component-card>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary btn-md">Save changes</button>
        </div>
    </form>
@endsection
