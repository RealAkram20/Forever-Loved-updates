@extends('layouts.app')

{{--
    Which design a reseller's public site wears.

    Separate from Appearance on purpose: this is the coarse choice ("what does our site look
    like"), that one is the refinement ("what colour is our button"). Folding a gallery into a
    thirty-colour form would bury the control most resellers actually came for.
--}}

@section('content')
    <x-common.page-breadcrumb pageTitle="Theme" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3">
            <ul class="list-inside list-disc text-sm text-red-600 dark:text-red-400">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $activeId = $reseller?->theme_id ?? $active?->id;
    @endphp

    <div class="space-y-6">
        <x-common.component-card
            title="Your site's design"
            desc="A theme decides the layout of your public site — your home page, your About, Pricing and Contact pages, and the header and footer around them.">

            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Currently using</p>
                    <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $active?->name ?? 'Basic' }}</p>
                </div>

                @if ($reseller)
                    <a href="{{ $reseller->publicBaseUrl() }}" target="_blank" rel="noopener"
                        class="btn btn-secondary btn-sm ml-auto">
                        View your site
                    </a>
                @endif
            </div>

            {{-- The one confusing state of the layering, named before it is discovered.
                 A theme sets a palette; anything the reseller set by hand still wins, which is
                 what stops applying a theme from throwing away an afternoon's work — and also
                 what makes a freshly applied theme look half-applied if nobody says so. --}}
            @if ($shadowedCount > 0)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/[0.08]">
                    <p class="text-sm text-amber-800 dark:text-amber-200">
                        <span class="font-medium">{{ $shadowedCount }}</span>
                        {{ \Illuminate\Support\Str::plural('colour', $shadowedCount) }} you set yourself
                        {{ $shadowedCount === 1 ? 'is' : 'are' }} sitting on top of this theme, so
                        {{ $shadowedCount === 1 ? 'it stays' : 'they stay' }} as you chose. Clear
                        {{ $shadowedCount === 1 ? 'it' : 'them' }} on the Appearance page to see the theme's own palette.
                    </p>
                    <a href="{{ route('reseller.appearance') }}" class="btn btn-secondary btn-sm">Open Appearance</a>
                </div>
            @endif

            {{-- The same confusing state, for pages rather than colours.

                 Applying a theme never overwrites a page somebody built. That is right, and on
                 its own it is indistinguishable from the theme being broken: the reseller gets
                 the new palette on their old front page and no explanation. Saying it here,
                 with the swap attached, is what turns "this theme didn't work" into a choice.

                 One button per page rather than a single "reset everything", because the
                 answer is genuinely different per page — a home page built in an afternoon is
                 worth keeping, a placeholder About is not. --}}
            @if (! empty($keptPages))
                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-500/[0.08]">
                    <p class="text-sm text-blue-900 dark:text-blue-200">
                        This theme ships {{ count($keptPages) === 1 ? 'a designed' : 'designed' }}
                        {{ \Illuminate\Support\Str::plural('page', count($keptPages)) }} you already have
                        {{ count($keptPages) === 1 ? 'a version' : 'versions' }} of, so
                        {{ count($keptPages) === 1 ? 'yours was' : 'yours were' }} kept and nothing you built was lost.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach ($keptPages as $kept)
                            <form method="POST" action="{{ route('reseller.theme.reset-page') }}"
                                onsubmit="return confirm('Replace your {{ $kept['title'] }} page with this theme\'s design? What you have built on that page will be replaced.');">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $kept['slug'] }}">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    Use the theme's {{ $kept['title'] }}
                                </button>
                            </form>
                        @endforeach
                    </div>

                    <p class="mt-2 text-xs text-blue-800/80 dark:text-blue-300/80">
                        This replaces that page. Your other pages are untouched.
                    </p>
                </div>
            @endif

            @if ($active?->templateIsMissing())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/[0.08] dark:text-red-300">
                    This theme's template isn't deployed on this server, so your site is rendering in the
                    Basic design until it is. Nothing is lost — pick another theme, or ask your platform
                    admin about <span class="font-mono">{{ $active->template }}</span>.
                </div>
            @endif
        </x-common.component-card>

        <x-common.component-card title="Choose a theme" desc="Applying a theme changes your public site immediately. Your logo, your colours and everything you've written stay exactly as they are.">
            @if ($themes->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    No themes are available yet. Ask your platform admin to run the theme sync.
                </p>
            @else
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($themes as $theme)
                        @php
                            $manifest = $registry->manifest($theme->templateSlug());
                            $isActive = $theme->id === $activeId;
                            // Gated themes stay in the gallery and stay previewable. Hiding one
                            // would mean asking somebody to upgrade for something they have
                            // never seen; showing it locked is the only version of this that
                            // argues for itself.
                            $locked = ! $theme->isAvailableTo($reseller);
                        @endphp

                        <div @class([
                            'flex flex-col rounded-2xl border p-4 transition-colors duration-200 ease-out',
                            'border-brand-500 bg-brand-50/40 dark:border-brand-400 dark:bg-brand-500/[0.08]' => $isActive,
                            'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700' => ! $isActive,
                        ])>
                            @include('partials.theme-preview', ['manifest' => $manifest])

                            <div class="mt-4 flex items-start gap-2">
                                <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $theme->name }}</h4>

                                @if ($isActive)
                                    <span class="ml-auto shrink-0 rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-semibold text-white">Active</span>
                                @elseif (! $theme->isPlatform())
                                    <span class="ml-auto shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">Yours</span>
                                @endif
                            </div>

                            <p class="mt-1.5 line-clamp-3 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                {{ $manifest?->description ?: 'A saved look of your own, built on the '.($manifest?->name ?? $theme->template).' layout.' }}
                            </p>

                            @if ($theme->templateIsMissing())
                                <p class="mt-2 text-xs font-medium text-red-600 dark:text-red-400">Template not deployed</p>
                            @endif

                            <div class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                                @if ($isActive)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">In use on your site</span>
                                @elseif ($locked)
                                    {{-- Says what plan it needs, not "unavailable". The second
                                         reads as a bug to somebody looking straight at the card. --}}
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        On {{ $theme->minimumTier?->name }} and above
                                    </span>
                                @else
                                    <form action="{{ route('reseller.theme.apply') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="theme_id" value="{{ $theme->id }}">
                                        <button type="submit" class="btn btn-primary btn-sm">Use this theme</button>
                                    </form>
                                @endif

                                {{-- Outside the branch above, so a locked theme keeps it. Preview
                                     is how somebody decides an upgrade is worth paying for, and
                                     it cannot change their site whatever their plan.

                                     Deliberately the quieter of the two, and deliberately next to
                                     it: the whole point is that one of these changes their live
                                     site and the other does not, and that difference has to be
                                     legible at a glance rather than explained. --}}
                                @unless ($isActive)
                                    <form action="{{ route('reseller.theme.preview') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="theme_id" value="{{ $theme->id }}">
                                        <button type="submit" class="btn btn-secondary btn-sm">Preview</button>
                                    </form>
                                @endunless

                                @if (! $theme->isPlatform() && ! $isActive)
                                    <form action="{{ route('reseller.theme.destroy', $theme) }}" method="POST" class="ml-auto">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="text-xs font-medium text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                            onclick="return confirm('Delete “{{ $theme->name }}”? Your site is not using it, so nothing on it changes.')">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-common.component-card>

        {{-- Saving is deliberately inert: it copies the look currently in force, and changes
             nothing about the live site. A save button that can alter what visitors see is a
             save button nobody presses twice. --}}
        <x-common.component-card
            title="Save this look"
            desc="Keep your current layout and colours together under a name, so you can come back to them after trying something else.">
            <form action="{{ route('reseller.theme.save') }}" method="POST" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-0 flex-1 sm:max-w-xs">
                    <label for="theme-name" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" id="theme-name" name="name" maxlength="60" required
                        value="{{ old('name') }}" placeholder="Our 2026 look"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900/80 dark:text-white/90" />
                </div>
                <button type="submit" class="btn btn-secondary btn-md">Save as my theme</button>
            </form>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Saving doesn't change your site. It records the layout you're on and the colours you've
                set, so applying it later brings both back at once.
            </p>
        </x-common.component-card>
    </div>
@endsection
