<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * The reseller's own team — staff who administer the reseller account itself (the 'reseller'
 * role), as opposed to the families they serve (ClientController, the 'user' role). Staff get
 * the same dashboard the owner does, so onboarding one is handing over the keys to the whole
 * reseller account; only the owner may do it, which also stops a staff member from minting
 * more staff or removing the owner.
 */
class StaffController extends Controller
{
    private function ownerOrAbort(Request $request): User
    {
        $user = $request->user();
        $reseller = $user->reseller;

        abort_unless($reseller && $user->id === $reseller->owner_user_id, 403);

        return $user;
    }

    public function index(Request $request)
    {
        $owner = $this->ownerOrAbort($request);

        $staff = User::where('reseller_id', $owner->reseller_id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'reseller'))
            ->latest()
            ->get();

        return view('pages.reseller.staff', [
            'title' => 'Staff',
            'staff' => $staff,
            'ownerId' => $owner->reseller->owner_user_id,
        ]);
    }

    public function store(Request $request)
    {
        $owner = $this->ownerOrAbort($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(str()->random(32)),
            'reseller_id' => $owner->reseller_id,
            'original_reseller_id' => $owner->reseller_id,
        ]);

        $staff->assignRole('reseller');

        NotificationService::notifyAccountInvite($staff, $owner->reseller->name);

        return back()->with('success', NotificationService::emailConfigured()
            ? "Invited {$staff->email} to your team."
            : "Added {$staff->name} to your team — but no invitation was sent, because outgoing email is not configured yet.");
    }

    public function destroy(Request $request, User $user)
    {
        $owner = $this->ownerOrAbort($request);

        // Only a staff member of this same tenant, and never the owner themselves.
        abort_unless(
            $user->reseller_id === $owner->reseller_id
                && $user->hasRole('reseller')
                && $user->id !== $owner->reseller->owner_user_id,
            403
        );

        $name = $user->name;
        $user->removeRole('reseller');
        $user->update(['reseller_id' => null]);

        return back()->with('success', "\"{$name}\" removed from your team.");
    }
}
