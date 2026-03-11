@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0">
    <div class="relative flex min-h-screen w-full flex-col justify-center py-12 sm:p-0 lg:flex-row">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto w-full max-w-lg px-6 pt-10 lg:px-12">
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 5 of 5</span>
                        <span class="text-sm text-gray-500">Complete setup</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">Complete your memorial</h1>
                    <p class="mb-6 text-sm text-gray-500">Add a biography and theme. You can add more details later from your dashboard.</p>

                    @if ($errors->any())
                        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-600">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('memorial.create.complete') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700" for="biography">Biography (optional)</label>
                            <textarea id="biography" name="biography" rows="5" placeholder="Share the story of your loved one..."
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden">{{ old('biography') }}</textarea>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700">Theme</label>
                            <div class="relative z-20 bg-transparent">
                                <select name="theme"
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-11 text-sm text-gray-800 focus:ring-3 focus:outline-hidden">
                                    <option value="free" {{ old('theme', 'free') === 'free' ? 'selected' : '' }}>Free</option>
                                    <option value="premium" {{ old('theme') === 'premium' ? 'selected' : '' }}>Premium</option>
                                </select>
                                <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500">
                                    <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </span>
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                            Create Memorial
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="bg-brand-950 relative hidden min-h-[40vh] w-full lg:flex lg:min-h-screen lg:w-1/2">
            <x-common.common-grid-shape />
            <div class="z-1 flex flex-col items-center justify-center px-8">
                <p class="max-w-sm text-center text-lg font-medium leading-relaxed text-white/90">
                    Step 5: Final setup
                </p>
                <p class="mt-2 text-center text-sm text-white/70">
                    In memory of {{ $data['first_name'] ?? '' }} {{ $data['last_name'] ?? '' }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
