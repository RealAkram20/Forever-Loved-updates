@extends('layouts.app')

@section('content')
    <x-common.page-header title="Pages"
        desc="Build and publish your own pages on your site, with the same drag-and-drop editor." />

    @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
    @endif
    @if (session('error'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('error', @json(session('error'))));</script>
    @endif

    @if ($locked)
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] px-6 py-16 text-center">
            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M7 10.5V7.5a5 5 0 0 1 10 0v3M5.75 10.5h12.5a1.5 1.5 0 0 1 1.5 1.5v7a1.5 1.5 0 0 1-1.5 1.5H5.75a1.5 1.5 0 0 1-1.5-1.5v-7a1.5 1.5 0 0 1 1.5-1.5Z"/></svg>
            <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Not included in your {{ $reseller->tier?->name ?? 'current' }} tier</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                The website builder lets you add your own pages — an About page, a service list, a landing page — and publish them on your site with a visual drag-and-drop editor. Get in touch to add it.
            </p>
        </div>
    @else
        {{-- The homepage: a system page, edited with the same builder but shown on its own so
             it never sits in the deletable list below. --}}
        @php $homeCustomised = $homePage && is_array($homePage->layout['widgets'] ?? null) && count($homePage->layout['widgets']) > 0; @endphp
        <a href="{{ route('reseller.pages.home') }}"
           class="group mb-4 flex items-center justify-between gap-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4 sm:p-5 transition-colors hover:border-brand-300 dark:hover:border-brand-500/50">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 dark:bg-brand-500/10 text-brand-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 dark:text-white/90 group-hover:text-brand-600 dark:group-hover:text-brand-400">Homepage</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Your site's front page.
                        @if ($homeCustomised)
                            You've built a custom layout.
                        @else
                            Currently using the default branded layout — build your own.
                        @endif
                    </p>
                </div>
            </div>
            <span class="btn btn-secondary btn-sm shrink-0">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit homepage
            </span>
        </a>

        {{-- The pages every site has. Switched on and off rather than created and deleted —
             turning one off keeps the content, so re-enabling restores their own copy. --}}
        <x-common.component-card title="Standard pages"
            desc="The pages most sites have. Switch on the ones you want; your visitors only see what is on."
            class="mb-8">
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($standardPages as $row)
                    @continue($row['slug'] === \App\Models\Page::SLUG_VISITOR_HOME)
                    @php
                        $definition = $row['definition'];
                        $page = $row['page'];
                        $enabled = $row['enabled'];
                        $lockedOn = ! $definition['disableable'];
                    @endphp
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3.5">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $page->title ?? $definition['title'] }}</p>
                                <span class="font-mono text-xs text-gray-400 dark:text-gray-500">/{{ $row['slug'] }}</span>
                                @if ($lockedOn)
                                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Always on</span>
                                @elseif ($enabled)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">On</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Off</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $definition['blurb'] }}</p>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($page && $enabled)
                                <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    View
                                </a>
                            @endif
                            @if ($page)
                                <a href="{{ route('reseller.pages.edit', $row['slug']) }}" class="btn btn-primary btn-sm">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit
                                </a>
                            @endif
                            @unless ($lockedOn)
                                <form action="{{ route('reseller.pages.standard.toggle', $row['slug']) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
                                    <button type="submit" class="btn {{ $enabled ? 'btn-secondary' : 'btn-primary' }} btn-sm">
                                        {{ $enabled ? 'Turn off' : 'Turn on' }}
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </x-common.component-card>

        <x-common.component-card title="Your pages"
            desc="Each page is published on your own site. Use Add page for a new URL, or Edit to change its layout and details.">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Your pages live at <span class="font-mono text-gray-800 dark:text-white/90">{{ $reseller->publicDisplayAddress() }}/your-slug</span>.
                    @if ($reseller->usingFallbackAddress())
                        <span class="text-amber-600 dark:text-amber-400">(development address — your live subdomain/domain serves these once DNS is set up.)</span>
                    @endif
                </p>
                <a href="{{ route('reseller.pages.create') }}" class="btn btn-primary btn-md shrink-0">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add page
                </a>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Page</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Last updated</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @forelse ($pages as $page)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white/90">{{ $page->title }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">/{{ $page->slug }}</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($page->is_published)
                                        <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">Published</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Draft</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $page->updated_at->diffForHumans() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ route('reseller.pages.edit', $page->slug) }}"
                                           class="btn btn-primary btn-sm">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            Edit
                                        </a>
                                        <a href="{{ $reseller->publicUrlForSlug($page->slug) }}" target="_blank" rel="noopener noreferrer"
                                           class="btn btn-secondary btn-sm">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            View on site
                                        </a>
                                        <form action="{{ route('reseller.pages.destroy', $page->slug) }}" method="POST" class="inline"
                                              onsubmit="return window._resellerPageDeleteConfirm('{{ $page->slug }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center rounded-lg p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:text-red-400 dark:hover:bg-red-900/20 transition"
                                                    title="Delete page">
                                                <span class="sr-only">Delete page</span>
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No pages yet. <a href="{{ route('reseller.pages.create') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Add your first page</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-common.component-card>

        @push('scripts')
        <script>
        (function () {
            var pending = {};
            window._resellerPageDeleteConfirm = function (slug) {
                if (!pending[slug]) {
                    pending[slug] = true;
                    if (window.$toast) window.$toast('warning', 'Click delete again to confirm.');
                    setTimeout(function () { pending[slug] = false; }, 5000);
                    return false;
                }
                pending[slug] = false;
                return true;
            };
        })();
        </script>
        @endpush
    @endif
@endsection
