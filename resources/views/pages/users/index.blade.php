@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Users" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6" x-data="{ selected: [], page: @js($users->pluck('id')->reject(fn ($id) => $id === auth()->id())->values()) }">
        {{-- Filters & Actions --}}
        <x-common.component-card title="Manage Users" desc="View, create, edit and manage all system users.">
            {{-- Wrapping, not just stacking. Below `sm` these are a column and fine; from
                 `sm` up they become a row of four controls plus a button, inside a column
                 that has already given width to the sidebar — which overflowed the page by
                 87px at tablet size. Wrapping lets the row use a second line instead. --}}
            <div class="flex flex-col flex-wrap sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                <form method="GET" action="{{ route('users.index') }}" class="flex w-full flex-col flex-wrap sm:w-auto sm:flex-row items-start sm:items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or email..."
                        class="h-11 w-full sm:w-64 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    <select name="role"
                        class="h-11 w-full sm:w-auto rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                        <option value="">All Roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                        @endforeach
                    </select>
                    <x-admin.reseller-filter :resellers="$resellers" :standalone="false" />
                    {{-- Link-shaped or message-length names that own nothing: the accounts the
                         2026-09-04 relay left behind. Same definition the bulk delete uses. --}}
                    <label class="inline-flex h-11 items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="suspicious" value="1" @checked(request()->boolean('suspicious'))
                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" />
                        Suspicious names
                    </label>
                    <button type="submit"
                        class="btn btn-secondary btn-md">
                        Filter
                    </button>
                </form>
                <a href="{{ route('users.create') }}"
                    class="btn btn-primary btn-md shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add User
                </a>
            </div>

            {{-- Bulk actions. Shown only once something is ticked, or when the suspicious
                 filter is on and there is a whole set to clear. Both forms live outside the
                 table: the per-row delete forms are inside it and forms cannot nest, so the
                 row checkboxes reach this one through form="bulk-ids" instead. --}}
            <div x-show="selected.length > 0" x-cloak
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/40 dark:bg-red-900/20">
                <p class="text-sm text-red-800 dark:text-red-300">
                    <span class="font-semibold" x-text="selected.length"></span>
                    <span x-text="selected.length === 1 ? 'user selected' : 'users selected'"></span>
                    <span class="text-red-700/70 dark:text-red-300/70">&middot; memorial owners, payers and staff are never deleted, even if ticked</span>
                </p>
                <form id="bulk-ids" method="POST" action="{{ route('users.bulk-destroy') }}"
                    onsubmit="return confirm('Delete the selected users? Accounts that own memorials, have payments, or are staff will be skipped.')">
                    @csrf
                    <input type="hidden" name="mode" value="ids" />
                    @foreach (['search', 'role', 'reseller', 'suspicious'] as $k)
                        @if (request()->filled($k))<input type="hidden" name="{{ $k }}" value="{{ request($k) }}" />@endif
                    @endforeach
                    <button type="submit" class="btn btn-md bg-red-600 text-white hover:bg-red-700">Delete selected</button>
                </form>
            </div>

            @if (request()->boolean('suspicious') && $users->total() > 0)
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/40 dark:bg-amber-900/20">
                    <p class="text-sm text-amber-900 dark:text-amber-200">
                        <span class="font-semibold">{{ number_format($users->total()) }}</span>
                        {{ $users->total() === 1 ? 'account matches' : 'accounts match' }} the suspicious filter across all pages.
                        Up to {{ \App\Support\JunkUserPurge::WEB_BATCH }} are removed per click; the message afterwards says how many remain.
                    </p>
                    <form method="POST" action="{{ route('users.bulk-destroy') }}"
                        onsubmit="return confirm('Delete up to {{ \App\Support\JunkUserPurge::WEB_BATCH }} of the {{ number_format($users->total()) }} suspicious accounts? Memorial owners, payers and staff are always skipped.')">
                        @csrf
                        <input type="hidden" name="mode" value="scope" />
                        <input type="hidden" name="suspicious" value="1" />
                        <button type="submit" class="btn btn-md bg-amber-600 text-white hover:bg-amber-700">Delete all suspicious</button>
                    </form>
                </div>
            @endif

            {{-- Users Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-3 w-8">
                                <input type="checkbox" aria-label="Select all on this page"
                                    :checked="page.length > 0 && selected.length === page.length"
                                    :indeterminate="selected.length > 0 && selected.length < page.length"
                                    @change="selected = $event.target.checked ? [...page] : []"
                                    class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" />
                            </th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden sm:table-cell">Email</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                            @if ($resellers->isNotEmpty())
                                <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden lg:table-cell">Owner</th>
                            @endif
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden md:table-cell">Memorials</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden md:table-cell">Joined</th>
                            <th class="pb-3 text-right font-medium text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($users as $user)
                            <tr>
                                <td class="py-3 w-8">
                                    @if ($user->id !== auth()->id())
                                        <input type="checkbox" form="bulk-ids" name="ids[]" value="{{ $user->id }}"
                                            x-model.number="selected" aria-label="Select {{ $user->email }}"
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" />
                                    @endif
                                </td>
                                <td class="py-3">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 sm:hidden">{{ $user->email }}</div>
                                </td>
                                <td class="py-3 text-gray-500 dark:text-gray-400 hidden sm:table-cell">{{ $user->email }}</td>
                                <td class="py-3">
                                    @php $roleName = $user->roles->pluck('name')->first() ?? 'none'; @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @if($roleName === 'super-admin') bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400
                                        @elseif($roleName === 'admin') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                        @endif">
                                        {{ $roleName }}
                                    </span>
                                </td>
                                @if ($resellers->isNotEmpty())
                                    <td class="py-3 hidden lg:table-cell"><x-admin.owner-tag :reseller="$user->reseller" /></td>
                                @endif
                                <td class="py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ $user->memorials_count ?? $user->memorials()->count() }}</td>
                                <td class="py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ $user->created_at->format('M d, Y') }}</td>
                                <td class="py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('users.edit', $user) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-brand-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                                            title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        @if ($user->id !== auth()->id())
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete {{ addslashes($user->name) }}?')">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                                    title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $resellers->isNotEmpty() ? 8 : 7 }}" class="py-8 text-center text-gray-500 dark:text-gray-400">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if ($users->hasPages())
                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} users
                    </p>
                    <div class="flex items-center gap-1">
                        @if ($users->onFirstPage())
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </span>
                        @else
                            <a href="{{ $users->previousPageUrl() }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                        @endif

                        @foreach ($users->getUrlRange(max(1, $users->currentPage() - 2), min($users->lastPage(), $users->currentPage() + 2)) as $page => $url)
                            <a href="{{ $url }}"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-medium transition
                                    {{ $page == $users->currentPage()
                                        ? 'bg-brand-500 text-white'
                                        : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                                {{ $page }}
                            </a>
                        @endforeach

                        @if ($users->hasMorePages())
                            <a href="{{ $users->nextPageUrl() }}" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-300 dark:text-gray-600 cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </x-common.component-card>
    </div>
@endsection
