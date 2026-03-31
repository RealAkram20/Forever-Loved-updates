@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Navigation menus" />

    @if (session('success'))
        <script>document.addEventListener('DOMContentLoaded', () => window.$toast?.('success', @json(session('success'))));</script>
    @endif

    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
        Drag items to reorder. Choose a <strong class="font-medium text-gray-800 dark:text-white/90">site route</strong> or <strong class="font-medium text-gray-800 dark:text-white/90">CMS page</strong> from the list, or leave the route on “Custom URL only” and paste a path or full URL. At least one of route or URL should be set.
    </p>

    @php
        $menuConfigs = [
            \App\Models\Menu::LOCATION_HEADER => ['title' => 'Header navigation', 'desc' => 'Shown in the visitor site header (desktop and mobile).'],
            \App\Models\Menu::LOCATION_FOOTER_QUICK => ['title' => 'Footer — Quick links', 'desc' => 'First link column in the footer.'],
            \App\Models\Menu::LOCATION_FOOTER_COMPANY => ['title' => 'Footer — Company', 'desc' => 'Second link column in the footer.'],
        ];
    @endphp

    @foreach ($menuConfigs as $location => $meta)
        @php
            $menu = $menus->get($location);
            $items = $menu ? $menu->allItems->whereNull('parent_id')->sortBy('sort_order')->values() : collect();
        @endphp

        <x-common.component-card :title="$meta['title']" :desc="$meta['desc']" class="mb-8">
            <ul
                id="sort-{{ $location }}"
                class="sortable-menu space-y-2 mb-6"
                data-location="{{ $location }}"
            >
                @forelse ($items as $item)
                    <li
                        data-id="{{ $item->id }}"
                        class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-800/50 px-3 py-2.5 cursor-grab active:cursor-grabbing"
                    >
                        <span class="flex h-9 shrink-0 items-center self-end text-base leading-none text-gray-400 select-none" aria-hidden="true" title="Drag to reorder">⠿</span>
                        <form action="{{ route('settings.menus.items.update', $item) }}" method="POST" class="flex min-w-0 flex-1 flex-wrap items-end gap-x-3 gap-y-2">
                            @csrf
                            @method('PUT')
                            <div class="min-w-[8rem] flex-1">
                                <label class="text-theme-xs text-gray-500 dark:text-gray-400">Label</label>
                                <input type="text" name="label" value="{{ $item->label }}" required
                                    class="mt-0.5 h-9 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm" />
                            </div>
                            <div class="min-w-[10rem] flex-1">
                                <label class="text-theme-xs text-gray-500 dark:text-gray-400">Route</label>
                                @include('pages.settings.menus.partials.route-select', [
                                    'menuRouteGroups' => $menuRouteGroups,
                                    'selectedValue' => $item->routeSelectValue(),
                                ])
                            </div>
                            <div class="min-w-[8rem] flex-1">
                                <label class="text-theme-xs text-gray-500 dark:text-gray-400">Custom URL</label>
                                <input type="text" name="url" value="{{ $item->url }}" placeholder="/page or https://"
                                    class="mt-0.5 h-9 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm" />
                            </div>
                            <div class="flex h-9 shrink-0 items-center gap-2">
                                <label class="flex cursor-pointer items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                    <input type="hidden" name="open_in_new_tab" value="0">
                                    <input type="checkbox" name="open_in_new_tab" value="1" @checked($item->open_in_new_tab) class="rounded border-gray-300 text-brand-500" />
                                    New tab
                                </label>
                            </div>
                            <button type="submit" class="inline-flex h-9 shrink-0 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 transition">Save</button>
                        </form>
                        <div class="flex h-9 shrink-0 items-center">
                            <form action="{{ route('settings.menus.items.destroy', $item) }}" method="POST"
                                  onsubmit="return window._menuDeleteConfirm({{ $item->id }}, this)">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:text-red-400 dark:hover:bg-red-900/20 transition"
                                        title="Remove link">
                                    <span class="sr-only">Remove link</span>
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-gray-500 dark:text-gray-400 py-2">No items yet — add one below.</li>
                @endforelse
            </ul>

            <form action="{{ route('settings.menus.reorder') }}" method="POST" id="form-reorder-{{ $location }}" class="hidden">
                @csrf
                <input type="hidden" name="menu_location" value="{{ $location }}">
                <div class="reorder-inputs reorder-inputs-{{ $location }}"></div>
            </form>

            <form action="{{ route('settings.menus.items.store') }}" method="POST" class="flex flex-wrap items-end gap-x-3 gap-y-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                @csrf
                <input type="hidden" name="menu_location" value="{{ $location }}">
                <div>
                    <label class="text-theme-xs text-gray-500 dark:text-gray-400">New label</label>
                    <input type="text" name="label" required placeholder="Link text"
                        class="mt-0.5 h-9 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm w-40" />
                </div>
                <div>
                    <label class="text-theme-xs text-gray-500 dark:text-gray-400">Route</label>
                    @include('pages.settings.menus.partials.route-select', [
                        'menuRouteGroups' => $menuRouteGroups,
                        'selectedValue' => '',
                        'selectClass' => 'mt-0.5 h-9 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm min-w-[12rem]',
                    ])
                </div>
                <div>
                    <label class="text-theme-xs text-gray-500 dark:text-gray-400">Custom URL</label>
                    <input type="text" name="url" placeholder="Optional"
                        class="mt-0.5 h-9 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 text-sm w-48" />
                </div>
                <div class="flex h-9 shrink-0 items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                        <input type="hidden" name="open_in_new_tab" value="0">
                        <input type="checkbox" name="open_in_new_tab" value="1" class="rounded border-gray-300 text-brand-500" />
                        New tab
                    </label>
                </div>
                <button type="submit" class="inline-flex h-9 shrink-0 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600 transition">Add link</button>
            </form>
        </x-common.component-card>
    @endforeach

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
    (function () {
        var pending = {};
        window._menuDeleteConfirm = function (id) {
            if (!pending[id]) {
                pending[id] = true;
                if (window.$toast) window.$toast('warning', 'Click delete again to confirm.');
                setTimeout(function () { pending[id] = false; }, 5000);
                return false;
            }
            pending[id] = false;
            return true;
        };
    })();
    </script>
    <script>
        document.querySelectorAll('.sortable-menu').forEach(function (ul) {
            var location = ul.dataset.location;
            var form = document.getElementById('form-reorder-' + location);
            if (!form || typeof Sortable === 'undefined') return;
            new Sortable(ul, {
                animation: 150,
                handle: 'li',
                onEnd: function () {
                    var box = form.querySelector('.reorder-inputs-' + location);
                    if (!box) return;
                    box.innerHTML = '';
                    ul.querySelectorAll('li[data-id]').forEach(function (li) {
                        var id = li.getAttribute('data-id');
                        if (!id) return;
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'item_ids[]';
                        input.value = id;
                        box.appendChild(input);
                    });
                    form.submit();
                }
            });
        });
    </script>
    @endpush
@endsection
