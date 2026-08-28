@extends('layouts.visitor')

{{--
    Pricing, in this template's language: square corners, a gold band on the recommended plan
    instead of a floating pill, and the plan name in small caps.

    The comparison table and the trust strip the base template carries are deliberately not
    here. On a funeral home's site a feature matrix reads as a software purchase, which is the
    wrong register for someone arranging a burial — the plans and what each includes is the
    whole of the decision. Both partials still exist and any reseller who wants them can add
    the pricing widget to a built page.
--}}

@php
    $planCount = $plans->count();
    $gridClasses = match (true) {
        $planCount >= 4 => 'sm:grid-cols-2 xl:grid-cols-4 max-w-6xl',
        $planCount === 3 => 'sm:grid-cols-2 lg:grid-cols-3 max-w-5xl',
        $planCount === 2 => 'sm:grid-cols-2 max-w-3xl',
        default => 'max-w-sm',
    };
@endphp

@section('page')
    @include('sections.page-banner', [
        'title' => 'Our Plans',
        'eyebrow' => 'Pricing',
        'sub' => 'Clear costs, explained before you commit. Every plan includes a memorial page that stays online.',
    ])

    <section class="bg-[var(--dg-paper)] py-12 sm:py-14">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            @if ($plans->isEmpty())
                <div class="mx-auto max-w-lg border border-[#e4e4e4] bg-white p-8 text-center">
                    <p class="text-[15px] text-[#6a6a6a]">Our plans are being prepared. Please get in touch and we will talk you through the options.</p>
                </div>
            @else
                <div class="mx-auto grid gap-5 {{ $gridClasses }}">
                    @foreach ($plans as $plan)
                        @php
                            $isFree = $plan->isFree();
                            $isPopular = (bool) $plan->is_popular;
                        @endphp

                        <div @class([
                            'relative flex flex-col border bg-white p-6 sm:p-7',
                            'border-[var(--dg-gold)]' => $isPopular,
                            'border-[#e4e4e4]' => ! $isPopular,
                        ])>
                            {{-- A full-width band rather than a pill: the pill floats above the
                                 card and this template has no rounded shapes for it to belong to. --}}
                            @if ($isPopular)
                                <div class="absolute inset-x-0 top-0 bg-[var(--dg-gold)] py-1 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--dg-ink)]">
                                    Most chosen
                                </div>
                            @endif

                            <div class="{{ $isPopular ? 'mt-5' : '' }}">
                                <h3 class="dg-caps text-[20px] leading-tight text-[var(--dg-ink)]">{{ $plan->name }}</h3>
                                @if (filled($plan->description))
                                    <p class="dg-body mt-1.5 text-[13px] text-[#6a6a6a]">{{ $plan->description }}</p>
                                @endif
                            </div>

                            <div class="mt-5 flex flex-wrap items-baseline gap-x-1.5 border-t border-[#eee] pt-5">
                                @if ($isFree)
                                    <span class="dg-caps text-[30px] leading-none text-[var(--dg-ink)]">Free</span>
                                @else
                                    <span class="text-[12px] font-medium uppercase tracking-wider text-[#8a8a8a]">{{ $currency }}</span>
                                    <span class="text-[30px] font-semibold leading-none tabular-nums text-[var(--dg-ink)]">{{ \App\Helpers\PriceHelper::format($plan->price) }}</span>
                                    <span class="whitespace-nowrap text-[13px] text-[#8a8a8a]">/{{ $plan->interval }}</span>
                                @endif
                            </div>

                            <div class="dg-body mt-5 flex-1 text-[14px] text-[#5c5c5c]">
                                @include('partials.pricing.plan-bullets', ['plan' => $plan])
                            </div>

                            <a href="{{ route('memorial.create.step1') }}"
                                @class([
                                    'mt-6 inline-flex items-center justify-center px-6 py-3 text-[11px] font-bold uppercase tracking-[0.16em] transition-[background-color,border-color,transform] duration-200 ease-out active:scale-[0.98]',
                                    'bg-[var(--dg-gold)] text-[var(--dg-ink)] hover:brightness-95' => $isPopular,
                                    'border border-[#c9c9c9] text-[var(--dg-ink)] hover:border-[var(--dg-ink)]' => ! $isPopular,
                                ])>
                                {{ $isFree ? 'Start free' : 'Choose this plan' }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- The question people actually have at this point is not "which tier", it is
                 "can I speak to someone". --}}
            @php $contactUrl = \App\Support\StandardPages::urlForRouteName('contact'); @endphp
            @if ($contactUrl)
                <p class="mt-10 text-center text-[14px] text-[#6a6a6a]">
                    Not sure which is right?
                    <a href="{{ $contactUrl }}" class="font-medium text-[var(--dg-red)] underline underline-offset-2">Talk to us</a>
                    and we will help you choose.
                </p>
            @endif
        </div>
    </section>
@endsection
