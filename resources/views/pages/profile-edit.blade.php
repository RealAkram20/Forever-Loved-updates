@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Edit Profile" />

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Profile Overview with Photo Upload --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5 lg:p-6"
             x-data="{
                 previewUrl: {{ $user->profile_photo_url ? "'" . $user->profile_photo_url . "'" : 'null' }},
                 fileChosen(event) {
                     const file = event.target.files[0];
                     if (file) {
                         this.previewUrl = URL.createObjectURL(file);
                     }
                 }
             }">
            <div class="flex flex-col items-center gap-5 sm:flex-row">
                <div class="relative group shrink-0">
                    <div class="h-24 w-24 overflow-hidden rounded-full bg-brand-100 dark:bg-brand-500/20 flex items-center justify-center">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Profile Photo" class="h-full w-full object-cover" />
                        </template>
                        <template x-if="!previewUrl">
                            <span class="text-3xl font-bold text-brand-600 dark:text-brand-400">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </template>
                    </div>

                    <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data"
                          id="photoForm" class="absolute inset-0">
                        @csrf
                        <label for="profile_photo"
                            class="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/0 group-hover:bg-black/40 transition-all duration-200">
                            <svg class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </label>
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="fileChosen($event); $nextTick(() => document.getElementById('photoForm').submit())" />
                    </form>
                </div>

                <div class="text-center sm:text-left flex-1">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $user->name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                    <div class="mt-1 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        @foreach ($user->roles as $role)
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                @if($role->name === 'super-admin') bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400
                                @elseif($role->name === 'admin') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400
                                @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
                                @endif">
                                {{ ucfirst($role->name) }}
                            </span>
                        @endforeach
                        <span class="text-xs text-gray-400 dark:text-gray-500">Joined {{ $user->created_at->format('M d, Y') }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-center sm:justify-start gap-2">
                        <label for="profile_photo"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ $user->profile_photo ? 'Change Photo' : 'Upload Photo' }}
                        </label>

                        @if ($user->profile_photo)
                            <form method="POST" action="{{ route('profile.photo.remove') }}" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Remove
                                </button>
                            </form>
                        @endif
                    </div>

                    @error('profile_photo')
                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">JPG, PNG or WebP. Max 2MB.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Update Profile --}}
            <x-common.component-card title="Profile Information" desc="Update your name and email address.">
                <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
                    @csrf @method('PUT')

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                        @error('email')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-1">
                        <button type="submit"
                            class="h-11 rounded-lg bg-brand-500 px-6 text-sm font-medium text-white hover:bg-brand-600 transition">
                            Save Changes
                        </button>
                    </div>
                </form>
            </x-common.component-card>

            {{-- Update Password --}}
            <x-common.component-card title="Update Password" desc="Use a strong password to protect your account.">
                <form method="POST" action="{{ route('profile.password') }}" class="space-y-5">
                    @csrf @method('PUT')

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                        <input type="password" name="current_password" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden"
                            placeholder="Enter current password" />
                        @error('current_password')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                        <input type="password" name="password" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden"
                            placeholder="Minimum 8 characters" />
                        @error('password')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                        <input type="password" name="password_confirmation" required
                            class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden"
                            placeholder="Repeat new password" />
                    </div>

                    <div class="pt-1">
                        <button type="submit"
                            class="h-11 rounded-lg bg-brand-500 px-6 text-sm font-medium text-white hover:bg-brand-600 transition">
                            Update Password
                        </button>
                    </div>
                </form>
            </x-common.component-card>
        </div>

        {{-- Account Stats --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-5 lg:p-6">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90 mb-4">Account Overview</h3>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $user->memorials()->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Memorials</p>
                </div>
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $user->tributes()->count() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tributes</p>
                </div>
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $user->roles->pluck('name')->first() ? ucfirst($user->roles->pluck('name')->first()) : 'User' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Role</p>
                </div>
                <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4 text-center">
                    <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $user->created_at->diffForHumans(null, true) }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Member for</p>
                </div>
            </div>
        </div>

        {{-- Delete Account --}}
        <div class="rounded-2xl border border-red-200 dark:border-red-900/50 bg-white dark:bg-white/[0.03] p-5 lg:p-6" x-data="{ confirmDelete: false }">
            <h3 class="text-base font-medium text-red-600 dark:text-red-400 mb-1">Danger Zone</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Once your account is deleted, all of its resources and data will be permanently removed.</p>

            <button @click="confirmDelete = true" x-show="!confirmDelete"
                class="h-10 rounded-lg border border-red-300 dark:border-red-700 px-5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                Delete Account
            </button>

            <form method="POST" action="{{ route('profile.destroy') }}" x-show="confirmDelete" x-cloak class="space-y-4">
                @csrf @method('DELETE')
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm your password to delete</label>
                    <input type="password" name="password" required
                        class="h-11 w-full max-w-sm rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-red-300 focus:ring-3 focus:ring-red-500/10 focus:outline-hidden"
                        placeholder="Enter your password" />
                    @error('password', 'userDeletion')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit"
                        class="h-10 rounded-lg bg-red-600 px-5 text-sm font-medium text-white hover:bg-red-700 transition">
                        Permanently Delete
                    </button>
                    <button type="button" @click="confirmDelete = false"
                        class="h-10 rounded-lg border border-gray-300 dark:border-gray-700 px-5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
