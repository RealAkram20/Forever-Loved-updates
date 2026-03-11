@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />
    <div class="space-y-6">
        <x-common.component-card :title="$title">
            @if (session('status'))
                <div class="p-4 mb-4 rounded-lg bg-green-50 text-green-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="p-4 mb-4 rounded-lg bg-red-50 text-red-800">{{ session('error') }}</div>
            @endif
            <div class="flex justify-end mb-4">
                <a href="{{ route('memorials.create') }}"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                    Create Memorial
                </a>
            </div>

            @if ($memorials->isEmpty())
                <p class="py-8 text-center text-gray-500">
                    {{ $isAdmin ? 'No memorials yet.' : 'No memorials yet. Create your first memorial to get started.' }}
                </p>
            @elseif ($isAdmin)
                {{-- Admin table: Memorial, Manager, Contributors, Status, Visitors, Actions --}}
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <div class="max-w-full overflow-x-auto custom-scrollbar">
                        <table class="w-full min-w-[1102px]">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs">Memorial</p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs">Manager</p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs">Contributors</p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs">Status</p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs">Visitors</p>
                                    </th>
                                    <th class="px-5 py-3 text-left sm:px-6">
                                        <p class="font-medium text-gray-500 text-theme-xs">Actions</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($memorials as $memorial)
                                    @php
                                        $deceasedImage = $memorial->profile_photo_path ?? $memorial->media->first()?->path;
                                        $contributors = $memorial->tributes->whereNotNull('user_id')->pluck('user')->filter()->unique('id')->take(5);
                                        $statusLabel = match($memorial->status ?? 'active') {
                                            'deactivated' => 'Deactivated',
                                            'suspended' => 'Suspended',
                                            default => 'Active',
                                        };
                                        $statusClass = match($memorial->status ?? 'active') {
                                            'deactivated' => 'bg-gray-50 text-gray-700',
                                            'suspended' => 'bg-red-50 text-red-700',
                                            default => 'bg-green-50 text-green-700',
                                        };
                                    @endphp
                                    <tr class="border-b border-gray-100">
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-200">
                                                    @if ($deceasedImage)
                                                        <img src="{{ \App\Helpers\StorageHelper::publicUrl($deceasedImage) }}" alt="{{ $memorial->full_name }}" class="h-full w-full object-cover" />
                                                    @else
                                                        <div class="flex h-full w-full items-center justify-center text-sm font-medium text-gray-500">
                                                            {{ strtoupper(substr($memorial->full_name, 0, 1)) }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <span class="block font-medium text-gray-800 text-theme-sm">{{ $memorial->full_name }}</span>
                                                    <span class="block text-gray-500 text-theme-xs">
                                                        @php $designation = $memorial->designation ?? $memorial->cause_of_death; @endphp
                                                        @if ($designation && $memorial->birth_death_years)
                                                            {{ $designation }} · {{ $memorial->birth_death_years }}
                                                        @else
                                                            {{ $designation ?? $memorial->birth_death_years ?? '—' }}
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <p class="text-gray-500 text-theme-sm">{{ $memorial->owner?->name ?? '—' }}</p>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex -space-x-2">
                                                @forelse ($contributors as $contributor)
                                                    <div class="h-6 w-6 overflow-hidden rounded-full border-2 border-white bg-gray-200">
                                                        <div class="flex h-full w-full items-center justify-center text-[10px] font-medium text-gray-500">
                                                            {{ strtoupper(substr($contributor->name ?? '?', 0, 1)) }}
                                                        </div>
                                                    </div>
                                                @empty
                                                    <span class="text-theme-xs text-gray-400">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <span class="inline-block rounded-full px-2 py-0.5 text-theme-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <p class="text-gray-500 text-theme-sm">{{ number_format($memorial->visitor_count ?? 0) }}</p>
                                        </td>
                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('memorial.public', $memorial->slug) }}" target="_blank" title="View" class="p-1.5 text-brand-500 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors">
                                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </a>
                                                <a href="{{ route('memorials.edit', $memorial) }}" title="Edit" class="p-1.5 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                                    <button type="button" @click="open = !open" class="text-gray-500 hover:text-gray-700 p-1">
                                                        <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.99902 10.245C6.96552 10.245 7.74902 11.0285 7.74902 11.995V12.005C7.74902 12.9715 6.96552 13.755 5.99902 13.755C5.03253 13.755 4.24902 12.9715 4.24902 12.005V11.995C4.24902 11.0285 5.03253 10.245 5.99902 10.245ZM17.999 10.245C18.9655 10.245 19.749 11.0285 19.749 11.995V12.005C19.749 12.9715 18.9655 13.755 17.999 13.755C17.0325 13.755 16.249 12.9715 16.249 12.005V11.995C16.249 11.0285 17.0325 10.245 17.999 10.245ZM13.749 11.995C13.749 11.0285 12.9655 10.245 11.999 10.245C11.0325 10.245 10.249 11.0285 10.249 11.995V12.005C10.249 12.9715 11.0325 13.755 11.999 13.755C12.9655 13.755 13.749 12.9715 13.749 12.005V11.995Z" fill="currentColor" />
                                                        </svg>
                                                    </button>
                                                    <div x-show="open" x-cloak class="absolute right-0 z-50 mt-1 w-40 rounded-2xl border border-gray-200 bg-white p-2 shadow-lg">
                                                        <form method="POST" action="{{ route('memorials.status', $memorial) }}" class="block">
                                                            @csrf
                                                            <input type="hidden" name="action" value="deactivate" />
                                                            <button type="submit" class="flex w-full px-3 py-2 text-left text-theme-xs font-medium text-gray-500 rounded-lg hover:bg-gray-100 hover:text-gray-700">Deactivate</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('memorials.status', $memorial) }}" class="block">
                                                            @csrf
                                                            <input type="hidden" name="action" value="suspend" />
                                                            <button type="submit" class="flex w-full px-3 py-2 text-left text-theme-xs font-medium text-gray-500 rounded-lg hover:bg-gray-100 hover:text-gray-700">Suspend</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('memorials.status', $memorial) }}" class="block" onsubmit="return confirm('Are you sure you want to delete this memorial?');">
                                                            @csrf
                                                            <input type="hidden" name="action" value="delete" />
                                                            <button type="submit" class="flex w-full px-3 py-2 text-left text-theme-xs font-medium text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                {{-- User table: Full Name, Status, Theme, Plan, Visibility, Actions (no title) --}}
                <div class="-mx-4 overflow-x-auto custom-scrollbar sm:mx-0 px-4 sm:px-0" style="-webkit-overflow-scrolling: touch;">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-[640px] w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-700 sm:px-5">Name</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-700 sm:px-5">Progress</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-700 sm:px-5">Theme</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-700 sm:px-5">Plan</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-700 sm:px-5">Visibility</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-700 sm:px-5">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($memorials as $memorial)
                                    @php
                                        $completionPercent = $memorial->completion_percentage;
                                        $planLabel = ($memorial->plan ?? 'free') === 'paid' ? 'Paid' : 'Free';
                                        $planClass = ($memorial->plan ?? 'free') === 'paid' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <tr class="border-b border-gray-100">
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-800 sm:px-5">{{ $memorial->full_name }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 sm:px-5">
                                            <div class="flex items-center gap-2 min-w-[100px]">
                                                <div class="flex-1 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                                    <div class="h-full rounded-full bg-brand-500" style="width: {{ $completionPercent }}%"></div>
                                                </div>
                                                <span class="text-xs font-medium text-gray-600 shrink-0">{{ $completionPercent }}%</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 sm:px-5">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $memorial->theme === 'premium' ? 'bg-brand-100 text-brand-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ \App\Models\Memorial::getThemeDisplayName($memorial->theme) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 sm:px-5">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $planClass }}">
                                                {{ $planLabel }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-800 sm:px-5">{{ $memorial->is_public ? 'Public' : 'Private' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 sm:px-5">
                                            <div class="flex flex-nowrap items-center gap-2">
                                                <a href="{{ route('memorial.public', $memorial->slug) }}" title="View" class="p-1.5 text-brand-500 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors">
                                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                </a>
                                                <a href="{{ route('memorials.edit', $memorial) }}" title="Edit" class="p-1.5 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                                                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </a>
                                                <form method="POST" action="{{ route('memorials.destroy', $memorial) }}" class="inline shrink-0" onsubmit="return confirm('Are you sure?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Delete" class="p-1.5 text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                                                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if ($memorials->isNotEmpty())
                <div class="mt-4">
                    {{ $memorials->links() }}
                </div>
            @endif
        </x-common.component-card>
    </div>
@endsection
