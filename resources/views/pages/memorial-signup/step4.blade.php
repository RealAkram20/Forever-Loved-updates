@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0">
    <div class="relative flex min-h-screen w-full flex-col justify-center py-12 sm:p-0 lg:flex-row">
        <div class="flex w-full flex-1 flex-col lg:w-1/2">
            <div class="mx-auto w-full max-w-lg px-6 pt-10 lg:px-12">
                <a href="{{ route('memorial.create.step3') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 4 of 5</span>
                        <span class="text-sm text-gray-500">Privacy</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">Privacy options</h1>
                    <p class="mb-6 text-sm text-gray-500">Would you like to share your memorial with others or keep it private? (This can be changed later.)</p>

                    <form method="POST" action="{{ route('memorial.create.storeStep4') }}" class="space-y-4">
                        @csrf
                        <label class="block cursor-pointer">
                            <input type="radio" name="is_public" value="1" {{ old('is_public', $data['is_public'] ?? 1) == 1 ? 'checked' : '' }}
                                class="peer sr-only" />
                            <div class="rounded-lg border-2 border-gray-200 p-4 transition peer-checked:border-brand-500 peer-checked:bg-brand-50/50 hover:border-gray-300">
                                <p class="font-medium text-gray-900">All visitors can view and contribute.</p>
                                <p class="mt-1 text-sm text-gray-600">This option allows easy access to the website and facilitates collaboration. Recommended for most memorials.</p>
                            </div>
                        </label>
                        <label class="block cursor-pointer">
                            <input type="radio" name="is_public" value="0" {{ old('is_public', $data['is_public'] ?? 1) == 0 ? 'checked' : '' }}
                                class="peer sr-only" />
                            <div class="rounded-lg border-2 border-gray-200 p-4 transition peer-checked:border-brand-500 peer-checked:bg-brand-50/50 hover:border-gray-300">
                                <p class="font-medium text-gray-900">Visible only to me.</p>
                                <p class="mt-1 text-sm text-gray-600">Choose this option if you do not want the memorial to be visible to others at this time.</p>
                            </div>
                        </label>
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                            Continue
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="bg-brand-950 relative hidden min-h-[40vh] w-full lg:flex lg:min-h-screen lg:w-1/2">
            <x-common.common-grid-shape />
            <div class="z-1 flex flex-col items-center justify-center px-8">
                <p class="max-w-sm text-center text-lg font-medium leading-relaxed text-white/90">
                    Step 4: Set privacy
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
