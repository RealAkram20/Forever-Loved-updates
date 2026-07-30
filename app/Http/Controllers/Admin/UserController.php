<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\User;
use App\Support\ProtectedRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles', 'reseller');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        Reseller::applyFilter($query, $request->input('reseller'));

        $users = $query->latest()->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $resellers = Reseller::filterOptions();

        return view('pages.users.index', compact('users', 'roles', 'resellers'));
    }

    public function create()
    {
        $roles = ProtectedRoles::assignableQuery(auth()->user())->orderBy('name')->get();

        return view('pages.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', 'exists:roles,name'],
        ]);

        // `exists:roles,name` says the role is real, not that this admin may grant it.
        ProtectedRoles::guardAssignment($request->user(), $validated['role']);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" created successfully.");
    }

    public function edit(User $user)
    {
        ProtectedRoles::guardTarget(auth()->user(), $user);

        $roles = ProtectedRoles::assignableQuery(auth()->user())->orderBy('name')->get();

        return view('pages.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        // Both directions: an admin may not promote anyone *into* super-admin, and may not
        // touch someone who already is one — a password reset on that account would be a
        // sign-in as them, which is the same escalation by another route.
        ProtectedRoles::guardTarget($request->user(), $user);

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'string', 'exists:roles,name'],
        ]);

        ProtectedRoles::guardAssignment($request->user(), $validated['role']);

        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" updated successfully.");
    }

    public function destroy(User $user)
    {
        ProtectedRoles::guardTarget(auth()->user(), $user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "User \"{$name}\" deleted successfully.");
    }
}
