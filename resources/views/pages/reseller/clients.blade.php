@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Clients" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</div>
    @endif

    <div class="space-y-6">
        <x-common.component-card title="Your Clients">
            @if ($clients->isEmpty())
                <div class="flex flex-col items-center py-12 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-500/10 text-blue-500">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8zm6 1.13A4 4 0 0018 12"/></svg>
                    </div>
                    <h4 class="mt-4 text-sm font-semibold text-gray-800 dark:text-white/90">No clients yet</h4>
                    <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">Add the families you're building memorials for. They'll be able to log in and help finish the memorial too.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                                <th class="py-2 pr-4 font-medium">Name</th>
                                <th class="py-2 pr-4 font-medium">Email</th>
                                <th class="py-2 pr-4 font-medium">Memorials</th>
                                <th class="py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $client)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pr-4 text-gray-800 dark:text-white/90">{{ $client->name }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $client->email }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $client->memorials->count() }}</td>
                                    <td class="py-2 text-right">
                                        <form action="{{ route('reseller.clients.destroy', $client) }}" method="POST" onsubmit="return confirm('Remove this client from your account?')" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-500 hover:underline text-xs">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $clients->links() }}</div>
            @endif
        </x-common.component-card>

        <x-common.component-card title="Add a Client" desc="Onboard a client manually. They can be invited to a memorial you create for them.">
            <form action="{{ route('reseller.clients.store') }}" method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn btn-primary btn-md">Add Client</button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
