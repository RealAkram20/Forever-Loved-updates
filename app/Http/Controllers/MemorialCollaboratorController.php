<?php

namespace App\Http\Controllers;

use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Models\MemorialCollaborator;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The team of a single memorial — the people invited to help manage it. This is the write
 * side of a feature the schema and read side already shipped: memorial_collaborators has
 * existed since March, Memorial::canBeEditedBy() already grants accepted 'editor'
 * collaborators, and plans carry a feature_advanced_privacy flag literally described as
 * "invite collaborators to manage memorials" — but nothing ever created a collaborator.
 *
 * An invited editor can manage exactly the memorials they are invited to, the same way an
 * admin can, and can still own and create their own memorials (they are a normal 'user').
 * A reseller creating a memorial for a family and inviting a family member to run it is the
 * headline case, so a new invitee is scoped to the memorial's reseller.
 */
class MemorialCollaboratorController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        abort_unless($memorial->canManageTeam($request->user()), 403);

        return response()->json([
            'collaborators' => $memorial->collaborators()->with('user:id,name,email')->latest()->get()
                ->map(fn (MemorialCollaborator $c) => $this->present($c)),
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        $actor = $request->user();
        abort_unless($memorial->canManageTeam($actor), 403);

        // The plan feature gates the *owner's* ability to invite, matching how it is sold.
        // Platform admins and the reseller's own staff manage on the platform's behalf, so
        // they are not held to the end-client's plan.
        $privileged = $actor->hasRole(['admin', 'super-admin']) || $memorial->isManagedByResellerStaff($actor);
        if (! $privileged && ! PlanLimitsHelper::canUseAdvancedPrivacy($memorial)) {
            return response()->json([
                'message' => 'Inviting people to help manage this memorial is not included in its current plan.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['editor', 'viewer'])],
        ]);

        $email = strtolower(trim($validated['email']));
        $name = $validated['name'] ?? null;

        $invitee = User::where('email', $email)->first();
        $isNewAccount = false;

        if (! $invitee) {
            $invitee = User::create([
                'name' => $name ?: (string) Str::of($email)->before('@')->headline(),
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                // A memorial's reseller owns the relationship, so a family member invited to a
                // reseller memorial becomes that reseller's user, not a stray platform account.
                'reseller_id' => $memorial->reseller_id,
                'original_reseller_id' => $memorial->reseller_id,
            ]);
            $invitee->assignRole('user');
            $isNewAccount = true;
        } elseif ($name && ! $invitee->name) {
            $invitee->update(['name' => $name]);
        }

        if ($invitee->id === $memorial->user_id) {
            return response()->json(['message' => 'That person already owns this memorial.'], 422);
        }

        // updateOrCreate honours the unique(memorial_id,user_id): re-inviting simply changes
        // their role rather than erroring. accepted_at is set now so access is immediate — the
        // app's invite convention is an account that already works, reached by a sign-in code.
        $collaborator = MemorialCollaborator::updateOrCreate(
            ['memorial_id' => $memorial->id, 'user_id' => $invitee->id],
            ['role' => $validated['role'], 'invited_at' => now(), 'accepted_at' => now()],
        );

        NotificationService::notifyMemorialCollaboratorInvite($invitee, $memorial, $actor->name, $isNewAccount);

        $collaborator->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'message' => NotificationService::emailConfigured()
                ? "Invited {$invitee->email} to help manage this memorial."
                : "Added {$invitee->email} — but no email was sent, because outgoing email is not configured yet.",
            'collaborator' => $this->present($collaborator),
        ], 201);
    }

    public function update(Request $request, string $slug, MemorialCollaborator $collaborator): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        abort_unless($memorial->canManageTeam($request->user()), 403);
        abort_unless($collaborator->memorial_id === $memorial->id, 404);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['editor', 'viewer'])],
        ]);

        $collaborator->update(['role' => $validated['role']]);
        $collaborator->load('user:id,name,email');

        return response()->json(['success' => true, 'collaborator' => $this->present($collaborator)]);
    }

    public function destroy(Request $request, string $slug, MemorialCollaborator $collaborator): JsonResponse
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();
        abort_unless($memorial->canManageTeam($request->user()), 403);
        abort_unless($collaborator->memorial_id === $memorial->id, 404);

        $collaborator->delete();

        return response()->json(['success' => true]);
    }

    private function present(MemorialCollaborator $collaborator): array
    {
        return [
            'id' => $collaborator->id,
            'role' => $collaborator->role,
            'name' => $collaborator->user?->name,
            'email' => $collaborator->user?->email,
            'invited_at' => $collaborator->invited_at?->toIso8601String(),
        ];
    }
}
