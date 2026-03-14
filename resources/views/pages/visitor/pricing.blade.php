@extends('layouts.visitor')

@section('page')

{{-- Header --}}
<section class="bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-900 py-16 sm:py-20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600 dark:text-brand-400">Pricing</p>
            <h1 class="mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl">Choose Your Memorial Plan</h1>
            <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">Start free, upgrade when you need more. Every plan includes a beautiful memorial page.</p>
        </div>

        {{-- Plan Cards --}}
        {{-- lg:grid-cols-2 lg:grid-cols-3 --}}
        <div class="mt-12 grid gap-6 sm:grid-cols-2 {{ $plans->count() >= 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2' }} max-w-4xl mx-auto">
            @foreach ($plans as $plan)
            @php
                $isFree = $plan->isFree();
                $isPopular = !$isFree && $plans->count() > 1;
            @endphp
            <div class="relative rounded-2xl border {{ $isPopular ? 'border-brand-500 dark:border-brand-400 ring-2 ring-brand-500/20' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-800 p-6 sm:p-8 flex flex-col">
                @if ($isPopular)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-4 py-1 text-xs font-semibold text-white">Most Popular</div>
                @endif

                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $plan->name }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $plan->description }}</p>
                </div>

                <div class="mb-6">
                    <div class="flex items-baseline gap-1">
                        @if ($isFree)
                            <span class="text-4xl font-bold text-gray-900 dark:text-white">Free</span>
                        @else
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $currency }}</span>
                            <span class="text-4xl font-bold text-gray-900 dark:text-white">{{ number_format($plan->price, 2) }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/{{ $plan->interval }}</span>
                        @endif
                    </div>
                </div>

                {{-- Feature List --}}
                <ul class="mb-8 space-y-3 flex-1">
                    <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ $plan->memorial_limit == 0 ? 'Unlimited' : $plan->memorial_limit }} {{ Str::plural('memorial', $plan->memorial_limit ?: 2) }}
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ $plan->storage_limit_mb >= 1024 ? ($plan->storage_limit_mb / 1024) . ' GB' : $plan->storage_limit_mb . ' MB' }} storage
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ $plan->max_gallery_images == 0 ? 'Unlimited' : $plan->max_gallery_images }} gallery {{ Str::plural('photo', $plan->max_gallery_images ?: 2) }}
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ $plan->max_tributes == 0 ? 'Unlimited' : $plan->max_tributes }} tributes
                    </li>
                    <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        {{ $plan->max_chapters == 0 ? 'Unlimited' : $plan->max_chapters }} story {{ Str::plural('chapter', $plan->max_chapters ?: 2) }}
                    </li>
                    @if ($plan->max_ai_bio_per_day > 0)
                    <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                        <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        AI biography ({{ $plan->max_ai_bio_per_day }}/day)
                    </li>
                    @else
                    <li class="flex items-start gap-2.5 text-sm text-gray-400 dark:text-gray-500">
                        <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        AI biography
                    </li>
                    @endif
                    @foreach ([
                        ['flag' => 'feature_background_music', 'label' => 'Background music'],
                        ['flag' => 'feature_no_ads', 'label' => 'Ad-free experience'],
                        ['flag' => 'feature_never_expires', 'label' => 'Never expires'],
                        ['flag' => 'feature_share_memories', 'label' => 'Share memories'],
                    ] as $feature)
                        @if ($plan->{$feature['flag']})
                        <li class="flex items-start gap-2.5 text-sm text-gray-700 dark:text-gray-300">
                            <svg class="h-5 w-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            {{ $feature['label'] }}
                        </li>
                        @else
                        <li class="flex items-start gap-2.5 text-sm text-gray-400 dark:text-gray-500">
                            <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            {{ $feature['label'] }}
                        </li>
                        @endif
                    @endforeach
                </ul>

                <a href="{{ route('memorial.create.step1') }}"
                   class="block w-full rounded-xl {{ $isPopular ? 'bg-brand-500 text-white hover:bg-brand-600' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-600' }} py-3 text-center text-sm font-semibold transition">
                    {{ $isFree ? 'Get Started Free' : 'Select Plan' }}
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Feature Comparison Table --}}
<section class="bg-white dark:bg-gray-900 py-16 sm:py-20">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <h2 class="text-center text-2xl font-bold text-gray-900 dark:text-white mb-10">Compare Plans</h2>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full min-w-[600px]">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-white">Feature</th>
                        @foreach ($plans as $plan)
                            <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-white">{{ $plan->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @php
                        $features = [
                            ['label' => 'Memorials', 'key' => 'memorial_limit', 'type' => 'limit'],
                            ['label' => 'Storage', 'key' => 'storage_limit_mb', 'type' => 'storage'],
                            ['label' => 'Gallery Photos', 'key' => 'max_gallery_images', 'type' => 'limit'],
                            ['label' => 'Gallery Videos', 'key' => 'max_gallery_videos', 'type' => 'limit'],
                            ['label' => 'Tributes', 'key' => 'max_tributes', 'type' => 'limit'],
                            ['label' => 'Story Chapters', 'key' => 'max_chapters', 'type' => 'limit'],
                            ['label' => 'AI Biography', 'key' => 'max_ai_bio_per_day', 'type' => 'daily'],
                            ['label' => 'Background Music', 'key' => 'feature_background_music', 'type' => 'bool'],
                            ['label' => 'Advanced Privacy', 'key' => 'feature_advanced_privacy', 'type' => 'bool'],
                            ['label' => 'Guest Notifications', 'key' => 'feature_guest_notifications', 'type' => 'bool'],
                            ['label' => 'Never Expires', 'key' => 'feature_never_expires', 'type' => 'bool'],
                            ['label' => 'Ad-Free', 'key' => 'feature_no_ads', 'type' => 'bool'],
                            ['label' => 'Share Memories', 'key' => 'feature_share_memories', 'type' => 'bool'],
                        ];
                    @endphp
                    @foreach ($features as $feature)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                        <td class="px-6 py-3.5 text-sm text-gray-700 dark:text-gray-300">{{ $feature['label'] }}</td>
                        @foreach ($plans as $plan)
                            <td class="px-6 py-3.5 text-center text-sm">
                                @if ($feature['type'] === 'bool')
                                    @if ($plan->{$feature['key']})
                                        <svg class="inline h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    @else
                                        <svg class="inline h-5 w-5 text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    @endif
                                @elseif ($feature['type'] === 'storage')
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $plan->{$feature['key']} >= 1024 ? ($plan->{$feature['key']} / 1024) . ' GB' : $plan->{$feature['key']} . ' MB' }}</span>
                                @elseif ($feature['type'] === 'daily')
                                    @if ($plan->{$feature['key']} > 0)
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $plan->{$feature['key']} }}/day</span>
                                    @else
                                        <svg class="inline h-5 w-5 text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    @endif
                                @else
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $plan->{$feature['key']} == 0 ? 'Unlimited' : $plan->{$feature['key']} }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>

{{-- Trust Section --}}
<section class="bg-gray-50 dark:bg-gray-800/50 py-12">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 sm:grid-cols-3 text-center">
            <div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 mx-auto mb-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Secure & Encrypted</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SSL encryption protects all your data and memories.</p>
            </div>
            <div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 mx-auto mb-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Cancel Anytime</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No lock-in contracts. Downgrade or cancel whenever you need.</p>
            </div>
            <div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 mx-auto mb-3">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Dedicated Support</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Our team is here to help you every step of the way.</p>
            </div>
        </div>
    </div>
</section>

@endsection
