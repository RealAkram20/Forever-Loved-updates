@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0">
    <div class="relative flex min-h-screen w-full flex-col justify-center py-12 sm:p-0">
        <div class="flex w-full flex-1 flex-col">
            <div class="mx-auto w-full max-w-2xl px-6 pt-10 lg:px-12">
                <x-memorial-signup.step-tabs :currentStep="3" />
                <a href="{{ auth()->user() ? route('memorial.create.step1') : route('memorial.create.step2') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                    <svg class="stroke-current" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Back
                </a>
                <div class="mt-8">
                    <div class="mb-6 flex items-center gap-2">
                        <span class="rounded-full bg-brand-500 px-3 py-1 text-xs font-medium text-white">Step 3 of 3</span>
                        <span class="text-sm text-gray-500">Choose plan</span>
                    </div>
                    <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800">Choose your plan</h1>
                    <p class="mb-6 text-sm text-gray-500">Select a plan that fits your needs. You can change this later.</p>

                    <form method="POST" action="{{ route('memorial.create.storeStep3') }}" class="space-y-4">
                        @csrf
                        @foreach ($plans as $plan)
                            <label class="block cursor-pointer">
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ old('plan_id', $data['plan_id'] ?? '') == $plan->id ? 'checked' : '' }}
                                    class="peer sr-only" />
                                <div class="rounded-lg border-2 border-gray-200 p-4 transition peer-checked:border-brand-500 peer-checked:bg-brand-50/50 hover:border-gray-300">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $plan->name }}</p>
                                            <p class="text-sm text-gray-600">{{ $plan->description }}</p>
                                            <p class="mt-1 text-sm text-gray-500">
                                                {{ $plan->memorial_limit }} memorial(s) · {{ $plan->storage_limit_mb }} MB storage
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            @if ($plan->isFree())
                                                <p class="text-lg font-semibold text-gray-900">Free</p>
                                            @else
                                                <p class="text-lg font-semibold text-gray-900">${{ number_format($plan->price, 2) }}</p>
                                                <p class="text-xs text-gray-500">/{{ $plan->interval }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </label>
                        @endforeach
                        @error('plan_id')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="w-full rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600">
                            Continue
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
