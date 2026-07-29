<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Reseller staff manage the end-clients under their own tenant (users with a matching
 * reseller_id and the 'user' role, onboarded by the reseller rather than self-registered
 * — self-serve client registration is out of scope for Phase 1).
 */
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = User::where('reseller_id', $request->user()->reseller_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'user'))
            ->with('memorials')
            ->latest()
            ->paginate(15);

        return view('pages.reseller.clients', [
            'title' => 'Clients',
            'clients' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $resellerId = $request->user()->reseller_id;

        $client = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(str()->random(32)),
            'reseller_id' => $resellerId,
            'original_reseller_id' => $resellerId,
        ]);

        $client->assignRole('user');

        return back()->with('success', "Client \"{$client->name}\" added.");
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->reseller_id === $request->user()->reseller_id, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return back()->with('success', "Client \"{$user->name}\" updated.");
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($user->reseller_id === $request->user()->reseller_id, 403);

        $name = $user->name;
        $user->update(['reseller_id' => null]);

        return back()->with('success', "\"{$name}\" removed from your client list.");
    }
}
