<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
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
        $resellerId = $request->user()->reseller_id;

        $clients = User::where('reseller_id', $resellerId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'user'))
            // Counted rather than loaded, and scoped: a client may also own a direct
            // platform memorial or one under a previous reseller, and those are none of
            // this reseller's business.
            ->withCount(['memorials' => fn ($q) => $q->where('reseller_id', $resellerId)])
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

        NotificationService::notifyAccountInvite($client, $request->user()->reseller->name);

        // Said plainly rather than claimed: the page promises the client can log in, and
        // without outgoing mail configured they have no way to learn the account exists.
        return back()->with('success', NotificationService::emailConfigured()
            ? "Client \"{$client->name}\" added and invited by email."
            : "Client \"{$client->name}\" added — but no invitation was sent, because outgoing email is not configured yet.");
    }

    /**
     * A client is a 'user'-role account inside this tenant. Checking the role as well as
     * the tenant matters: without it, any staff member could rename or change the email of
     * the reseller *owner* account, and an email change plus a password reset is a
     * complete account takeover.
     */
    private function authorizeClient(Request $request, User $user): void
    {
        abort_unless(
            $user->reseller_id === $request->user()->reseller_id && $user->hasRole('user'),
            403
        );
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeClient($request, $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return back()->with('success', "Client \"{$user->name}\" updated.");
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeClient($request, $user);

        $name = $user->name;
        $user->update(['reseller_id' => null]);

        return back()->with('success', "\"{$name}\" removed from your client list.");
    }
}
