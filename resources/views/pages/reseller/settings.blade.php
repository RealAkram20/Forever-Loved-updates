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
        <x-common.component-card title="Business details">
            <form action="{{ route('reseller.settings.update') }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div class="max-w-md">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Business Name</label>
                    <input type="text" name="name" value="{{ $reseller->name }}" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-md">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Contact email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $reseller->contact_email) }}"
                        placeholder="enquiries@yourbusiness.com"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Where messages from your Contact page are sent. Leave blank and they come to us to forward on.
                    </p>
                    @error('contact_email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="max-w-md">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300" for="tagline">Footer description</label>
                    <textarea id="tagline" name="tagline" rows="3"
                        placeholder="Providing dignified funeral services with compassion, respect and professionalism."
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm leading-relaxed text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90">{{ old('tagline', $tagline) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        The line under your logo in the footer. Leave blank and nothing is shown there.
                    </p>
                    @error('tagline') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-md">Save</button>
            </form>
        </x-common.component-card>

        {{-- Contact & Location.

             These are the facts a visitor needs to reach the business, and they are what the
             site's contact section and footer render. Kept here rather than on the Appearance
             or Theme page because they survive a change of theme: an address is not a design
             choice. --}}
        <x-common.component-card
            title="Contact & Location"
            desc="Shown on your contact page, in your footer, and anywhere your theme asks for them. Leave anything blank to hide that line.">
            <form action="{{ route('reseller.settings.contact.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                @php
                    // Not `use ... as CD`: Blade compiles this template into a nested
                    // block and PHP only allows an import at the top level of a file, so
                    // that form is a parse error and took the whole page down with it.
                    $CD = \App\Support\SiteContactDetails::class;
                    $inputCls = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900/80 dark:text-white/90';
                    $areaCls = str_replace('h-11 ', '', $inputCls).' py-3 leading-relaxed';
                    $labelCls = 'mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300';
                    $hintCls = 'mt-1.5 text-xs text-gray-500 dark:text-gray-400';
                @endphp

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div>
                        <label class="{{ $labelCls }}" for="c-phone">Phone</label>
                        <input id="c-phone" type="text" name="contact[{{ $CD::PHONE }}]" maxlength="40"
                            value="{{ old('contact.'.$CD::PHONE, $contact[$CD::PHONE] ?? '') }}"
                            placeholder="+256 200 123 456" class="{{ $inputCls }}" />
                    </div>

                    <div>
                        <label class="{{ $labelCls }}" for="c-phone-alt">Second phone</label>
                        <input id="c-phone-alt" type="text" name="contact[{{ $CD::PHONE_ALT }}]" maxlength="40"
                            value="{{ old('contact.'.$CD::PHONE_ALT, $contact[$CD::PHONE_ALT] ?? '') }}"
                            placeholder="Optional" class="{{ $inputCls }}" />
                    </div>

                    <div>
                        <label class="{{ $labelCls }}" for="c-address">Address</label>
                        <textarea id="c-address" name="contact[{{ $CD::ADDRESS }}]" rows="3" maxlength="300"
                            placeholder="Plot 123, Kampala Road&#10;P.O. Box 5678, Kampala, Uganda"
                            class="{{ $areaCls }}">{{ old('contact.'.$CD::ADDRESS, $contact[$CD::ADDRESS] ?? '') }}</textarea>
                        <p class="{{ $hintCls }}">One line per line, the way you would write it on an envelope.</p>
                    </div>

                    <div>
                        <label class="{{ $labelCls }}" for="c-hours">Opening hours</label>
                        <textarea id="c-hours" name="contact[{{ $CD::HOURS }}]" rows="3" maxlength="300"
                            placeholder="Mon - Fri: 8:00am - 5:00pm&#10;Sat: 9:00am - 1:00pm&#10;Sun: By appointment"
                            class="{{ $areaCls }}">{{ old('contact.'.$CD::HOURS, $contact[$CD::HOURS] ?? '') }}</textarea>
                    </div>
                </div>

                <div>
                    <label class="{{ $labelCls }}" for="c-map">Map</label>
                    <textarea id="c-map" name="contact[{{ $CD::MAP_EMBED }}]" rows="3" maxlength="1000"
                        placeholder='&lt;iframe src="https://www.google.com/maps/embed?pb=..." ...&gt;&lt;/iframe&gt;'
                        class="{{ $areaCls }} font-mono text-xs">{{ old('contact.'.$CD::MAP_EMBED, $contact[$CD::MAP_EMBED] ?? '') }}</textarea>
                    <p class="{{ $hintCls }}">
                        <span class="font-medium">Optional.</span> Your address above already draws the map &mdash;
                        leave this blank unless you want a particular view, such as a satellite layer or a pin on a
                        specific entrance. To set one, find your location in Google Maps and choose
                        <span class="font-medium">Share &rarr; Embed a map</span>, then paste the whole snippet here.
                        OpenStreetMap embeds work too.
                    </p>
                    @error('contact.'.$CD::MAP_EMBED)
                        <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @foreach ([[$CD::SOCIAL_FACEBOOK, 'Facebook'], [$CD::SOCIAL_TWITTER, 'X (Twitter)'], [$CD::SOCIAL_INSTAGRAM, 'Instagram']] as [$key, $label])
                        <div>
                            <label class="{{ $labelCls }}">{{ $label }}</label>
                            <input type="url" name="contact[{{ $key }}]" maxlength="500"
                                value="{{ old('contact.'.$key, $contact[$key] ?? '') }}"
                                placeholder="https://" class="{{ $inputCls }}" />
                            @error('contact.'.$key)
                                <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary btn-md">Save contact details</button>
                </div>
            </form>
        </x-common.component-card>

        <x-common.component-card title="Account Details" desc="Your subdomain and plan tier are managed by the platform admin.">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Your address</dt>
                    <dd class="mt-1"><x-common.reseller-address :reseller="$reseller" :copy="false" /></dd>
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

        <x-common.component-card title="Custom Domain" desc="Use your own domain for your memorial pages instead of your {{ $reseller->publicHost() }} subdomain.">
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
                            <span class="font-mono">{{ $reseller->publicDisplayAddress() }}</span>.
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

                @unless ($reseller->custom_domain)
                    {{-- Shown before saving, not after. The two DNS records used to appear
                         only once a domain was already submitted, so there was no way to
                         know any DNS work was involved — the form looked like it did
                         nothing. Say what the job is before asking someone to start it. --}}
                    <div class="mt-6 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-white/[0.03] p-5">
                        <p class="text-sm font-medium text-gray-800 dark:text-white/90">What you'll need to do</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Two DNS records at whoever manages your domain — your registrar, or your web host's control panel.
                            We'll show you the exact values to copy after you save.
                        </p>
                        <ol class="mt-4 space-y-3">
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[0.6875rem] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">1</span>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    Add a <span class="font-mono font-medium text-gray-800 dark:text-gray-200">TXT</span> record so we can confirm the domain is yours.
                                </span>
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[0.6875rem] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">2</span>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    Add a <span class="font-mono font-medium text-gray-800 dark:text-gray-200">CNAME</span> record so visitors reach your memorial pages.
                                </span>
                            </li>
                        </ol>
                        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            DNS changes usually appear within minutes, but can take up to 48 hours. Your
                            <span class="font-mono">{{ $reseller->publicDisplayAddress() }}</span>
                            address keeps working throughout, so nothing goes offline while you set this up.
                        </p>
                    </div>
                @endunless

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
                                {{-- Asked for from the service that will do the lookup, so the
                                     record we tell them to add is by construction the one we
                                     check for. --}}
                                <x-common.dns-record type="TXT"
                                    :host="app(\App\Services\DomainVerificationService::class)->txtHost($reseller->custom_domain)"
                                    :value="$reseller->custom_domain_token" />
                                <form action="{{ route('reseller.settings.domain.verify') }}" method="POST" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Verify Now</button>
                                </form>
                                @if ($reseller->custom_domain_status === 'failed')
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">DNS changes can take a while to propagate — if you just added the record, try again shortly.</p>
                                @endif
                            </div>
                        @endunless

                        {{-- Shown from the moment a domain is saved, not only after verification:
                             the TXT proves ownership, this record actually brings visitors, and
                             people set all their DNS in one sitting. A root domain can't take a
                             CNAME at most providers, so it gets an A record instead. --}}
                        @php $isApex = substr_count($reseller->custom_domain, '.') === 1; @endphp
                        <div>
                            <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Step 2 — point your domain here</p>
                            @if ($isApex ? $domainTargetIp : $domainTargetHost)
                                @if ($isApex)
                                    <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Both records, so your site answers with and without <span class="font-mono">www</span>:</p>
                                    <x-common.dns-record type="A"
                                        :host="$reseller->custom_domain"
                                        :value="$domainTargetIp" />
                                    <div class="mt-2">
                                        <x-common.dns-record type="CNAME"
                                            :host="'www.'.$reseller->custom_domain"
                                            :value="$reseller->custom_domain" />
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        At most providers the host field is <span class="font-mono">@</span> for the A record and <span class="font-mono">www</span> for the CNAME.
                                    </p>
                                @else
                                    <x-common.dns-record type="CNAME"
                                        :host="$reseller->custom_domain"
                                        :value="$domainTargetHost" />
                                @endif
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    That's the complete set — nothing else to add. SSL certificates are issued and renewed automatically for {{ $isApex ? 'both addresses' : 'your address' }} once the records are in place; your site is live on the domain, fully secured, usually within a few minutes of DNS taking effect.
                                </p>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Where to point the domain is still being set up by your platform admin — you can verify ownership now and check back soon for this step.</p>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </x-common.component-card>

        <x-common.component-card title="More Settings">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <a href="{{ route('reseller.appearance') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <p class="font-medium text-gray-800 dark:text-white/90">Appearance</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Logo, favicon, colours and fonts</p>
                </a>
                <a href="{{ route('reseller.embed') }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 hover:bg-gray-50 dark:hover:bg-white/5 transition">
                    <p class="font-medium text-gray-800 dark:text-white/90">Embed on your website</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Show memorials on any site you own</p>
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
