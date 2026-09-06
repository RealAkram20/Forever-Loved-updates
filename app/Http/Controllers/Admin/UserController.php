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

        // The relay's leftovers: link-shaped or message-length names that own nothing.
        // One definition shared with the bulk delete and the console command, so what the
        // screen shows is exactly what those remove.
        if ($request->boolean('suspicious')) {
            \App\Support\JunkUserPurge::scope($query);
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $roles = Role::orderBy('name')->get();
        $resellers = Reseller::filterOptions();

        return view('pages.users.index', compact('users', 'roles', 'resellers'));
    }

    /**
     * Remove many at once -- the rows ticked on the page, or every row the suspicious
     * filter matches.
     *
     * `scope` mode is deliberately not "delete everything matching the current filters".
     * It is the suspicious filter only. A search box that happens to match a real family's
     * surname must never be one confirm away from cascading their memorial.
     *
     * Every row still passes JunkUserPurge::reasonToSkip, whichever mode. Ticking a box is
     * not permission to delete a memorial owner.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', 'in:ids,scope'],
            'ids' => ['required_if:mode,ids', 'array', 'max:'.\App\Support\JunkUserPurge::WEB_BATCH],
            'ids.*' => ['integer'],
        ]);

        $actor = auth()->user();
        $remaining = null;

        if ($data['mode'] === 'ids') {
            $users = User::with('roles')->whereKey($data['ids'])->get();
            $summary = \App\Support\JunkUserPurge::purge($users, $actor);
        } else {
            // Capped per request so it returns before a proxy times out; the flash says how
            // many are left and points at the command for the rest.
            $users = \App\Support\JunkUserPurge::query()->with('roles')->orderBy('id')->limit(\App\Support\JunkUserPurge::WEB_BATCH)->get();
            $summary = \App\Support\JunkUserPurge::purge($users, $actor);
            $remaining = \App\Support\JunkUserPurge::query()->count() ?: null;
        }

        return redirect()
            ->route('users.index', $request->only(['search', 'role', 'reseller', 'suspicious']))
            ->with('success', \App\Support\JunkUserPurge::describe($summary, $remaining));
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
