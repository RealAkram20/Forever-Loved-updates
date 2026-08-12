<?php

namespace App\Http\Controllers\Reseller;

use App\Exceptions\ResellerCapacityExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reseller\StoreResellerMemorialRequest;
use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\MemorialCreationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        return view('pages.reseller.dashboard', [
            'title' => 'Reseller Dashboard',
            'reseller' => $reseller,
            'memorialCount' => $reseller->memorialsUsed(),
            'memorialAllowance' => $reseller->memorialAllowance(),
            'memorialsRemaining' => $reseller->memorialsRemaining(),
            'storageUsed' => $reseller->storageUsedBytes(),
            'storageLimit' => $reseller->storageLimitBytes(),
            'storagePercent' => $reseller->storagePercentUsed(),
            'clientCount' => User::where('reseller_id', $reseller->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'user'))->count(),
            'planCount' => $reseller->plans()->count(),
        ]);
    }

    public function memorials(Request $request)
    {
        $memorials = Memorial::where('reseller_id', $request->user()->reseller_id)
            ->with('owner')
            ->latest()
            ->paginate(15);

        return view('pages.reseller.memorials', [
            'title' => 'Client Memorials',
            'memorials' => $memorials,
        ]);
    }

    public function createMemorial(Request $request)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();
        $plans = $reseller->plans()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get();

        return view('pages.reseller.memorials-create', [
            'title' => 'Create Memorial for a Client',
            'reseller' => $reseller,
            // The same intake vocabulary as /memorials/create, so staff gathering details
            // from a family can record all of it at once rather than opening a second,
            // different screen afterwards to enter the rest.
            'clients' => User::where('reseller_id', $reseller->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'user'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'plans' => $plans,
            'defaultPlanId' => $plans->first(fn (SubscriptionPlan $plan) => $plan->isFree())?->id,
        ]);
    }

    /**
     * Reseller staff building a memorial on behalf of a client — the intake/service model
     * described by the business owner ("assign someone to create, gather all the
     * information"). The client becomes the memorial's actual owner (invited by email,
     * passwordless, same pattern already used for guest tribute submitters) while
     * reseller staff retain management rights via Memorial::isManagedByResellerStaff().
     *
     * Creation itself is MemorialCreationService's job — the same one the platform form
     * and the public wizard use, so a memorial taken in here is the same object, with the
     * same relations and plan record, as one a family creates for itself.
     */
    public function storeMemorial(StoreResellerMemorialRequest $request, MemorialCreationService $creator)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        // Checked before the client is resolved, not only inside the service: creating the
        // account first and refusing the memorial afterwards would leave an orphan
        // passwordless user behind, and the retry would then find it and skip the invite.
        // The service still enforces the same rule — that is the invariant; this is the
        // door it is checked at.
        if (! $reseller->hasMemorialCapacity()) {
            return back()->withInput()->with('error', (new ResellerCapacityExceededException($reseller))->staffMessage());
        }

        [$client, $wasCreated] = $this->resolveClient($request, $reseller);

        $plan = $request->filled('plan_id')
            ? $reseller->plans()->find($request->integer('plan_id'))
            : null;

        try {
            $memorial = $creator->create(
                $client,
                $request->validatedPayload(),
                $plan,
                [
                    'reseller_id' => $reseller->id,
                    'original_reseller_id' => $reseller->id,
                    'status' => Memorial::STATUS_ACTIVE,
                ]
            );
        } catch (ResellerCapacityExceededException $e) {
            return back()->withInput()->with('error', $e->staffMessage());
        }

        if ($plan) {
            // Recorded as active immediately, marked 'offline': the reseller collects from
            // the family directly, so withholding entitlements until a gateway payment that
            // will never arrive would only deny staff the quotas they need to build the page.
            $creator->attachPlanSubscription(
                $memorial,
                $plan,
                $plan->isFree() ? null : 'offline',
                $plan->isFree() ? null : 'reseller-intake'
            );
        }

        // Told either way. The notice names the memorial, so an existing client learns a
        // page has been made for their family — staying silent for them would mean the
        // only people who ever hear about a memorial are those who happened to be new.
        NotificationService::notifyAccountInvite($client, $reseller->name, $memorial->full_name);

        return redirect()->route('reseller.memorials')->with('success', $this->intakeMessage($memorial, $client, $wasCreated));
    }

    /**
     * The memorial's owner: either an existing client picked from the roster, or a new
     * passwordless account created from the name and email typed in.
     *
     * Picking by id is what fixes the old screen's quiet failure — it matched an existing
     * account by typed email and then ignored the name beside it, so correcting a client's
     * name here did nothing at all.
     *
     * @return array{0: User, 1: bool} the client, and whether it was newly created
     *
     * @throws ValidationException when the account is not this reseller's to assign to
     */
    private function resolveClient(StoreResellerMemorialRequest $request, $reseller): array
    {
        if ($request->filled('client_id')) {
            $picked = User::find($request->integer('client_id'));

            // The request's exists rule scopes by tenant; the role is checked here, the
            // same guard ClientController::authorizeClient applies. Without it a staff
            // member could post the reseller owner's or a colleague's id and put a
            // client's memorial inside a staff account.
            if (! $picked || $picked->reseller_id !== $reseller->id || ! $picked->hasRole('user')) {
                throw ValidationException::withMessages([
                    'client_id' => 'That client could not be found in your organisation.',
                ]);
            }

            return [$picked, false];
        }

        $email = strtolower($request->string('client_email')->toString());
        $existing = User::where('email', $email)->first();

        // An existing account may only be used if it already belongs to this reseller as
        // a client. Without this check, submitting any known email — another reseller's
        // client, a direct platform user, an admin, or this reseller's own staff —
        // creates a memorial *inside that person's account* while leaving it under this
        // reseller's control and branding.
        if ($existing && ($existing->reseller_id !== $reseller->id || ! $existing->hasRole('user'))) {
            throw ValidationException::withMessages([
                'client_email' => 'That email already belongs to an account outside your client list. Use a different email, or ask them to be transferred to you first.',
            ]);
        }

        if ($existing) {
            return [$existing, false];
        }

        $client = User::create([
            'name' => $request->string('client_name')->toString(),
            'email' => $email,
            // Passwordless, consistent with every other invited account: they sign in with
            // an emailed code rather than a password nobody ever told them.
            'password' => null,
            'reseller_id' => $reseller->id,
            'original_reseller_id' => $reseller->id,
        ]);
        $client->assignRole('user');

        return [$client, true];
    }

    /** Says plainly whether the client was actually emailed, since SMTP may not be configured. */
    private function intakeMessage(Memorial $memorial, User $client, bool $wasCreated): string
    {
        $who = $wasCreated
            ? "{$client->name} has been invited by email"
            : "{$client->name} has been notified by email";

        return NotificationService::emailConfigured()
            ? "Memorial for \"{$memorial->full_name}\" created, and {$who}."
            : "Memorial for \"{$memorial->full_name}\" created — but {$client->name} was not emailed, because outgoing email is not configured yet.";
    }
}
