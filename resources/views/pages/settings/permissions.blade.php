@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Permissions" />

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-3 text-sm text-red-700 dark:text-red-400">
            {{ session('error') }}
        </div>
    @endif

    <div class="space-y-6">
        {{-- Roles --}}
        <x-common.component-card title="Roles" desc="Manage user roles. System roles (super-admin, admin, user) cannot be deleted.">
            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($roles as $role)
                    <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm">
                        <span class="font-medium text-gray-800 dark:text-white/90">{{ $role->name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">({{ $role->users_count ?? $role->users()->count() }} users)</span>
                        @if (!in_array($role->name, ['super-admin', 'admin', 'user']))
                            <form action="{{ route('settings.roles.destroy', $role) }}" method="POST" class="inline"
                                onsubmit="return confirm('Delete this role?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <form action="{{ route('settings.roles.store') }}" method="POST" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">New Role</label>
                    <input type="text" name="name" placeholder="e.g. editor, moderator"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                    class="btn btn-primary btn-md">
                    Add Role
                </button>
            </form>
        </x-common.component-card>

        {{-- Permissions --}}
        <x-common.component-card title="Permissions" desc="Define permissions, then grant them to roles below. Note: these are for custom authorization you build into your own features — the platform's built-in admin/reseller access is still governed by roles.">
            @if ($permissions->isEmpty())
                <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">No permissions defined yet. Create one below (e.g. <span class="font-mono">memorials.moderate</span>, <span class="font-mono">reports.view</span>).</p>
            @else
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach ($permissions as $permission)
                        <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2 text-sm">
                            <span class="font-mono text-gray-800 dark:text-white/90">{{ $permission->name }}</span>
                            <form action="{{ route('settings.permissions.destroy', $permission) }}" method="POST" class="inline"
                                onsubmit="return confirm('Delete the {{ addslashes($permission->name) }} permission? It will be removed from every role.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 transition" title="Delete permission">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('settings.permissions.store') }}" method="POST" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">New Permission</label>
                    <input type="text" name="name" placeholder="e.g. memorials.moderate"
                        class="h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-md">Add Permission</button>
            </form>
        </x-common.component-card>

        {{-- Grant permissions to roles --}}
        @if ($permissions->isNotEmpty())
            <x-common.component-card title="Role Permissions" desc="Tick the permissions each role should have, then save that role.">
                <div class="space-y-5">
                    @foreach ($roles as $role)
                        @php $rolePerms = $role->permissions->pluck('name'); @endphp
                        <form action="{{ route('settings.roles.permissions', $role) }}" method="POST"
                            class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
                            @csrf @method('PUT')
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $role->name }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $rolePerms->count() }} of {{ $permissions->count() }}</span>
                                </div>
                                <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                            </div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($permissions as $permission)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->name }}"
                                            @checked($rolePerms->contains($permission->name))
                                            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20" />
                                        <span class="font-mono">{{ $permission->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </form>
                    @endforeach
                </div>
            </x-common.component-card>
        @endif

        {{-- User Role Assignment --}}
        <x-common.component-card title="User Roles" desc="Assign roles to users.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Current Role</th>
                            <th class="pb-3 text-left font-medium text-gray-500 dark:text-gray-400">Change Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($users as $user)
                            <tr>
                                <td class="py-3 text-gray-800 dark:text-white/90">{{ $user->name }}</td>
                                <td class="py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                <td class="py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                        {{ $user->hasRole('super-admin') ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' : '' }}
                                        {{ $user->hasRole('admin') ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                        {{ $user->hasRole('user') ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                        {{ $user->hasRole('contributor') ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : '' }}">
                                        {{ $user->roles->pluck('name')->first() ?? 'none' }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <form action="{{ route('settings.users.role', $user) }}" method="POST" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <select name="role"
                                            class="h-9 rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-3 py-1 text-sm text-gray-800 dark:text-white/90 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden">
                                            @foreach ($assignableRoles as $role)
                                                <option value="{{ $role->name }}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                            class="btn btn-secondary btn-sm">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-common.component-card>
    </div>
@endsection
