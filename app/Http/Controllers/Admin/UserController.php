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

        // How many accounts the suspicious definition matches, site-wide, so the page can say so
        // without anyone having to know the filter exists. Cached for a minute: it is a REGEXP
        // scan of the users table and this page is opened often; a minute of staleness on a
        // number that only ever falls is fine. Busted below the moment a purge is started.
        $suspiciousCount = \Illuminate\Support\Facades\Cache::remember('users.suspicious_count', 60, fn () => \App\Support\JunkUserPurge::query()->count());

        return view('pages.users.index', compact('users', 'roles', 'resellers', 'suspiciousCount'));
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
            'mode' => ['required', 'in:ids,scope,all'],
            'ids' => ['required_if:mode,ids', 'array', 'max:'.\App\Support\JunkUserPurge::WEB_BATCH],
            'ids.*' => ['integer'],
        ]);

        $actor = auth()->user();
        $remaining = null;

        \Illuminate\Support\Facades\Cache::forget('users.suspicious_count');

        // `all` hands the whole set to a job rather than deleting in the request. The
        // 500-per-click cap protected the request, not the admin; an attack that left
        // thousands of rows should be one click to undo, not dozens. Same definition,
        // same refusals — only where the work runs changes.
        if ($data['mode'] === 'all') {
            $count = \App\Support\JunkUserPurge::query()->count();

            if ($count === 0) {
                return redirect()->route('users.index', ['suspicious' => 1])->with('success', 'No suspicious accounts to remove.');
            }

            \App\Support\ReliableDispatch::dispatch(new \App\Jobs\PurgeSuspiciousUsersJob);

            return redirect()->route('users.index', ['suspicious' => 1])->with('success', sprintf(
                'Removing %s suspicious %s in the background. Memorial owners, payers and staff are skipped. Refresh this filter in a minute to watch it drain.',
                number_format($count),
                $count === 1 ? 'account' : 'accounts'
            ));
        }

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
