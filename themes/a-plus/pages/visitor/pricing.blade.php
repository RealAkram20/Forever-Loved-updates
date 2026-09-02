@extends('layouts.visitor')

{{--
    Pricing, in A-Plus.

    The data and the shared partials are untouched: `$plans`, `$currency`, `PriceHelper`, and
    both `partials.pricing.*` includes. Those carry the plan limits and the unlimited sentinel
    formatting, and a template inventing its own copy of that arithmetic is exactly the bug the
    signup wizard shipped on 2026-08-31.

    What changes is the surfaces. Three things in the base view fight this design:

    - **The plan cards are `rounded-2xl` boxes with a brand-coloured ring.** Kept as cards here
      rather than turned into hairline columns, because a price and a call to action need an
      edge to sit inside — this is the one place in the template where a card earns its keep.
      They take the theme's radius and hairline instead of the platform's.
    - **`.btn-primary` / `.btn-secondary` carry the platform's brand colour**, not the
      reseller's. Replaced with `.t-btn` and the template's own fills.
    - **The trust row uses green, blue and purple badges.** Three unrelated hues in a design
      built from one. They become the template's pale disc.
--}}

@php
    // Cards get narrower as plans are added, so the price steps down with them
    // and the grid widens — 4 plans must not push "/yearly" out of the card.
    $planCount = $plans->count();
    $gridClasses = match (true) {
        $planCount >= 4 => 'sm:grid-cols-2 xl:grid-cols-4 max-w-7xl',
        $planCount === 3 => 'sm:grid-cols-2 lg:grid-cols-3 max-w-6xl',
        $planCount === 2 => 'sm:grid-cols-2 max-w-4xl',
        default => 'max-w-md',
    };
    $priceClasses = $planCount >= 4 ? 'text-[28px] sm:text-[32px]' : 'text-[34px] sm:text-[40px]';
@endphp

@section('page')

    @include('sections.page-banner', [
        'title' => 'Choose Your Memorial Plan',
        'eyebrow' => 'Pricing',
        'sub' => 'Start free, upgrade when you need more. Every plan includes a beautiful memorial page.',
    ])

    {{-- Plans --}}
    <section class="bg-[var(--ap-paper)] py-[var(--t-pad-md)]">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto mt-2 grid gap-6 {{ $gridClasses }}">
                @foreach ($plans as $plan)
                    @php
                        $isFree = $plan->isFree();
                        $isPopular = (bool) $plan->is_popular;
                    @endphp

                    <div @class([
                        'relative flex flex-col rounded-[var(--t-radius)] bg-white p-7 sm:p-8',
                        'border-2 border-[var(--ap-blue)]' => $isPopular,
                        'border border-[var(--ap-line)]' => ! $isPopular,
                    ])>
                        @if ($isPopular)
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-[var(--t-btn-radius)] bg-[var(--ap-blue)] px-4 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-white">Most Popular</div>
                        @endif

                        <div class="mb-6">
                            <h3 class="t-heading text-[21px] text-[var(--ap-ink)]">{{ $plan->name }}</h3>
                            <p class="t-body mt-2 text-[13.5px] text-[var(--ap-ink-soft)]">{{ $plan->description }}</p>
                        </div>

                        <div class="mb-6">
                            <div class="flex flex-wrap items-baseline gap-x-1.5">
                                @if ($isFree)
                                    <span class="t-heading {{ $priceClasses }} text-[var(--ap-ink)]">Free</span>
                                @else
                                    <span class="text-[13px] font-medium text-[var(--ap-ink-soft)]">{{ $currency }}</span>
                                    <span class="t-heading {{ $priceClasses }} tabular-nums leading-tight text-[var(--ap-ink)]">{{ \App\Helpers\PriceHelper::format($plan->price) }}</span>
                                    <span class="whitespace-nowrap text-[13px] text-[var(--ap-ink-soft)]">/{{ $plan->interval }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="ap-pricing">
                            @include('partials.pricing.plan-bullets', ['plan' => $plan])
                        </div>

                        <a href="{{ route('memorial.create.step1') }}" @class([
                            't-btn mt-auto w-full',
                            'bg-[var(--ap-blue)] text-white hover:brightness-110' => $isPopular,
                            'border border-[var(--ap-blue)]/35 text-[var(--ap-blue)] hover:border-[var(--ap-blue)]' => ! $isPopular,
                        ])>{{ $isFree ? 'Get Started Free' : 'Select Plan' }}</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Comparison --}}
    <section class="bg-[var(--ap-mist)] py-[var(--t-pad-md)]">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <h2 class="t-heading t-h2 text-center text-[var(--ap-ink)]">Compare Plans</h2>
            <span class="ap-rule ap-rule-center mt-4" aria-hidden="true"></span>

            <div class="mt-10 overflow-x-auto rounded-[var(--t-radius)] border border-[var(--ap-line)] bg-white">
                <table class="w-full min-w-[600px]">
                    <thead>
                        <tr class="border-b border-[var(--ap-line)] bg-[var(--ap-sky)]">
                            <th class="px-6 py-4 text-left text-[13px] font-bold text-[var(--ap-ink)]">Feature</th>
                            @foreach ($plans as $plan)
                                <th class="px-6 py-4 text-center text-[13px] font-bold text-[var(--ap-ink)]">{{ $plan->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="ap-pricing divide-y divide-[var(--ap-line)] text-[var(--ap-ink-soft)]">
                        @include('partials.pricing.comparison-rows', ['plans' => $plans])
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Trust --}}
    <section class="bg-[var(--ap-paper)] py-[var(--t-pad-md)]">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-3">
                @foreach ([
                    ['shield-check', 'Secure & Encrypted', 'SSL encryption protects all your data and memories.'],
                    ['circle-check-big', 'Cancel Anytime', 'No lock-in contracts. Downgrade or cancel whenever you need.'],
                    ['heart-handshake', 'Dedicated Support', 'Our team is here to help you every step of the way.'],
                ] as [$icon, $title, $text])
                    <div class="flex flex-col items-center px-6 text-center">
                        <span class="ap-badge"><x-icon :name="$icon" class="h-9 w-9" stroke="1.4" /></span>
                        <h3 class="ap-col-title mt-5 text-[16px] text-[var(--ap-ink)]">{{ $title }}</h3>
                        <p class="t-body mt-2 max-w-[14rem] text-[13.5px] text-[var(--ap-ink-soft)]">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

@endsection
