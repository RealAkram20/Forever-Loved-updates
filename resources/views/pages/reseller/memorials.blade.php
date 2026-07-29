@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Client Memorials" />

    <x-common.component-card title="Memorials" desc="Memorials created under your account.">
        @if (! $memorials->isEmpty())
            <div class="mb-4">
                <a href="{{ route('reseller.memorials.create') }}" class="btn btn-primary btn-sm">Create Memorial for a Client</a>
            </div>
        @endif

        @if ($memorials->isEmpty())
            <div class="flex flex-col items-center py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 dark:bg-brand-500/10 text-brand-500">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h4 class="mt-4 text-sm font-semibold text-gray-800 dark:text-white/90">No memorials yet</h4>
                <p class="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">Memorials you build for clients — or invite clients to finish themselves — show up here, branded on your subdomain.</p>
                <a href="{{ route('reseller.memorials.create') }}" class="btn btn-primary btn-sm mt-4">Create your first memorial</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400">
                            <th class="py-2 pr-4 font-medium">Name</th>
                            <th class="py-2 pr-4 font-medium">Client</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Created</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($memorials as $memorial)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 text-gray-800 dark:text-white/90">{{ $memorial->full_name }}</td>
                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $memorial->owner?->name ?? '—' }}</td>
                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ ucfirst($memorial->status) }}</td>
                                <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $memorial->created_at->format('M j, Y') }}</td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('memorial.public', $memorial->slug) }}" target="_blank" class="text-brand-500 hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $memorials->links() }}</div>
        @endif
    </x-common.component-card>
@endsection
