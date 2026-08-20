{{-- oneTapMode 'intent': the Google prompt waits for the first touch of something
     interactive. A mourner opening a shared link reads in peace; the moment they move to
     participate, the sign-in they would have needed is already offering itself. --}}
@extends('layouts.fullscreen-layout', ['hideFullscreenThemeToggle' => true, 'oneTapMode' => 'intent'])

@push('head')
{{--
    Quill is deliberately not loaded here. Most arrivals on this page are mourners following a
    shared link who never open an editor, and a blocking third-party script in <head> made every
    one of them wait for it. memorial-public.js fetches it on demand instead — when a tab with a
    composer opens, or an inline edit begins.
--}}
@php
    $personSchemaLd = \App\Helpers\MemorialSchemaHelper::personJsonLd($memorial);
@endphp
@if (!empty($personSchemaLd))
<script type="application/ld+json">{!! json_encode($personSchemaLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
@endpush

@section('content')
@php
    // Resolved once for the whole page: the profile card's media count and the gallery tab
    // used to each run galleryMedia() separately, paying for the same query twice per view.
    //
    // The gallery is now both things people gave this memorial: what the family uploaded to
    // it, and what visitors attached to their stories. The second used to be reachable only
    // by scrolling the story feed, so a picture someone drove across the country to share
    // was invisible to anyone browsing photos.
    $uploadedMedia = $memorial->galleryMedia()->orderBy('sort_order')->get();
    $storyMedia = $memorial->storyMedia()->orderBy('sort_order')->get();
    $galleryItems = $uploadedMedia->concat($storyMedia);
    $galleryImages = $galleryItems->where('type', 'photo')->values();
    $galleryVideos = $galleryItems->where('type', 'video')->values();

    $galleryCategories = $memorial->galleryCategories;
    $storyMediaIds = array_flip($storyMedia->pluck('id')->all());

    // Which chips an item answers to. An array rather than a single key because a story's
    // photo the family has also filed under "Childhood" genuinely belongs in both places,
    // and picking one would hide it from whoever went looking in the other.
    $galleryKeysFor = function ($media) use ($storyMediaIds) {
        $keys = [];
        if (isset($storyMediaIds[$media->id])) {
            $keys[] = 'stories';
        }
        if ($media->gallery_category_id) {
            $keys[] = (string) $media->gallery_category_id;
        }

        return $keys ?: ['uncategorised'];
    };

    $galleryCatMap = [];
    $galleryCounts = [];
    foreach ($galleryItems as $media) {
        $keys = $galleryKeysFor($media);
        $galleryCatMap[(string) $media->id] = $keys;
        foreach ($keys as $key) {
            $galleryCounts[$key] = ($galleryCounts[$key] ?? 0) + 1;
        }
    }

    // An empty category is a shelf the family has not filled yet: useful to them, noise to
    // a visitor. Curators see all of theirs so they have somewhere to file into.
    $visibleGalleryChips = $galleryCategories
        ->filter(fn ($category) => $canEdit || ($galleryCounts[(string) $category->id] ?? 0) > 0)
        ->values();
    $showStoriesChip = ($galleryCounts['stories'] ?? 0) > 0;
    // "Other" only means something once there is something else to be other than.
    $showUnfiledChip = ($galleryCounts['uncategorised'] ?? 0) > 0
        && ($visibleGalleryChips->isNotEmpty() || $showStoriesChip);
    $galleryChipTotal = $visibleGalleryChips->count() + (int) $showStoriesChip + (int) $showUnfiledChip;
    // Only the upload button needs to name a category, and only a curator sees that button.
    // Sending the full list to every mourner would put the names of categories they cannot
    // even see into the page source.
    $galleryCatNames = $canEdit ? $galleryCategories->pluck('name', 'id')->all() : [];
    // Two chips that both show everything are a decoration, not a filter.
    $showGalleryFilters = $canEdit || $galleryChipTotal >= 2;

    // Uncaptioned photos used to all read as "Photo", so a screen reader announced the same
    // word twenty times over. The position at least tells them apart.
    $galleryAlt = fn ($media, $index) => $media->caption ?: 'Gallery photo '.($index + 1);
@endphp
<div class="min-h-screen bg-gradient-to-b from-gray-50 via-white/80 to-gray-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900 glass-bg-mesh" data-memorial-slug="{{ $memorial->slug }}" data-tribute-url="{{ route('memorial.api.tribute', ['slug' => $memorial->slug]) }}" data-can-edit="{{ $canEdit ? '1' : '0' }}" data-is-authenticated="{{ $isAuthenticated ? '1' : '0' }}" data-can-upload="{{ $canEdit ? '1' : '0' }}" data-scroll-chapter="{{ $scrollToChapterId ?? '' }}" data-deceased-first="{{ \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'them') }}" data-user-initial="{{ strtoupper(substr(auth()->user()?->name ?? 'G', 0, 1)) }}" data-user-name="{{ auth()->user()?->name }}">
    <x-home-header />

    {{-- Owner-only: unfinished paid-plan checkout --}}
    @if (! empty($pendingPaymentOrder))
        <div class="border-b border-amber-200 bg-amber-50 dark:border-amber-800/50 dark:bg-amber-900/20">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <p class="text-sm text-amber-800 dark:text-amber-300">
                    <strong>Payment pending.</strong> Complete your payment to unlock all plan features for this memorial.
                </p>
                <a href="{{ route('subscription.index') }}" class="btn btn-primary btn-sm shrink-0">Complete payment</a>
            </div>
        </div>
    @endif

    {{-- Global upload progress (large media / slow connections); driven by memorial-public.js --}}
    <div id="memorial-upload-progress" class="fixed left-0 right-0 top-16 lg:top-[4.5rem] z-[45] hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-hidden="true" aria-labelledby="memorial-upload-progress-label">
        <div class="mx-auto max-w-7xl px-4 pt-2 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur-md dark:border-gray-700 dark:bg-gray-900/95">
                <div class="flex items-center justify-between gap-3">
                    <p id="memorial-upload-progress-label" class="text-sm font-semibold text-gray-900 dark:text-white/90">Uploading…</p>
                    <span id="memorial-upload-progress-pct" class="tabular-nums text-sm font-medium text-brand-600 dark:text-brand-400">0%</span>
                </div>
                <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                    <div id="memorial-upload-progress-bar" class="h-full rounded-full bg-brand-500 transition-[width] duration-200 ease-out" style="width: 0%"></div>
                </div>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Keep this page open until the upload finishes. On slow connections this may take several minutes for large videos.</p>
            </div>
        </div>
    </div>

    @if ($canEdit)
        {{-- Owner edit affordance: hover-only controls are invisible on touch; banner + mobile-visible pencils fix that --}}
        <div class="sticky top-14 z-30 border-b border-amber-300/80 bg-amber-50 px-4 py-2.5 shadow-sm dark:border-amber-500/45 dark:bg-amber-950/95 sm:px-6" role="status" aria-live="polite">
            <div class="mx-auto flex max-w-7xl items-start gap-2.5">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-200/90 text-amber-900 dark:bg-amber-500/25 dark:text-amber-200">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-950 dark:text-amber-50">You’re editing this memorial</p>
                    <p class="mt-0.5 text-xs leading-snug text-amber-900/85 dark:text-amber-100/85">Tap any <span class="font-medium">pencil</span> button or a <span class="font-medium">dashed outline</span> to update content. This strip is only shown to you.</p>

                    {{-- The photo and the cover can only be set from this page — the memorial
                         edit form has neither — and both controls live in the profile card,
                         which is not rendered below `md`. Without these two, hiding that card
                         would quietly take away the only way to change either from a phone.
                         Shown only where that card is absent. --}}
                    <div class="mt-2.5 flex flex-wrap items-center gap-1.5 md:hidden">
                        <label class="memorial-cover-btn inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-amber-300 bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-amber-900 dark:border-amber-500/50 dark:bg-amber-900/40 dark:text-amber-100">
                            <input type="file" data-profile-photo-input accept="image/*" class="hidden" />
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Photo
                        </label>
                        @include('pages.memorials.partials.cover-controls', ['variant' => 'inline'])
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Guest modal: name + email for tributes/reactions --}}
    <div id="guest-modal" role="dialog" aria-modal="true" aria-labelledby="guest-modal-title" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-xl">
            <h3 id="guest-modal-title" class="text-lg font-semibold text-gray-900 dark:text-white/90">Enter your details</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Please provide your name and email to continue.</p>
            <form id="guest-form" class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your name</label>
                    <input type="text" id="guest-name" required autocomplete="name"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="Your name" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your email</label>
                    <input type="email" id="guest-email" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm" placeholder="your@email.com" />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-md">Continue</button>
                    <button type="button" data-close-guest-modal class="btn btn-secondary btn-md">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Memorial hero: an art-directed remembrance scene — deep indigo night, one candle,
         lilies in the corner, the portrait dissolving into the dark like a memory.

         Always dark, whatever the site theme: the scene is the design. The cover photo no
         longer backs the hero (it still dresses the profile card); the artwork here is the
         same for every memorial, which is what makes each one feel dressed rather than
         wallpapered with whatever photo happened to be widest. --}}
    @php
        $birthYear = $memorial->date_of_birth?->format('Y') ?: ($memorial->birth_year ?: null);
        $deathYear = $memorial->date_of_passing?->format('Y') ?: ($memorial->death_year ?: null);
        $heroBio = \Illuminate\Support\Str::limit(trim(strip_tags($memorial->biography ?? '')), 150);
    @endphp
    <section id="memorial-hero" class="memorial-hero">
        @php
            // Stamped with the file's mtime for the same reason the tribute artwork is:
            // swapping the asset under a fixed URL leaves returning visitors on the old
            // picture until their cache expires.
            $heroBackdropPath = public_path('images/memorial/hero-backdrop.webp');
            $heroBackdropVersion = is_file($heroBackdropPath) ? filemtime($heroBackdropPath) : null;
        @endphp
        <img class="memorial-hero__bg" src="{{ asset('images/memorial/hero-backdrop.webp') }}{{ $heroBackdropVersion ? '?v='.$heroBackdropVersion : '' }}" alt="" aria-hidden="true" />
        <div class="memorial-hero__vignette" aria-hidden="true"></div>

        <div class="memorial-hero__frame">
            {{-- Always in the markup, hidden until there is a photo, so uploading one from
                 the profile card reveals it without a reload (the JS toggles `hidden`). --}}
            <div id="memorial-hero-portrait" class="memorial-hero__portrait {{ $memorial->profile_photo_path ? '' : 'hidden' }}">
                <img id="memorial-hero-portrait-image"
                    @if ($memorial->profile_photo_path) {!! \App\Helpers\ResponsiveImage::attrs($memorial->profile_photo_path, '(min-width: 1024px) 26rem, 66vw') !!} fetchpriority="high" @endif
                    alt="{{ $memorial->full_name }}" />
            </div>

            <div class="memorial-hero__content">
                <p class="memorial-hero__eyebrow">In Loving Memory</p>
                <h1 class="memorial-hero__name">{{ $memorial->full_name }}</h1>
                @if ($birthYear || $deathYear)
                    <p class="memorial-hero__years">{{ $birthYear }}@if ($birthYear && $deathYear) &ndash; @endif{{ $deathYear }}</p>
                @endif
                <div class="memorial-hero__divider" aria-hidden="true">
                    <span></span>
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                    <span></span>
                </div>
                @if ($heroBio !== '')
                    <p class="memorial-hero__bio">{{ $heroBio }}</p>
                @endif
                {{-- Opens the composer at the top of the feed — the same one everything
                     else opens, so the invitation and the act are never two places. --}}
                <button type="button" id="hero-share-memory" class="memorial-hero__cta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    Share a Memory
                </button>
            </div>

        </div>
    </section>

    {{-- Three-column layout --}}
    <main class="mx-auto max-w-7xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
        <div class="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-12">
            {{-- Column 1: Profile card (narrow).

                 Not rendered below `md`. Beside the hero it is a summary in the margin; once
                 the columns stack it becomes the same portrait, the same name and the same
                 years again, a screen after you have just read them — which reads as the page
                 repeating itself rather than as a second view of it.

                 What it holds that the hero does not — the full dates, the tallies, the
                 status badges — is either on the page already or a scroll away in the feed.
                 The two controls it *exclusively* owns, the profile photo and the cover, move
                 into the owner's editing strip at small sizes; see the `$canEdit` banner near
                 the top of this file. Nothing is lost, it is only somewhere better. --}}
            <aside class="hidden md:col-span-4 md:block lg:col-span-3">
                <div class="md:sticky md:top-16 lg:top-[4.5rem] space-y-4">
                    <div class="memorial-card-enter overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 glass-card dark:bg-white/[0.03] shadow-theme-sm">
                        {{-- Cover banner; the profile photo below overlaps its lower edge --}}
                        <div id="memorial-cover" class="relative h-28 sm:h-32">
                            {{-- src is omitted entirely when there is no cover: an empty src
                                 makes the browser re-request the current page. --}}
                            <img id="memorial-cover-image"
                                @if ($memorial->cover_photo_path) {!! \App\Helpers\ResponsiveImage::attrs($memorial->cover_photo_path, '(min-width: 1024px) 25vw, (min-width: 768px) 33vw, 100vw') !!} @endif
                                alt="Cover photo for {{ $memorial->full_name }}"
                                class="h-full w-full object-cover {{ $memorial->cover_photo_path ? '' : 'hidden' }}" />
                            {{-- Fallback: a quiet wash rather than an empty grey slab, so a memorial
                                 without a cover still reads as finished rather than broken. --}}
                            <div id="memorial-cover-fallback"
                                class="h-full w-full bg-gradient-to-br from-brand-100 via-brand-50 to-gray-100 dark:from-brand-500/25 dark:via-brand-500/10 dark:to-gray-800 {{ $memorial->cover_photo_path ? 'hidden' : '' }}"
                                aria-hidden="true"></div>
                            {{-- Keeps the age bubble and any light cover from washing out against the card --}}
                            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/15 to-transparent dark:from-black/35" aria-hidden="true"></div>

                            @if ($canEdit)
                                @include('pages.memorials.partials.cover-controls', ['variant' => 'overlay'])
                            @endif
                        </div>

                        <div class="px-4 pb-4 sm:px-6 sm:pb-6">
                            <div class="flex flex-col items-center text-center">
                                {{-- Profile photo with upload + age bubble --}}
                                @php
                                    $ageLabel = null;
                                    if ($memorial->date_of_birth && $memorial->date_of_passing) {
                                        $days   = (int) abs($memorial->date_of_birth->diffInDays($memorial->date_of_passing));
                                        $months = (int) abs($memorial->date_of_birth->diffInMonths($memorial->date_of_passing));
                                        $years  = (int) abs($memorial->date_of_birth->diffInYears($memorial->date_of_passing));

                                        if ($years >= 1) {
                                            $ageLabel = $years . 'yr' . ($years !== 1 ? 's' : '');
                                        } elseif ($months >= 1) {
                                            $ageLabel = $months . 'mth' . ($months !== 1 ? 's' : '');
                                        } else {
                                            $ageLabel = $days . 'day' . ($days !== 1 ? 's' : '');
                                        }
                                    }
                                @endphp
                                {{-- -mt-12 lifts the avatar over the banner's lower edge; the ring
                                     matches the card surface so it reads as cut out of it. --}}
                                <div class="relative group -mt-12 mb-4">
                                    <div id="memorial-profile-photo" class="h-24 w-24 overflow-hidden rounded-full bg-gray-200 ring-4 ring-white dark:bg-gray-700 dark:ring-gray-900">
                                        @if ($memorial->profile_photo_path)
                                            <img {!! \App\Helpers\ResponsiveImage::attrs($memorial->profile_photo_path, '6rem') !!} alt="{{ $memorial->full_name }}" class="h-full w-full object-cover" />
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-3xl text-gray-400 dark:text-gray-500">?</div>
                                        @endif
                                    </div>
                                    @if ($ageLabel)
                                        <span class="absolute -right-2 top-0 z-10 rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-bold text-white shadow-md shadow-brand-500/30 ring-2 ring-white dark:ring-gray-900">{{ $ageLabel }}</span>
                                    @endif
                                    @if ($canEdit)
                                        <label class="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/55">
                                            <input type="file" data-profile-photo-input accept="image/*" class="hidden" />
                                            <span class="rounded-md bg-white/95 px-2 py-0.5 text-[11px] font-semibold text-gray-900 shadow-sm dark:bg-gray-900/95 dark:text-white">Photo</span>
                                        </label>
                                    @endif
                                </div>
                                <div data-editable="full_name" class="relative group w-full @if($canEdit) rounded-lg border border-dashed border-brand-400/55 bg-brand-50/40 px-2 py-2 dark:border-brand-400/40 dark:bg-brand-500/[0.08] @endif">
                                    @if ($canEdit)
                                        <div class="flex items-start justify-center gap-2">
                                            <h2 data-display class="min-w-0 flex-1 text-center text-lg font-semibold text-gray-900 dark:text-white/90">{{ $memorial->full_name ?: 'Full name' }}</h2>
                                            <button type="button" data-edit-trigger class="memorial-edit-fab shrink-0 rounded-lg border border-brand-300/90 bg-white p-1.5 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit name">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                        </div>
                                        <div data-edit class="hidden mt-1">
                                            <input type="text" value="{{ $memorial->full_name }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white" />
                                            <button type="button" data-save class="btn btn-primary btn-sm mt-2">Save</button>
                                        </div>
                                    @else
                                        <h2 data-display class="text-lg font-semibold text-gray-900 dark:text-white/90">{{ $memorial->full_name ?: 'Full name' }}</h2>
                                    @endif
                                </div>
                                <div class="mt-2 flex flex-wrap items-center justify-center gap-1.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 dark:bg-success-500/20 px-3 py-1 text-xs font-medium text-success-700 dark:text-success-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-success-500"></span>
                                        In Loving Memory
                                    </span>
                                    @if ($memorial->is_public)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-500/15 px-2.5 py-0.5 text-[11px] font-medium text-blue-600 dark:text-blue-400">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Public
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Private
                                        </span>
                                    @endif
                                    @php
                                        $qualityPercent = $memorial->completion_percentage;
                                        $qualityColor = $qualityPercent >= 75 ? 'green' : ($qualityPercent >= 40 ? 'amber' : 'red');
                                        $qualityColors = [
                                            'green' => ['bg' => 'bg-green-50 dark:bg-green-500/15', 'text' => 'text-green-600 dark:text-green-400', 'dot' => 'bg-green-500'],
                                            'amber' => ['bg' => 'bg-amber-50 dark:bg-amber-500/15', 'text' => 'text-amber-600 dark:text-amber-400', 'dot' => 'bg-amber-500'],
                                            'red' => ['bg' => 'bg-red-50 dark:bg-red-500/15', 'text' => 'text-red-600 dark:text-red-400', 'dot' => 'bg-red-500'],
                                        ];
                                        $qc = $qualityColors[$qualityColor];
                                    @endphp
                                    @if ($canEdit)
                                        <span class="inline-flex items-center gap-1 rounded-full {{ $qc['bg'] }} px-2.5 py-0.5 text-[11px] font-medium {{ $qc['text'] }}" title="Profile completeness: {{ $qualityPercent }}%">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $qc['dot'] }}"></span>
                                            {{ $qualityPercent }}% Complete
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-4 flex gap-6">
                                    <div class="text-center">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90" data-story-count>{{ $stories->count() }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ Str::plural('Story', $stories->count()) }}</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-lg font-semibold text-gray-900 dark:text-white/90">{{ $galleryItems->count() }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Photos</p>
                                    </div>
                                </div>
                            </div>
                            @if ($memorial->date_of_birth || $memorial->date_of_passing || $canEdit)
                                <div data-editable="dates" class="mt-4 pt-4 text-center @if($canEdit) rounded-lg border border-dashed border-brand-400/50 bg-brand-50/30 px-2 pb-3 dark:border-brand-400/35 dark:bg-brand-500/[0.06] @else border-t border-gray-100 dark:border-gray-800 @endif">
                                    <p data-display class="text-sm text-gray-600 dark:text-gray-400">
                                        @if ($memorial->date_of_birth){{ $memorial->date_of_birth->format('M d, Y') }}@endif
                                        @if ($memorial->date_of_birth && $memorial->date_of_passing) &ndash; @endif
                                        @if ($memorial->date_of_passing){{ $memorial->date_of_passing->format('M d, Y') }}@endif
                                        @if (!$memorial->date_of_birth && !$memorial->date_of_passing && $canEdit) Add dates @endif
                                    </p>
                                    @if ($ageLabel)
                                        <p class="mt-1 text-xs font-medium text-brand-600 dark:text-brand-400">Died at {{ $ageLabel }}</p>
                                    @endif
                                    @if ($canEdit)
                                        <div data-edit class="hidden mt-2 space-y-2 text-left sm:text-center">
                                            <input type="date" data-date-type="birth" value="{{ $memorial->date_of_birth?->format('Y-m-d') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:w-auto" />
                                            <input type="date" data-date-type="death" value="{{ $memorial->date_of_passing?->format('Y-m-d') }}" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-white sm:ml-1 sm:w-auto" />
                                            <button type="button" data-save class="btn btn-primary btn-sm mt-1">Save</button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </aside>

            {{-- Column 2: Tabbed content (Life, Biography, Gallery, Tributes) --}}
            <section class="md:col-span-8 lg:col-span-6">
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 glass-card dark:bg-white/[0.03] shadow-theme-sm">
                    {{-- Tab buttons: scroll horizontally on narrow screens instead of squeezing --}}
                    <div role="tablist" aria-label="Memorial sections" class="flex overflow-x-auto border-b border-gray-100 dark:border-gray-800">
                        <button type="button" role="tab" id="tab-btn-biography" aria-controls="tab-biography" aria-selected="true" tabindex="0" data-tab-panel="biography" class="memorial-tab-btn min-w-fit flex-1 whitespace-nowrap px-4 py-3 text-sm font-medium text-brand-600 dark:text-brand-400 border-b-2 border-brand-500 bg-brand-50/50 dark:bg-brand-500/10">Bio</button>
                        <button type="button" role="tab" id="tab-btn-gallery" aria-controls="tab-gallery" aria-selected="false" tabindex="-1" data-tab-panel="gallery" class="memorial-tab-btn min-w-fit flex-1 whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Gallery</button>
                        <button type="button" role="tab" id="tab-btn-stories" aria-controls="tab-stories" aria-selected="false" tabindex="-1" data-tab-panel="stories" class="memorial-tab-btn min-w-fit flex-1 whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 border-b-2 border-transparent">Stories/Tributes</button>
                    </div>

                    @php
                        // One preview under the biography, not two. The Bio tab used to end
                        // with a Life Stories block and a Tributes block, which was the same
                        // split the tab below had — and it read as two half-empty walls
                        // rather than one wall.
                        $storiesForPreview = $stories->take(3);
                        $storiesTotal = $stories->count();
                    @endphp

                    {{-- Tab: Biography (first) --}}
                    <div id="tab-biography" role="tabpanel" aria-labelledby="tab-btn-biography" tabindex="0" class="memorial-tab-panel p-4 sm:p-6">
                        <div data-editable="biography" class="relative group rounded-xl @if($canEdit) border border-dashed border-brand-400/55 bg-brand-50/35 p-3 dark:border-brand-400/40 dark:bg-brand-500/[0.07] @endif">
                            @if ($canEdit)
                                <button type="button" data-edit-trigger class="memorial-edit-fab absolute right-0 top-0 z-10 rounded-lg border border-brand-300/90 bg-white p-2 text-brand-700 shadow-sm dark:border-brand-500/50 dark:bg-gray-900/95 dark:text-brand-300" title="Edit biography">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                </button>
                            @endif
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90 @if($canEdit) pr-12 @endif">Biography</h2>
                            {{-- Visitors get a folded biography: a long life story shouldn't
                                 push the gallery and stories below three screens of scroll.
                                 The clamp is visitor-only — an editor sees the whole text,
                                 because the inline editor reads this element's markup and
                                 clamping it would make editing feel like the text was lost.
                                 The button appears only when the text actually overflows. --}}
                            <div data-display @if(!$canEdit) data-bio-clamp @endif class="mt-3 text-gray-700 dark:text-gray-300 prose prose-sm dark:prose-invert max-w-none">{!! \App\Helpers\BiographyFormatter::format($memorial->biography) ?: 'Add biography...' !!}</div>
                            @if (!$canEdit)
                                <button type="button" data-bio-toggle aria-expanded="false" class="mt-2 hidden text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400">
                                    Show more
                                </button>
                            @endif
                            @if ($canEdit)
                                <div data-edit class="hidden mt-3 space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Your story</label>
                                        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Share your memories with text, photos, videos, or documents.</p>
                                        <div id="biography-editor" class="min-h-[200px] rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900"></div>
                                        <input type="hidden" id="biography-content" />
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" data-save class="btn btn-primary btn-md" data-save-text="Save" data-saving-text="Saving...">Save</button>
                                        <a href="{{ route('memorials.edit', $memorial) }}#biography" class="inline-flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-brand-600 dark:hover:text-brand-400">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Edit in full memorial
                                        </a>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($storiesTotal > 0)
                            <div class="mt-8 border-t border-gray-100 pt-8 dark:border-gray-800">
                                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white/90">Stories <span class="font-normal text-gray-500 dark:text-gray-400">({{ $storiesTotal }})</span></h3>
                                <div class="flex flex-col gap-4">
                                    @foreach ($storiesForPreview as $post)
                                        <div class="min-w-0">
                                            @include('pages.memorials.partials.life-post-article', [
                                                'post' => $post,
                                                'memorial' => $memorial,
                                                'canEdit' => $canEdit,
                                                'quotaInfo' => $quotaInfo,
                                                'embedInBiography' => true,
                                            ])
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-4">
                                    <button type="button" data-switch-tab="stories" class="btn btn-secondary btn-sm btn-block w-full">Read all {{ $storiesTotal }} {{ Str::plural('story', $storiesTotal) }}</button>
                                </div>
                            </div>
                        @endif

                        @if ($galleryImages->isNotEmpty())
                            @php $galleryImageCount = $galleryImages->count(); @endphp
                            <div class="mt-8 border-t border-gray-100 pt-8 dark:border-gray-800">
                                <h3 class="mb-4 text-base font-semibold text-gray-900 dark:text-white/90">Gallery <span class="font-normal text-gray-500 dark:text-gray-400">({{ $galleryImageCount }})</span></h3>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($galleryImages->take(9) as $previewIdx => $media)
                                        <button type="button" data-gallery-preview-lightbox="{{ $media->id }}" class="group relative aspect-square overflow-hidden rounded-lg bg-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:bg-gray-700">
                                            <img {!! \App\Helpers\ResponsiveImage::attrs($media->path, '(min-width: 1024px) 20vw, 33vw') !!} alt="{{ $galleryAlt($media, $previewIdx) }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" loading="lazy" decoding="async" />
                                            <span class="absolute inset-0 bg-black/0 transition group-hover:bg-black/10" aria-hidden="true"></span>
                                        </button>
                                    @endforeach
                                </div>
                                <div class="mt-4">
                                    <button type="button" data-switch-tab="gallery" class="btn btn-secondary btn-sm btn-block w-full">View all {{ $galleryImageCount }} {{ Str::plural('item', $galleryImageCount) }}</button>
                                </div>
                            </div>
                        @endif
                    </div>


                    {{-- Tab: Gallery with Images/Videos sub-tabs + lightbox --}}
                    <div id="tab-gallery" role="tabpanel" aria-labelledby="tab-btn-gallery" tabindex="0" class="memorial-tab-panel hidden p-4 sm:p-6"
                        x-data="{
                            subTab: 'images',
                            activeCat: 'all',
                            lightboxOpen: false,
                            currentIndex: 0,
                            {{-- `caption` is the real caption and may be empty; `alt` always has a
                                 usable label. Keeping them apart stops the alt-text fallback from
                                 being rendered as though the family had written it. --}}
                            {{-- `url` is the lightbox rendition (display-sized derivative, or the
                                 original while derivatives are still queued); `thumb` feeds the
                                 filmstrip so it stops paying full-photo prices for 56px tiles. --}}
                            images: {{ Js::from($galleryImages->map(fn($m, $i) => ['id' => $m->id, 'url' => \App\Helpers\ResponsiveImage::url($m->path, 1600), 'thumb' => \App\Helpers\ResponsiveImage::url($m->path, 160), 'caption' => $m->caption ?: '', 'alt' => $m->caption ?: 'Gallery photo '.($i + 1)])->toArray()) }},
                            {{-- Videos are rendered as Blade DOM, not from here. This carries their
                                 ids only, so the chip counts and the Videos tab can be filtered by
                                 the same rules as the photos without duplicating the player state. --}}
                            videos: {{ Js::from($galleryVideos->pluck('id')->map(fn($id) => ['id' => $id])->toArray()) }},
                            {{-- One map, media id → the chips it answers to. The grid cells ask it
                                 rather than carrying their own copy, so filing a photo somewhere new
                                 updates the cell, the counts and the lightbox from a single write. --}}
                            catMap: {{ Js::from($galleryCatMap) }},
                            catNames: {{ Js::from($galleryCatNames) }},
                            {{-- Uploading while a category is selected files the photo there. Said
                                 out loud on the button, because otherwise the picture you just
                                 chose lands outside the filter and looks like it failed. --}}
                            get uploadTargetName() { return this.catNames[this.activeCat] || '' },
                            playing: false,
                            speed: 3000,
                            interval: null,
                            matches(id) {
                                if (this.activeCat === 'all') return true;
                                return (this.catMap[id] || []).includes(this.activeCat);
                            },
                            get visibleImages() {
                                return this.activeCat === 'all' ? this.images : this.images.filter(i => this.matches(i.id));
                            },
                            get visibleVideoCount() {
                                return this.activeCat === 'all' ? this.videos.length : this.videos.filter(v => this.matches(v.id)).length;
                            },
                            catCount(key) {
                                if (key === 'all') return this.images.length + this.videos.length;
                                return Object.values(this.catMap).filter(keys => keys.includes(key)).length;
                            },
                            selectCat(key) {
                                if (this.activeCat === key) return;
                                this.activeCat = key;
                                {{-- The slideshow was playing through a set that no longer exists;
                                     leaving it running would advance through the wrong photos. --}}
                                this.stopSlideshow();
                                this.currentIndex = 0;
                            },
                            get currentImage() { return this.visibleImages[this.currentIndex] || {} },
                            get total() { return this.visibleImages.length },
                            lastFocused: null,
                            {{-- Addressed by media id, not grid position. Position was fragile even
                                 before filtering — every delete had to renumber every cell after it —
                                 and a filtered grid has no stable position to renumber. --}}
                            openLightbox(mediaId) {
                                const idx = this.visibleImages.findIndex(i => i.id === mediaId);
                                if (idx === -1) return;
                                this.currentIndex = idx;
                                this.lightboxOpen = true;
                                document.body.style.overflow = 'hidden';
                                // Remember the thumbnail so closing returns the visitor to
                                // their place in the grid instead of the top of the page.
                                this.lastFocused = document.activeElement;
                                this.$nextTick(() => this.$refs.lightboxClose?.focus());
                            },
                            closeLightbox() {
                                this.stopSlideshow();
                                this.lightboxOpen = false;
                                document.body.style.overflow = '';
                                if (this.lastFocused instanceof HTMLElement) this.lastFocused.focus();
                                this.lastFocused = null;
                            },
                            trapTab(event) {
                                const items = Array.from(
                                    event.currentTarget.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')
                                ).filter(el => el.offsetParent !== null);
                                if (!items.length) return;
                                const first = items[0];
                                const last = items[items.length - 1];
                                if (event.shiftKey && document.activeElement === first) {
                                    event.preventDefault();
                                    last.focus();
                                } else if (!event.shiftKey && document.activeElement === last) {
                                    event.preventDefault();
                                    first.focus();
                                }
                            },
                            next() {
                                if (this.total === 0) return;
                                this.currentIndex = (this.currentIndex + 1) % this.total;
                            },
                            prev() {
                                if (this.total === 0) return;
                                this.currentIndex = (this.currentIndex - 1 + this.total) % this.total;
                            },
                            toggleSlideshow() {
                                this.playing ? this.stopSlideshow() : this.startSlideshow();
                            },
                            startSlideshow() {
                                if (this.total <= 1) return;
                                this.playing = true;
                                this.interval = setInterval(() => this.next(), this.speed);
                            },
                            stopSlideshow() {
                                this.playing = false;
                                clearInterval(this.interval);
                                this.interval = null;
                            },
                            setSpeed(ms) {
                                this.speed = ms;
                                if (this.playing) {
                                    clearInterval(this.interval);
                                    this.interval = setInterval(() => this.next(), this.speed);
                                }
                            },
                            addImage(id, url, caption, keys) {
                                this.catMap[id] = keys;
                                this.images.push({
                                    id,
                                    url,
                                    caption: caption || '',
                                    alt: caption || ('Gallery photo ' + (this.images.length + 1)),
                                });
                            },
                            addVideo(id, keys) {
                                this.catMap[id] = keys;
                                this.videos.push({ id });
                            },
                            removeMedia(id) {
                                delete this.catMap[id];
                                this.images = this.images.filter(i => i.id !== id);
                                this.videos = this.videos.filter(v => v.id !== id);
                                if (this.currentIndex >= this.total) this.currentIndex = 0;
                            },
                            setCaption(id, caption) {
                                const image = this.images.find(i => i.id === id);
                                if (!image) return;
                                image.caption = caption || '';
                                image.alt = caption || ('Gallery photo ' + (this.images.indexOf(image) + 1));
                            },
                            {{-- A deleted category releases its photos rather than taking them
                                 with it, so anything filed only there becomes unfiled. --}}
                            unfileCat(key) {
                                Object.keys(this.catMap).forEach(id => {
                                    const rest = this.catMap[id].filter(k => k !== key);
                                    if (rest.length !== this.catMap[id].length) {
                                        this.catMap[id] = rest.length ? rest : ['uncategorised'];
                                    }
                                });
                                if (this.activeCat === key) this.selectCat('all');
                            },
                            setCats(id, keys) {
                                this.catMap[id] = keys;
                                {{-- Filing the photo you were looking at into somewhere else can
                                     empty the set under you. Fall back rather than strand the
                                     lightbox on an index past the end. --}}
                                if (this.currentIndex >= this.total) this.currentIndex = 0;
                            }
                        }"
                        @keydown.escape.window="if (lightboxOpen) closeLightbox()"
                        @keydown.arrow-right.window="if (lightboxOpen) next()"
                        @keydown.arrow-left.window="if (lightboxOpen) prev()">

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white/90">Gallery</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Photos and videos shared in memory.</p>
                            </div>
                            @if ($canEdit)
                                <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium transition-colors duration-150 hover:bg-gray-50 active:scale-[0.97] motion-reduce:active:scale-100 dark:border-gray-600 dark:hover:bg-white/5">
                                    <input type="file" id="gallery-upload" accept="image/*,video/*" class="hidden" />
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    <span>Upload<span x-show="uploadTargetName" x-cloak x-text="' to ' + uploadTargetName"></span></span>
                                </label>
                            @endif
                        </div>
                        @if (isset($quotaInfo) && ($quotaInfo['gallery_images']['max'] > 0 || $quotaInfo['gallery_videos']['max'] > 0))
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                @if ($quotaInfo['gallery_images']['max'] > 0)
                                    <span data-quota-images data-current="{{ $quotaInfo['gallery_images']['current'] }}" data-max="{{ $quotaInfo['gallery_images']['max'] }}" class="{{ !$quotaInfo['gallery_images']['allowed'] ? 'text-red-500 dark:text-red-400 font-medium' : '' }}">
                                        Images: {{ $quotaInfo['gallery_images']['current'] }}/{{ $quotaInfo['gallery_images']['max'] }}
                                    </span>
                                @endif
                                @if ($quotaInfo['gallery_videos']['max'] > 0)
                                    <span data-quota-videos data-current="{{ $quotaInfo['gallery_videos']['current'] }}" data-max="{{ $quotaInfo['gallery_videos']['max'] }}" class="{{ !$quotaInfo['gallery_videos']['allowed'] ? 'text-red-500 dark:text-red-400 font-medium' : '' }}">
                                        Videos: {{ $quotaInfo['gallery_videos']['current'] }}/{{ $quotaInfo['gallery_videos']['max'] }}
                                    </span>
                                @endif
                            </div>
                        @endif

                        {{-- Category chips.

                             Filtering is instant — no transition on the grid. A photo grid is
                             the one place where animating the change actively hurts: two hundred
                             cells reflowing at once reads as jank however it is eased, and the
                             chip's own press feedback already confirms the tap was heard. --}}
                        @if ($showGalleryFilters)
                            <div class="mt-4 flex items-center gap-2">
                                <div data-category-chips class="-mx-1 flex flex-1 gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="Filter gallery by category">
                                    @php
                                        $chipClass = 'shrink-0 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium transition-colors duration-150 active:scale-[0.97] motion-reduce:active:scale-100';
                                        $chipOn = 'border-brand-500 bg-brand-500 text-white';
                                        $chipOff = 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-white';
                                    @endphp
                                    <button type="button" @click="selectCat('all')" :aria-pressed="activeCat === 'all'"
                                        :class="activeCat === 'all' ? '{{ $chipOn }}' : '{{ $chipOff }}'"
                                        class="{{ $chipClass }}">
                                        All <span class="opacity-60" x-text="catCount('all')"></span>
                                    </button>
                                    @foreach ($visibleGalleryChips as $category)
                                        <button type="button" data-category-chip="{{ $category->id }}" @click="selectCat('{{ $category->id }}')" :aria-pressed="activeCat === '{{ $category->id }}'"
                                            :class="activeCat === '{{ $category->id }}' ? '{{ $chipOn }}' : '{{ $chipOff }}'"
                                            class="{{ $chipClass }}">
                                            <span data-category-chip-name>{{ $category->name }}</span> <span class="opacity-60" x-text="catCount('{{ $category->id }}')"></span>
                                        </button>
                                    @endforeach
                                    @if ($showStoriesChip)
                                        <button type="button" @click="selectCat('stories')" :aria-pressed="activeCat === 'stories'"
                                            :class="activeCat === 'stories' ? '{{ $chipOn }}' : '{{ $chipOff }}'"
                                            class="{{ $chipClass }}" title="Photos and videos people shared with their stories">
                                            From Stories <span class="opacity-60" x-text="catCount('stories')"></span>
                                        </button>
                                    @endif
                                    <button type="button" data-chip-unfiled x-show="catCount('uncategorised') > 0" x-cloak @click="selectCat('uncategorised')" :aria-pressed="activeCat === 'uncategorised'"
                                        :class="activeCat === 'uncategorised' ? '{{ $chipOn }}' : '{{ $chipOff }}'"
                                        class="{{ $chipClass }} {{ $showUnfiledChip ? '' : 'hidden' }}">
                                        Other <span class="opacity-60" x-text="catCount('uncategorised')"></span>
                                    </button>
                                </div>
                                @if ($canEdit)
                                    <button type="button" data-category-manage
                                        class="shrink-0 rounded-full border border-dashed border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-500 transition-colors duration-150 hover:border-brand-400 hover:text-brand-600 active:scale-[0.97] motion-reduce:active:scale-100 dark:border-gray-600 dark:text-gray-400 dark:hover:border-brand-400 dark:hover:text-brand-400">
                                        Categories
                                    </button>
                                @endif
                            </div>
                        @endif

                        {{-- Sub-tabs: Images / Videos --}}
                        <div class="mt-3 flex gap-1 rounded-lg bg-gray-100 dark:bg-white/[0.04] p-1">
                            <button type="button" @click="subTab = 'images'"
                                :class="subTab === 'images' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition">
                                Images <span class="ml-1 text-xs opacity-60" x-text="'(' + visibleImages.length + ')'"></span>
                            </button>
                            <button type="button" @click="subTab = 'videos'"
                                :class="subTab === 'videos' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                                class="flex-1 rounded-md px-4 py-2 text-sm font-medium transition">
                                Videos <span class="ml-1 text-xs opacity-60" x-text="'(' + visibleVideoCount + ')'"></span>
                            </button>
                        </div>

                        {{-- Images grid --}}
                        <div x-show="subTab === 'images'" x-cloak class="mt-4">
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" id="gallery-grid-images">
                                @foreach ($galleryImages as $idx => $media)
                                    @php $isStoryMedia = isset($storyMediaIds[$media->id]); @endphp
                                    <div class="group/img relative aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700" x-show="matches({{ $media->id }})" data-gallery-item data-media-id="{{ $media->id }}" data-media-type="photo" @if ($isStoryMedia) data-from-story @endif>
                                        <button type="button" @click="openLightbox({{ $media->id }})"
                                            class="block h-full w-full focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                                            <img {!! \App\Helpers\ResponsiveImage::attrs($media->path, '(min-width: 640px) 33vw, 50vw') !!} alt="{{ $galleryAlt($media, $idx) }}"
                                                class="h-full w-full object-cover transition duration-300 group-hover/img:scale-105" loading="lazy" decoding="async" />
                                            <div class="absolute inset-0 bg-black/0 transition group-hover/img:bg-black/10"></div>
                                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/40 to-transparent p-2 opacity-0 transition group-hover/img:opacity-100">
                                                <svg class="mx-auto h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                            </div>
                                        </button>
                                        @if ($canEdit)
                                            <div class="absolute top-1 right-1 z-10 flex items-center gap-1">
                                                {{-- The caption is not passed through e() first: Blade's echo
                                                     already escapes it, and the two together double-encoded the
                                                     attribute — opening the editor on "Mum's 70th" put the
                                                     literal text "Mum&#039;s 70th" in the input, and saving
                                                     wrote that back as the caption. --}}
                                                <button type="button" data-gallery-edit-caption="{{ $media->id }}" data-current-caption="{{ $media->caption ?? '' }}" data-current-category="{{ $media->gallery_category_id }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-brand-500 active:scale-[0.97] motion-reduce:active:scale-100" title="Edit photo">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                {{-- No delete on a story's photo: the story is still showing it,
                                                     and removing the file here would leave a hole in what
                                                     someone wrote. It goes when the story goes. --}}
                                                @unless ($isStoryMedia)
                                                    <button type="button" data-gallery-delete="{{ $media->id }}"
                                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-red-500 active:scale-[0.97] motion-reduce:active:scale-100" title="Delete">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                @endunless
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            {{-- Visibility is Alpine's alone now. A Blade `hidden` class here would
                                 outrank x-show's inline style and keep the empty state hidden when a
                                 filter emptied the grid. --}}
                            <div id="gallery-images-empty" x-show="visibleImages.length === 0" x-cloak>
                                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400" x-text="activeCat === 'all' ? 'No photos yet.' : 'No photos in this category yet.'">No photos yet.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Videos grid --}}
                        <div x-show="subTab === 'videos'" x-cloak class="mt-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" id="gallery-grid-videos">
                                @foreach ($galleryVideos as $media)
                                    @php $isStoryMedia = isset($storyMediaIds[$media->id]); @endphp
                                    <div class="group/vid relative" x-show="matches({{ $media->id }})" data-gallery-item data-media-id="{{ $media->id }}" data-media-type="video" @if ($isStoryMedia) data-from-story @endif>
                                        <x-media.video-player :src="$media->url" :caption="$media->caption" />
                                        @if ($canEdit)
                                            <div class="absolute top-2 right-2 z-20 flex items-center gap-1">
                                                {{-- The caption is not passed through e() first: Blade's echo
                                                     already escapes it, and the two together double-encoded the
                                                     attribute — opening the editor on "Mum's 70th" put the
                                                     literal text "Mum&#039;s 70th" in the input, and saving
                                                     wrote that back as the caption. --}}
                                                <button type="button" data-gallery-edit-caption="{{ $media->id }}" data-current-caption="{{ $media->caption ?? '' }}" data-current-category="{{ $media->gallery_category_id }}"
                                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-brand-500 active:scale-[0.97] motion-reduce:active:scale-100" title="Edit video">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                </button>
                                                @unless ($isStoryMedia)
                                                    <button type="button" data-gallery-delete="{{ $media->id }}"
                                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-red-500 active:scale-[0.97] motion-reduce:active:scale-100" title="Delete">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                @endunless
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div id="gallery-videos-empty" x-show="visibleVideoCount === 0" x-cloak>
                                <div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-8 text-center">
                                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400" x-text="activeCat === 'all' ? 'No videos yet.' : 'No videos in this category yet.'">No videos yet.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Caption + category editor --}}
                        @if ($canEdit)
                            <div id="gallery-caption-editor" role="dialog" aria-modal="true" aria-labelledby="gallery-caption-title" class="hidden fixed inset-0 z-[99998] flex items-center justify-center bg-black/50 backdrop-blur-sm">
                                <div class="mx-4 w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl">
                                    <div class="p-5">
                                        <h3 id="gallery-caption-title" class="text-base font-semibold text-gray-900 dark:text-white">Edit photo</h3>
                                        <label for="gallery-caption-input" class="mt-3 block text-xs font-medium text-gray-500 dark:text-gray-400">Caption</label>
                                        <input type="text" id="gallery-caption-input" placeholder="Enter caption..." class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                        <label for="gallery-category-select" class="mt-4 block text-xs font-medium text-gray-500 dark:text-gray-400">Category</label>
                                        <select id="gallery-category-select" class="mt-1 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <option value="">Not filed</option>
                                            @foreach ($galleryCategories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                        <p id="gallery-caption-story-note" class="mt-2 hidden text-xs text-gray-500 dark:text-gray-400">
                                            This came from a story, so it stays under <span class="font-medium">From Stories</span> as well.
                                        </p>
                                        <input type="hidden" id="gallery-caption-media-id" />
                                    </div>
                                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-700 px-5 py-3">
                                        <button type="button" id="gallery-caption-cancel" class="btn btn-secondary btn-md">Cancel</button>
                                        <button type="button" id="gallery-caption-save" class="btn btn-primary btn-md">Save</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Category manager --}}
                            <div id="gallery-category-editor" role="dialog" aria-modal="true" aria-labelledby="gallery-category-title" class="hidden fixed inset-0 z-[99998] flex items-center justify-center bg-black/50 backdrop-blur-sm">
                                <div class="mx-4 w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl">
                                    <div class="p-5">
                                        <h3 id="gallery-category-title" class="text-base font-semibold text-gray-900 dark:text-white">Gallery categories</h3>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How visitors browse the photos. Deleting one keeps its photos — they just go back to unfiled.</p>

                                        <ul id="gallery-category-list" class="mt-4 space-y-1.5">
                                            @foreach ($galleryCategories as $category)
                                                <li class="flex items-center gap-2" data-category-row="{{ $category->id }}">
                                                    <input type="text" value="{{ $category->name }}" maxlength="60" aria-label="Category name" data-category-name
                                                        class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900" />
                                                    <button type="button" data-category-delete="{{ $category->id }}" title="Delete category"
                                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors duration-150 hover:bg-red-50 hover:text-red-500 active:scale-[0.97] motion-reduce:active:scale-100 dark:hover:bg-red-500/10">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <p id="gallery-category-empty" class="mt-4 text-sm text-gray-500 dark:text-gray-400 {{ $galleryCategories->isEmpty() ? '' : 'hidden' }}">No categories yet.</p>

                                        <div class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                                            <input type="text" id="gallery-category-new" maxlength="60" placeholder="e.g. School Life" aria-label="New category name"
                                                class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-600 dark:bg-gray-900" />
                                            <button type="button" data-category-add class="btn btn-secondary btn-sm shrink-0">Add</button>
                                        </div>
                                        <p id="gallery-category-error" class="mt-2 hidden text-xs text-red-500"></p>
                                    </div>
                                    <div class="flex items-center justify-end border-t border-gray-100 px-5 py-3 dark:border-gray-700">
                                        <button type="button" id="gallery-category-done" class="btn btn-primary btn-md">Done</button>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Lightbox overlay --}}
                        <template x-teleport="body">
                            <div x-show="lightboxOpen" x-cloak
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                role="dialog" aria-modal="true" aria-label="Gallery viewer"
                                @keydown.tab="trapTab($event)"
                                class="fixed inset-0 z-[99999] flex flex-col bg-black/95" @click.self="closeLightbox()">

                                {{-- Top bar: no flex-wrap on small screens (avoids pagination jumping under controls); scroll controls instead --}}
                                <div class="flex w-full min-h-[2.75rem] items-center gap-3 px-4 py-3 text-white">
                                    <span class="shrink-0 select-none whitespace-nowrap text-sm font-medium tabular-nums" x-text="(currentIndex + 1) + ' / ' + total"></span>
                                    <div class="flex min-h-[2.25rem] min-w-0 flex-1 flex-nowrap items-center justify-end gap-2 overflow-x-auto overscroll-x-contain py-0.5 [-ms-overflow-style:none] [scrollbar-width:none] sm:gap-3 [&::-webkit-scrollbar]:hidden">
                                        {{-- Slideshow toggle (x-show icons — avoids x-if remount layout jump on open) --}}
                                        <button type="button" @click="toggleSlideshow()"
                                            :class="playing ? 'bg-white/20' : 'hover:bg-white/10'"
                                            class="inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition"
                                            :title="playing ? 'Pause slideshow' : 'Start slideshow'">
                                            <svg x-show="!playing" class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                            <svg x-show="playing" class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                            <span class="whitespace-nowrap" x-text="playing ? 'Pause' : 'Slideshow'"></span>
                                        </button>
                                        {{-- Speed control --}}
                                        <div class="flex shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-2 py-1">
                                            <svg class="h-3.5 w-3.5 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <button type="button" @click="setSpeed(1500)"
                                                :class="speed === 1500 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">1.5s</button>
                                            <button type="button" @click="setSpeed(3000)"
                                                :class="speed === 3000 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">3s</button>
                                            <button type="button" @click="setSpeed(5000)"
                                                :class="speed === 5000 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">5s</button>
                                            <button type="button" @click="setSpeed(8000)"
                                                :class="speed === 8000 ? 'bg-white/20 text-white' : 'text-white/60 hover:text-white'"
                                                class="rounded px-1.5 py-0.5 text-xs font-medium transition">8s</button>
                                        </div>
                                        {{-- Close --}}
                                        <button type="button" x-ref="lightboxClose" @click="closeLightbox()" aria-label="Close gallery viewer" class="shrink-0 rounded-lg p-1.5 hover:bg-white/10 transition">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Main image area --}}
                                <div class="relative flex flex-1 items-center justify-center px-16" @click.self="closeLightbox()">
                                    {{-- Previous button --}}
                                    <button type="button" @click="prev()" x-show="total > 1" aria-label="Previous photo"
                                        class="absolute left-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white backdrop-blur-sm transition hover:bg-white/20">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    </button>

                                    {{-- Image --}}
                                    <img :src="currentImage.url" :alt="currentImage.alt"
                                        class="max-h-[80vh] max-w-full rounded-lg object-contain shadow-2xl select-none"
                                        @click.stop />

                                    {{-- Next button --}}
                                    <button type="button" @click="next()" x-show="total > 1" aria-label="Next photo"
                                        class="absolute right-2 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white backdrop-blur-sm transition hover:bg-white/20">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                </div>

                                {{-- Caption --}}
                                <div class="px-4 py-3 text-center" x-show="currentImage.caption">
                                    <p class="text-sm text-white/70" x-text="currentImage.caption"></p>
                                </div>

                                {{-- Thumbnail strip --}}
                                <div class="border-t border-white/10 px-4 py-3" x-show="total > 1">
                                    <div class="flex justify-center gap-1.5 overflow-x-auto">
                                        {{-- The strip has to show the same set the arrows walk, or the
                                             highlighted thumbnail stops matching the photo on screen.
                                             Keyed by id so filtering reuses cells instead of rebuilding
                                             every thumbnail. --}}
                                        <template x-for="(img, i) in visibleImages" :key="img.id">
                                            <button type="button" @click="currentIndex = i; if (playing) { stopSlideshow(); startSlideshow(); }"
                                                :class="i === currentIndex ? 'ring-2 ring-white opacity-100' : 'opacity-50 hover:opacity-80'"
                                                :aria-label="'Show ' + img.alt"
                                                :aria-current="i === currentIndex"
                                                class="h-12 w-12 shrink-0 overflow-hidden rounded-md transition">
                                                <img :src="img.thumb || img.url" alt="" class="h-full w-full object-cover" loading="lazy" />
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Slideshow progress bar --}}
                                <div x-show="playing" class="h-0.5 bg-white/10">
                                    <div class="h-full bg-brand-500 transition-all"
                                        :style="'animation: slideshow-progress ' + speed + 'ms linear infinite'"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Tab: Stories.

                         There were two sub-tabs in here — Tributes and Stories — and a
                         visitor had to pick one before they could say anything. They were
                         never two things: both were somebody writing something. One feed
                         now, and what used to be the choice of tab is an optional marker
                         on what you write. --}}
                    @php
                        $tc = $tributeCounts ?? ['flower' => 0, 'candle' => 0, 'prayer' => 0, 'total' => 0];
                    @endphp
                    <div id="tab-stories" role="tabpanel" aria-labelledby="tab-btn-stories" tabindex="0"
                        class="memorial-tab-panel hidden p-4 sm:p-6">
                        @include('pages.memorials.partials.stories-pane')
                    </div>
                </div>
            </section>

            {{-- Column 3, in the order a visitor meets it: the gesture, then the two asks
                 that follow from it (spread it, stay with it), then the figures — which are
                 the family's to watch and nobody else's business to act on. --}}
            <aside class="md:col-span-12 lg:col-span-3">
                <div class="lg:sticky lg:top-[4.5rem] grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4 sm:gap-6">

                    {{-- One-tap tributes. Each card posts immediately and bursts in place, so
                         the visitor stays where they are rather than being thrown to another tab. --}}
                    @php
                        $tributeActions = [
                            ['type' => 'flower', 'title' => 'Leave a Flower', 'image' => 'flower.png', 'noun' => 'flower'],
                            ['type' => 'candle', 'title' => 'Light a Candle', 'image' => 'candle.png', 'noun' => 'candle'],
                            ['type' => 'prayer', 'title' => 'Send a Prayer',  'image' => 'prayer.png', 'noun' => 'prayer'],
                        ];
                        $tributeQuotaReached = isset($quotaInfo) && $quotaInfo['tributes']['max'] > 0 && ! $quotaInfo['tributes']['allowed'];
                    @endphp
                    <div class="sm:col-span-2 lg:col-span-1">
                            {{-- The cards stay up and stay live once the limit is reached.
                                 Replacing them with a notice took the memorial's warmest
                                 gesture away from every visitor because the owner's plan
                                 filled up — and a visitor has no idea what that even means.
                                 What stops at the limit is the recording, not the gesture:
                                 the tap still plays, nothing is sent, no tally moves. --}}
                            <div class="grid grid-cols-3 gap-3" @if ($tributeQuotaReached) data-tribute-quota-reached @endif>
                                @foreach ($tributeActions as $action)
                                    <button type="button"
                                        class="memorial-tribute-action memorial-tribute-action--{{ $action['type'] }} group relative flex flex-col items-center justify-start overflow-hidden rounded-2xl border border-gray-200 bg-white/70 px-2 pb-4 pt-5 text-center dark:border-white/10 dark:bg-white/[0.04]"
                                        data-tribute-action="{{ $action['type'] }}"
                                        aria-label="{{ $action['title'] }} for {{ $memorial->full_name }}">
                                        <span class="memorial-tribute-action__art pointer-events-none mb-2.5 block h-16 w-16 sm:h-20 sm:w-20">
                                            @include('pages.memorials.partials.tribute-art', ['type' => $action['type'], 'image' => $action['image']])
                                        </span>
                                        {{-- Broken at the last space so every title is two
                                             lines: "Leave a / Flower", "Light a / Candle",
                                             "Send a / Prayer". Left to wrap naturally, only
                                             the longest one takes two lines and the three
                                             cards end up different heights with their counts
                                             on different rows. Derived rather than stored as
                                             two fields, so the title stays one string and the
                                             aria-label above keeps reading it whole. --}}
                                        <span class="text-sm font-bold leading-tight text-gray-900 dark:text-white">{{ Str::beforeLast($action['title'], ' ') }}<br>{{ Str::afterLast($action['title'], ' ') }}</span>
                                        <span class="mt-1.5 text-xs font-medium tabular-nums text-gray-500 dark:text-gray-400" data-tribute-action-count="{{ $action['type'] }}" data-noun="{{ $action['noun'] }}">
                                            {{ $tc[$action['type']] }} {{ Str::plural($action['noun'], $tc[$action['type']]) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            @if (isset($quotaInfo) && $quotaInfo['tributes']['max'] > 0)
                                @if ($tributeQuotaReached)
                                    {{-- Aimed at whoever can act on it. A visitor is not
                                         being told off for tapping; the owner is being told
                                         their plan is full. --}}
                                    <p class="mt-2 text-center text-[11px] text-amber-600 dark:text-amber-400">
                                        Tribute limit reached ({{ $quotaInfo['tributes']['current'] }}/{{ $quotaInfo['tributes']['max'] }}) — new tributes are no longer counted.
                                    </p>
                                @else
                                    <p class="mt-2 text-center text-[11px] text-gray-400 dark:text-gray-500">{{ $quotaInfo['tributes']['current'] }}/{{ $quotaInfo['tributes']['max'] }} tributes used</p>
                                @endif
                            @endif

                            {{-- Appears only after a tap lands, and only once. The gesture is
                                 complete on its own — nobody owes the page a paragraph — so
                                 this is an offer sitting quietly under the cards rather than
                                 a prompt thrown in front of the person who just made it.
                                 Taking it opens the composer with that marker already on. --}}
                            <button type="button" id="tribute-say-more" data-marker=""
                                class="tribute-say-more mt-3 hidden w-full rounded-lg border border-dashed border-brand-300 px-3 py-2 text-xs font-medium text-brand-600 dark:border-brand-500/60 dark:text-brand-400">
                                <span data-say-more-label>Add a few words</span>
                            </button>
                    </div>

                    {{-- Spreading it, and staying with it.

                         Both are things a visitor does once and then leaves, and both were
                         stranded: the invite was a footer bolted onto the statistics card,
                         and Stay Updated sat at the very bottom of the column. Views and
                         Shares is a figure for the family to watch; these two are asks of
                         the reader, so they belong together and directly under the gesture
                         they follow from. --}}
                    @if (($quotaInfo['share_memories'] ?? false) || ($quotaInfo['guest_notifications'] ?? false))
                        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 glass-card dark:bg-white/[0.03] shadow-theme-sm">
                                @if ($quotaInfo['share_memories'] ?? false)
                                    @php $deceasedFirstName = \Illuminate\Support\Str::before($memorial->full_name ?? '', ' ') ?: ($memorial->full_name ?? 'their'); @endphp
                                    <div class="p-4">
                                        <button type="button" id="invite-share-btn" data-share-url="{{ url()->current() }}" aria-expanded="false" aria-controls="invite-share-dropdown" class="flex w-full items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-brand-400 bg-brand-50/30 px-3 py-2 text-xs font-medium text-brand-600 transition hover:bg-brand-100 dark:border-brand-500 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20 sm:gap-2 sm:px-4 sm:py-3 sm:text-sm">
                                            <svg class="h-4 w-4 shrink-0 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                            <span class="text-left leading-snug">Invite {{ $deceasedFirstName }}'s family and friends</span>
                                        </button>
                                        {{-- The same channels a story offers. Inviting the family is
                                             the single most useful thing anyone does on this page,
                                             and it was the one share control that could only copy a
                                             link — leaving the person most likely to spread the
                                             memorial to paste it somewhere themselves.

                                             `data-share-dropdown` is what the shared click handlers
                                             key on, so this now closes when another one opens and
                                             when a channel is picked, like every other. --}}
                                        <div id="invite-share-dropdown" data-share-dropdown class="mt-2 hidden rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                            @include('pages.memorials.partials.share-dropdown', ['shareUrl' => url()->current()])
                                        </div>
                                    </div>
                                @endif
                        @if ($quotaInfo['guest_notifications'] ?? false)
                        <div class="{{ ($quotaInfo['share_memories'] ?? false) ? 'border-t border-gray-100 dark:border-gray-800' : '' }}"
                             x-data="{
                                subscribed: false,
                                loading: true,
                                submitting: false,
                                showForm: false,
                                guestName: '',
                                guestEmail: '',
                                subName: '',
                                notifyLifeChapters: true,
                                notifyTributes: true,
                                isAuth: {{ $isAuthenticated ? 'true' : 'false' }},
                                baseUrl: '{{ route('memorial.api.tribute', ['slug' => $memorial->slug]) }}'.replace(/\/tribute$/, ''),
                                csrf: document.querySelector('meta[name=csrf-token]')?.content,

                                init() {
                                    if (this.isAuth) {
                                        this._check();
                                    } else {
                                        this.loading = false;
                                    }
                                },

                                _fetchOpts(method, body) {
                                    return {
                                        method,
                                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        body: body ? JSON.stringify(body) : null,
                                    };
                                },

                                _check(email) {
                                    const url = this.baseUrl + '/subscribe/check' + (email ? '?email=' + encodeURIComponent(email) : '');
                                    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                                        .then(r => r.json())
                                        .then(data => {
                                            this.loading = false;
                                            if (data.subscribed) {
                                                this.subscribed = true;
                                                this.subName = data.subscription.name;
                                                this.notifyLifeChapters = data.subscription.notify_life_chapters;
                                                this.notifyTributes = data.subscription.notify_tributes;
                                            }
                                        })
                                        .catch(() => { this.loading = false; });
                                },

                                handleSubscribe() {
                                    if (this.isAuth) {
                                        this._doSubscribe();
                                    } else {
                                        this.showForm = true;
                                        this.$nextTick(() => this.$refs.subNameInput?.focus());
                                    }
                                },

                                submitGuestForm() {
                                    if (!this.guestName.trim() || !this.guestEmail.trim()) return;
                                    this._doSubscribe(this.guestName.trim(), this.guestEmail.trim());
                                },

                                _doSubscribe(name, email) {
                                    this.submitting = true;
                                    const body = { notify_life_chapters: this.notifyLifeChapters, notify_tributes: this.notifyTributes };
                                    if (name) body.guest_name = name;
                                    if (email) body.guest_email = email;
                                    fetch(this.baseUrl + '/subscribe', this._fetchOpts('POST', body))
                                        .then(r => r.json())
                                        .then(data => {
                                            this.submitting = false;
                                            if (data.success) {
                                                this.subscribed = true;
                                                this.subName = data.subscription.name;
                                                this.notifyLifeChapters = data.subscription.notify_life_chapters;
                                                this.notifyTributes = data.subscription.notify_tributes;
                                                this.showForm = false;
                                                if (email) this.guestEmail = email;
                                            } else if (data.error) {
                                                $toast('error', data.error);
                                            }
                                        })
                                        .catch(() => { this.submitting = false; $toast('error', 'Something went wrong.'); });
                                },

                                updatePrefs() {
                                    const body = { notify_life_chapters: this.notifyLifeChapters, notify_tributes: this.notifyTributes };
                                    if (!this.isAuth && this.guestEmail) body.guest_email = this.guestEmail;
                                    fetch(this.baseUrl + '/subscribe', this._fetchOpts('PUT', body));
                                },

                                unsubscribe() {
                                    const body = {};
                                    if (!this.isAuth && this.guestEmail) body.guest_email = this.guestEmail;
                                    fetch(this.baseUrl + '/subscribe', this._fetchOpts('DELETE', body))
                                        .then(r => r.json())
                                        .then(data => {
                                            if (data.success) {
                                                this.subscribed = false;
                                                this.subName = '';
                                                this.notifyLifeChapters = true;
                                                this.notifyTributes = true;
                                            }
                                        });
                                }
                             }" x-cloak>
                            <div class="p-4">
                                {{-- Loading --}}
                                <template x-if="loading">
                                    <div class="flex items-center justify-center py-4">
                                        <svg class="h-5 w-5 animate-spin text-brand-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                    </div>
                                </template>

                                {{-- Not subscribed --}}
                                <template x-if="!loading && !subscribed && !showForm">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 dark:bg-brand-500/20">
                                                <svg class="h-5 w-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <h3 class="font-semibold text-gray-900 dark:text-white/90 text-sm">Stay Updated</h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Get notified about new stories &amp; tributes</p>
                                            </div>
                                        </div>
                                        <button @click="handleSubscribe()" class="btn btn-primary btn-md btn-block w-full mt-4 active:scale-[0.98]">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                            Subscribe
                                        </button>
                                    </div>
                                </template>

                                {{-- Guest form --}}
                                <template x-if="!loading && !subscribed && showForm">
                                    <div>
                                        <div class="flex items-center gap-2 mb-3">
                                            <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                            </button>
                                            <h3 class="font-semibold text-gray-900 dark:text-white/90 text-sm">Subscribe</h3>
                                        </div>
                                        <form @submit.prevent="submitGuestForm()" class="space-y-3">
                                            <input x-model="guestName" x-ref="subNameInput" type="text" required placeholder="Your name" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3.5 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                            <input x-model="guestEmail" type="email" required placeholder="your@email.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-white/[0.03] px-3.5 text-sm placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
                                            <div class="space-y-2 rounded-lg bg-gray-50 dark:bg-white/[0.03] p-3">
                                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Notify me about:</p>
                                                <label class="flex items-center gap-2.5 cursor-pointer">
                                                    <input type="checkbox" x-model="notifyLifeChapters" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">New life chapters &amp; stories</span>
                                                </label>
                                                <label class="flex items-center gap-2.5 cursor-pointer">
                                                    <input type="checkbox" x-model="notifyTributes" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                                    <span class="text-sm text-gray-700 dark:text-gray-300">Tributes (flowers, candles, notes)</span>
                                                </label>
                                            </div>
                                            <button type="submit" :disabled="submitting" class="btn btn-primary btn-md btn-block w-full active:scale-[0.98] disabled:opacity-50">
                                                <template x-if="submitting"><svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg></template>
                                                <span x-text="submitting ? 'Subscribing...' : 'Subscribe'"></span>
                                            </button>
                                        </form>
                                    </div>
                                </template>

                                {{-- Subscribed: show preferences --}}
                                <template x-if="!loading && subscribed">
                                    <div>
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-500/20">
                                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <h3 class="font-semibold text-gray-900 dark:text-white/90 text-sm">Subscribed</h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Hi <span x-text="subName" class="font-medium text-gray-700 dark:text-gray-300"></span>, you'll get notified.</p>
                                            </div>
                                        </div>
                                        <div class="mt-4 space-y-2 rounded-lg bg-gray-50 dark:bg-white/[0.03] p-3">
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Notification preferences</p>
                                            <label class="flex items-center gap-2.5 cursor-pointer">
                                                <input type="checkbox" x-model="notifyLifeChapters" @change="updatePrefs()" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Life chapters &amp; stories</span>
                                            </label>
                                            <label class="flex items-center gap-2.5 cursor-pointer">
                                                <input type="checkbox" x-model="notifyTributes" @change="updatePrefs()" class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/30" />
                                                <span class="text-sm text-gray-700 dark:text-gray-300">Tributes (flowers, candles, notes)</span>
                                            </label>
                                        </div>
                                        <button @click="unsubscribe()" class="btn btn-secondary btn-md btn-block w-full mt-3">
                                            Unsubscribe
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        @endif
                        </div>
                    @endif

                    @php $stats = $memorialStats ?? ['views_today' => 0, 'views_last_week' => 0, 'views_all_time' => 0, 'shares_today' => 0, 'shares_last_week' => 0, 'shares_all_time' => 0]; @endphp
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 glass-card dark:bg-white/[0.03] shadow-theme-sm">
                        <div class="border-b border-gray-100 dark:border-gray-800 px-3 py-2 sm:px-4 sm:py-3">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white/90 sm:text-base">Views & Shares</h3>
                            <p class="mt-0.5 text-[11px] leading-snug text-gray-500 dark:text-gray-400 sm:text-xs">Unique people who have visited or shared this memorial</p>
                        </div>
                        <div class="space-y-3 p-3 sm:space-y-4 sm:p-4">
                            <div>
                                <label class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Views</label>
                                <div class="mt-1.5 grid grid-cols-3 gap-1.5 text-center sm:mt-2 sm:gap-2">
                                    <div class="rounded-lg bg-gray-50 p-1.5 dark:bg-white/[0.03] sm:p-2">
                                        <p class="text-base font-semibold tabular-nums text-gray-900 dark:text-white/90 sm:text-lg" data-stats-views-today>{{ $stats['views_today'] }}</p>
                                        <p class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400 sm:text-xs">Today</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 p-1.5 dark:bg-white/[0.03] sm:p-2">
                                        <p class="text-base font-semibold tabular-nums text-gray-900 dark:text-white/90 sm:text-lg" data-stats-views-week>{{ $stats['views_last_week'] }}</p>
                                        <p class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400 sm:text-xs"><span class="sm:hidden">Week</span><span class="hidden sm:inline">Last Week</span></p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 p-1.5 dark:bg-white/[0.03] sm:p-2">
                                        <p class="text-base font-semibold tabular-nums text-gray-900 dark:text-white/90 sm:text-lg" data-stats-views-all>{{ $stats['views_all_time'] }}</p>
                                        <p class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400 sm:text-xs"><span class="sm:hidden">All</span><span class="hidden sm:inline">All Time</span></p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Shares</label>
                                <div class="mt-1.5 grid grid-cols-3 gap-1.5 text-center sm:mt-2 sm:gap-2">
                                    <div class="rounded-lg bg-gray-50 p-1.5 dark:bg-white/[0.03] sm:p-2">
                                        <p class="text-base font-semibold tabular-nums text-gray-900 dark:text-white/90 sm:text-lg" data-stats-shares-today>{{ $stats['shares_today'] }}</p>
                                        <p class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400 sm:text-xs">Today</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 p-1.5 dark:bg-white/[0.03] sm:p-2">
                                        <p class="text-base font-semibold tabular-nums text-gray-900 dark:text-white/90 sm:text-lg" data-stats-shares-week>{{ $stats['shares_last_week'] }}</p>
                                        <p class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400 sm:text-xs"><span class="sm:hidden">Week</span><span class="hidden sm:inline">Last Week</span></p>
                                    </div>
                                    <div class="rounded-lg bg-gray-50 p-1.5 dark:bg-white/[0.03] sm:p-2">
                                        <p class="text-base font-semibold tabular-nums text-gray-900 dark:text-white/90 sm:text-lg" data-stats-shares-all>{{ $stats['shares_all_time'] }}</p>
                                        <p class="mt-0.5 text-[10px] leading-tight text-gray-500 dark:text-gray-400 sm:text-xs"><span class="sm:hidden">All</span><span class="hidden sm:inline">All Time</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </aside>
        </div>
    </main>

    @include('pages.memorials.partials.comment-sheet')

    {{-- The floating dock.

         Two separately-positioned widgets, each given its own `fixed` corner, is why they
         sat side by side and why they landed on top of the feed on a phone rather than
         beside it. One column, bottom right, the way a chat launcher sits.

         Share is here for small screens only. On a wide screen the invite panel is in the
         sidebar a glance away; below `lg` that whole column drops to the very bottom of a
         long page, so the thing we most want a visitor to do became the hardest to reach. --}}
    @php
        // Read before the dock, not inside it: both the upload control and the player below
        // need these, and the upload control is rendered first.
        $bgMusicAllowed = $quotaInfo['background_music'] ?? false;
        $bgMusicUrl = ($bgMusicAllowed && $memorial->background_music) ? \App\Helpers\StorageHelper::publicUrl($memorial->background_music) : null;
    @endphp
    <div class="memorial-dock fixed bottom-5 right-4 z-50 flex flex-col items-end gap-2.5 sm:bottom-6 sm:right-6">
        {{-- Background music: upload control for owner/admin (plan-gated) --}}
        @if ($canEdit && ($quotaInfo['background_music'] ?? false))
            <div id="bg-music-admin"
                x-data="{ uploading: false }"
                class="order-1">
                <div class="flex flex-col items-center gap-1.5">
                    <label :class="uploading ? 'opacity-50 pointer-events-none' : ''"
                        class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 shadow-md transition hover:scale-110 hover:border-brand-300 hover:text-brand-500 active:scale-95">
                        <input type="file" accept="audio/*" class="hidden"
                            @change="
                                if (!$event.target.files[0]) return;
                                uploading = true;
                                const fd = new FormData();
                                fd.append('file', $event.target.files[0]);
                                fd.append('_token', document.querySelector('meta[name=csrf-token]').content);
                                fetch(document.querySelector('[data-memorial-slug]').dataset.tributeUrl.replace(/\/tribute$/, '/background-music'), {
                                    method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd
                                }).then(r => r.json()).then(data => {
                                    uploading = false;
                                    if (data.success) {
                                        const widget = document.getElementById('bg-music-widget');
                                        if (widget && widget.__x) widget.__x.$data.setMusic(data.url);
                                        else if (typeof Alpine !== 'undefined') Alpine.$data(widget).setMusic(data.url);
                                    } else { $toast('error', data.error || 'Upload failed'); }
                                }).catch(() => { uploading = false; $toast('error', 'Upload failed'); });
                                $event.target.value = '';
                            ">
                        <template x-if="!uploading">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        </template>
                        <template x-if="uploading">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500"></div>
                        </template>
                    </label>
                    <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500 whitespace-nowrap">{{ $bgMusicUrl ? 'Change' : 'Add' }} Music</span>
                </div>
            </div>
        @endif
        {{-- Background music: the player itself. Hidden until there is something to play. --}}
        <div id="bg-music-widget"
        x-data="{
            hasMusic: {{ $bgMusicUrl ? 'true' : 'false' }},
            muted: false,
            showTooltip: false,
            storageKey: 'bgm_muted_{{ $memorial->slug }}',
            _bound: false,
            init() {
                this.muted = localStorage.getItem(this.storageKey) === '1';
                const audio = this.$refs.bgAudio;
                if (!audio || !audio.src) return;
                audio.volume = 0.3;
                if (this.muted) { audio.muted = true; return; }
                audio.muted = false;
                this._tryAutoplay(audio);
            },
            _tryAutoplay(audio) {
                if (this._bound) return;
                const attempt = () => {
                    if (this.muted || !audio.paused) return;
                    audio.play().then(() => { this._cleanup(); }).catch(() => {});
                };
                attempt();
                audio.addEventListener('canplaythrough', () => attempt(), { once: true });
                setTimeout(() => attempt(), 500);
                setTimeout(() => attempt(), 1500);
                this._bound = true;
                this._handler = () => {
                    if (this.muted) return;
                    audio.play().then(() => { this._cleanup(); }).catch(() => {});
                };
                ['click','touchstart','touchend','scroll','keydown','pointerdown','pointerup'].forEach(e =>
                    document.addEventListener(e, this._handler, { capture: true })
                );
            },
            _cleanup() {
                if (!this._handler) return;
                ['click','touchstart','touchend','scroll','keydown','pointerdown','pointerup'].forEach(e =>
                    document.removeEventListener(e, this._handler, { capture: true })
                );
                this._handler = null;
            },
            toggle() {
                this.muted = !this.muted;
                const audio = this.$refs.bgAudio;
                audio.muted = this.muted;
                localStorage.setItem(this.storageKey, this.muted ? '1' : '0');
                if (!this.muted && audio.paused) audio.play().catch(() => {});
            },
            setMusic(url) {
                this.hasMusic = true;
                const audio = this.$refs.bgAudio;
                audio.src = url;
                audio.load();
                this.muted = false;
                localStorage.setItem(this.storageKey, '0');
                audio.muted = false;
                audio.play().catch(() => {});
            },
            removeMusic() {
                this.hasMusic = false;
                const audio = this.$refs.bgAudio;
                audio.pause();
                audio.src = '';
            }
        }"
        x-show="hasMusic"
        x-cloak
        class="order-2">

        @if ($bgMusicUrl)
            <audio x-ref="bgAudio" loop preload="auto" autoplay src="{{ $bgMusicUrl }}"></audio>
        @else
            <audio x-ref="bgAudio" loop preload="auto"></audio>
        @endif

        <div class="flex flex-col items-center gap-1.5"
            @mouseenter="showTooltip = true" @mouseleave="showTooltip = false">

            {{-- Tooltip --}}
            <div x-show="showTooltip" x-cloak x-transition
                class="rounded-lg bg-gray-900 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-white shadow-lg whitespace-nowrap">
                <span x-text="muted ? 'Tap to unmute' : 'Tap to mute'"></span>
            </div>

            {{-- Mute/Unmute button --}}
            <button type="button" @click="toggle()"
                :class="muted ? 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400' : 'bg-brand-500 text-white shadow-lg shadow-brand-500/30'"
                class="flex h-12 w-12 items-center justify-center rounded-full transition-all duration-300 hover:scale-110 active:scale-95">
                {{-- Unmuted: music note with animated rings --}}
                <template x-if="!muted">
                    <span class="relative flex items-center justify-center">
                        <span class="absolute h-10 w-10 animate-ping rounded-full bg-brand-400/20"></span>
                        <svg class="relative h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                    </span>
                </template>
                {{-- Muted: muted speaker --}}
                <template x-if="muted">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/></svg>
                </template>
            </button>

            {{-- Label --}}
            <span class="text-[10px] font-medium leading-tight text-center"
                :class="muted ? 'text-gray-400 dark:text-gray-500' : 'text-brand-600 dark:text-brand-400'"
                x-text="muted ? 'Muted' : 'Playing'"></span>
        </div>
    </div>

    @if ($quotaInfo['share_memories'] ?? false)
        {{-- The dropdown has to be this button's immediate next sibling: that is how the
             shared toggle handler finds it when there is no post id. Opens upward, because
             there is nothing below it. --}}
        <div class="relative order-3 lg:hidden" data-share-container>
            <button type="button" data-share-toggle data-share-url="{{ url()->current() }}" aria-label="Share this memorial"
                class="memorial-dock__fab flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-700 shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            </button>
            <div data-share-dropdown class="absolute bottom-full right-0 mb-2 hidden w-52 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-800">
                @include('pages.memorials.partials.share-dropdown', ['shareUrl' => url()->current()])
            </div>
        </div>
    @endif
    </div>

</div>

@vite('resources/js/memorial-public.js')
@endsection
