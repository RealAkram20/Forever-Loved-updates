<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
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
            // Passwordless, like every other invited account here: the invite sends a
            // login code, and nobody is ever told a password. A random hash instead
            // only meant the account had a secret that could not be used or reset by
            // anyone, which is the same thing dressed up as different.
            'password' => null,
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

    /**
     * One client and the memorials this reseller holds for them. Exists because the
     * roster could only ever show a count — answering "which memorials?" meant leaving
     * for the tenant-wide list and searching by name.
     */
    public function show(Request $request, User $user)
    {
        $this->authorizeClient($request, $user);

        $resellerId = $request->user()->reseller_id;

        return view('pages.reseller.client-show', [
            'title' => $user->name,
            'client' => $user,
            // Scoped: a client may also own memorials under a previous reseller or
            // directly on the platform, and those are not this reseller's to show.
            'memorials' => $user->memorials()
                ->where('reseller_id', $resellerId)
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeClient($request, $user);

        // Its own error bag: the roster hosts both an "add client" and an "edit client"
        // form, and a shared bag would light up the wrong one — and refill it with the
        // other form's rejected input.
        $validated = $request->validateWithBag('updateClient', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return back()->with('success', "Client \"{$user->name}\" updated.");
    }

    /**
     * Send the invitation again — the common case being that it was created before SMTP
     * worked, or the family never found the first one.
     */
    public function resendInvite(Request $request, User $user)
    {
        $this->authorizeClient($request, $user);

        NotificationService::notifyAccountInvite($user, $request->user()->reseller->name);

        return back()->with('success', NotificationService::emailConfigured()
            ? "Invitation resent to {$user->email}."
            : "Nothing was sent to {$user->email} — outgoing email is not configured yet.");
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeClient($request, $user);

        $name = $user->name;
        $user->update(['reseller_id' => null]);

        return back()->with('success', "\"{$name}\" removed from your client list.");
    }
}
