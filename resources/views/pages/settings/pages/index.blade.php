@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Manage Pages" />

    @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
    @endif
    @if (session('error'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('error', @json(session('error'))));</script>
    @endif

    <p class="mb-6 flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
        <a href="{{ route('settings.pages.edit', \App\Models\Page::SLUG_VISITOR_HOME) }}" class="font-medium text-brand-500 hover:underline">Homepage editor</a>
        <a href="{{ route('settings.menus.edit') }}" class="font-medium text-brand-500 hover:underline">Navigation menus</a>
    </p>

    <x-common.component-card title="Editable Pages" desc="Home, Pricing, and Contact use one visual editor when a CMS page exists. Find Memorial only has route metadata (no page builder). About, Privacy, and Terms use the editor when those slugs exist. Custom pages use /p/your-slug unless they match built-in paths.">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">Use <strong class="font-medium text-gray-800 dark:text-white/90">Add page</strong> for a new URL, or <strong class="font-medium text-gray-800 dark:text-white/90">Edit</strong> for layout and page details in one place.</p>
            <a href="{{ route('settings.pages.create') }}"
               class="btn btn-primary btn-md shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add page
            </a>
        </div>
        {{-- `overflow-x-auto`, not `overflow-hidden`. This is a four-column table with px-6
             cells: on a phone it is around 670px wide, and hidden simply cut Status, Last
             updated and Actions off with no way to reach them. Auto lets the table scroll
             inside its own border, which is what the pricing tables already do. --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
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
                    @foreach ($systemPages as $row)
                        <tr class="bg-gray-50/90 dark:bg-gray-800/50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white/90">{{ $row['title'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $row['subtitle'] }}</div>
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $row['path'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                @if ($row['is_published'])
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">Published</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400">Draft</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $row['updated_at'] ? $row['updated_at']->diffForHumans() : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ $row['edit_url'] }}"
                                       class="btn btn-primary btn-sm">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        {{ $row['edit_label'] }}
                                    </a>
                                    @foreach ($row['extra_links'] as $link)
                                        <a href="{{ $link['url'] }}"
                                           class="btn btn-secondary btn-sm">
                                            {{ $link['label'] }}
                                        </a>
                                    @endforeach
                                    <a href="{{ $row['view_url'] }}" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-secondary btn-sm">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        View on site
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    @forelse ($pages as $page)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white/90">{{ $page->title }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ parse_url($page->publicUrl(), PHP_URL_PATH) ?: '/' }}</div>
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
                                    <a href="{{ route('settings.pages.edit', $page->slug) }}"
                                       class="btn btn-primary btn-sm">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Edit
                                    </a>
                                    <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-secondary btn-sm">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        View on site
                                    </a>
                                    @unless ($page->isSystemLayoutPage())
                                        <form action="{{ route('settings.pages.destroy', $page->slug) }}" method="POST" class="inline"
                                              onsubmit="return window._pageDeleteConfirm('{{ $page->slug }}')">
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
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No custom CMS pages yet. <a href="{{ route('settings.pages.create') }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">Add a page</a> for content at /p/your-slug.
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
        window._pageDeleteConfirm = function (slug) {
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
@endsection
