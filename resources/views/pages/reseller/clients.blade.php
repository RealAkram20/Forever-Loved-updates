@extends('layouts.app')

@section('content')
    <div x-data="{ create: {{ $errors->hasAny(['name', 'email']) ? 'true' : 'false' }} }">
        <x-common.page-header title="Clients"
            desc="The families you're building memorials for. Each one can sign in to view and help finish their memorial.">
            <x-slot:actions>
                <button type="button" @click="create = true" class="btn btn-primary btn-md">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Add client
                </button>
            </x-slot:actions>
        </x-common.page-header>

        <x-common.flash />

        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
            @if ($clients->isEmpty())
                <div class="px-6 py-16 text-center">
                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m6-1.13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm6 1.13A4 4 0 0 0 18 12"/></svg>
                    <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">No clients yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        Add a family and we'll email them an invitation. They can sign in with just their email address — no password to remember at a difficult time.
                    </p>
                    <button type="button" @click="create = true" class="btn btn-primary btn-md mt-5">Add your first client</button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Client</th>
                                <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Memorials</th>
                                <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Added</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($clients as $client)
                                <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-800 dark:text-white/90">{{ $client->name }}</div>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $client->email }}</div>
                                    </td>
                                    {{-- memorials_count, not memorials->count(): the controller scopes the
                                         count to this reseller, and a lazy relation load would both
                                         re-query per row and quietly show memorials held elsewhere. --}}
                                    <td class="px-3 py-4 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $client->memorials_count }}</td>
                                    <td class="px-3 py-4 text-gray-500 dark:text-gray-400">{{ $client->created_at?->format('M j, Y') }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <form action="{{ route('reseller.clients.destroy', $client) }}" method="POST" class="inline"
                                            onsubmit="return confirm('Remove {{ addslashes($client->name) }} from your client list? Their memorials stay published — only the link to your business is removed.')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 transition-colors duration-150 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400">
                                                Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($clients->hasPages())
                    <div class="border-t border-gray-100 dark:border-gray-800 px-6 py-4">{{ $clients->links() }}</div>
                @endif
            @endif
        </div>

        {{-- Adding a client is occasional, so it gets a modal rather than a permanent card
             pushing the list they actually came here to read below the fold. --}}
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
                        <h3 class="text-base font-medium text-gray-800 dark:text-white/90">Add a client</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We'll email them an invitation to sign in.</p>
                    </div>
                    <button type="button" @click="create = false" class="-mr-2 -mt-1 rounded-lg p-2 text-gray-400 transition-colors duration-150 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('reseller.clients.store') }}" method="POST" class="space-y-5 border-t border-gray-100 dark:border-gray-800 p-6">
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
                        <button type="submit" class="btn btn-primary btn-md">Add client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
