@extends('layouts.app')

@section('content')
    <x-common.page-header title="Memorials"
        desc="Memorials hosted under your business, published at your own address.">
        <x-slot:actions>
            <a href="{{ route('reseller.memorials.create') }}" class="btn btn-primary btn-md">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Create memorial
            </a>
        </x-slot:actions>
    </x-common.page-header>

    <x-common.flash />

    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03]">
        @if ($memorials->isEmpty())
            <div class="px-6 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6.25v13M12 6.25C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.48 5.75 18 7.5 18s3.33.48 4.5 1.25M12 6.25C13.17 5.48 14.75 5 16.5 5S19.83 5.48 21 6.25v13C19.83 18.48 18.25 18 16.5 18s-3.33.48-4.5 1.25"/></svg>
                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">No memorials yet</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Build one for a family — or invite them to finish it themselves. Either way it's published under your brand, not ours.
                </p>
                <a href="{{ route('reseller.memorials.create') }}" class="btn btn-primary btn-md mt-5">Create your first memorial</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Memorial</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Family</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Status</th>
                            <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($memorials as $memorial)
                            <tr class="transition-colors duration-150 hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800 dark:text-white/90">{{ $memorial->full_name }}</div>
                                    {{-- The address they can actually hand a family — their own,
                                         not the platform's. --}}
                                    <div class="mt-0.5 truncate font-mono text-xs text-gray-400 dark:text-gray-500">
                                        {{ parse_url($memorial->publicUrl(), PHP_URL_HOST) }}/{{ $memorial->slug }}
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-gray-600 dark:text-gray-400">{{ $memorial->owner?->name ?? '—' }}</td>
                                <td class="px-3 py-4">
                                    <x-admin.status-badge :status="$memorial->status ?? 'active'" />
                                </td>
                                <td class="px-3 py-4 text-gray-500 dark:text-gray-400">{{ $memorial->created_at?->format('M j, Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    {{-- The report this reseller hands to the family. Placed
                                         next to View because it is about this one memorial,
                                         not the tenant-wide tables under Reports. --}}
                                    <a href="{{ route('memorials.report', $memorial->slug) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-500 transition-[transform,background-color,color] duration-150 ease-out hover:bg-gray-100 hover:text-gray-700 active:scale-[0.97] dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-gray-200">
                                        Report
                                    </a>
                                    <a href="{{ $memorial->publicUrl() }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-600 transition-colors duration-150 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10">
                                        View
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($memorials->hasPages())
                <div class="border-t border-gray-100 dark:border-gray-800 px-6 py-4">{{ $memorials->links() }}</div>
            @endif
        @endif
    </div>
@endsection
