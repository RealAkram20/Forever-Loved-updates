@extends('layouts.app')

@section('content')
    <div x-data="{ create: {{ $errors->hasAny(['name', 'email']) ? 'true' : 'false' }} }">
        <x-common.page-header title="Staff"
            desc="People who help run your reseller account. Staff get the same dashboard you do, across all your memorials and clients.">
            <x-slot:actions>
                <button type="button" @click="create = true" class="btn btn-primary btn-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Invite staff
                </button>
            </x-slot:actions>
        </x-common.page-header>

        <x-common.flash />

        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Member</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Role</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Added</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($staff as $member)
                            <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $member->name }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</div>
                                </td>
                                <td class="px-3 py-4">
                                    @if ($member->id === $ownerId)
                                        <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-600 ring-1 ring-inset ring-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">Owner</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Staff</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-gray-500 dark:text-gray-400">{{ $member->created_at?->format('M j, Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if ($member->id !== $ownerId)
                                        <form action="{{ route('reseller.staff.destroy', $member) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Remove {{ addslashes($member->name) }} from your team? They lose access to your reseller account immediately.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400">
                                                Remove
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-600">You</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Inviting staff is occasional, so it gets a modal rather than a permanent card. --}}
        <div x-show="create" x-cloak @keydown.escape.window="create = false" class="fixed inset-0 z-99999 flex items-start justify-center overflow-y-auto p-4 sm:p-6">
            <div x-show="create" x-transition:enter="transition-opacity duration-200 ease-out" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity duration-150 ease-out" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                @click="create = false" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

            <div x-show="create"
                x-transition:enter="transition duration-200 ease-[cubic-bezier(0.23,1,0.32,1)]"
                x-transition:enter-start="opacity-0 scale-[0.97]" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition duration-150 ease-out"
                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-[0.98]"
                class="relative my-auto w-full max-w-lg rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xl">

                <div class="flex items-start justify-between gap-4 px-6 py-5">
                    <div>
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Invite a team member</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">They'll get full access to your reseller dashboard. We'll email them an invitation to sign in.</p>
                    </div>
                    <button type="button" @click="create = false" class="-mr-2 -mt-1 rounded-lg p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('reseller.staff.store') }}" method="POST" class="space-y-5 border-t border-gray-100 dark:border-gray-800 p-6">
                    @csrf
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required autofocus
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="create = false" class="btn btn-secondary btn-md">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-md">Send invite</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
