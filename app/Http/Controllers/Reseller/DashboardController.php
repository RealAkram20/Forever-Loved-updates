<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Memorial;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        return view('pages.reseller.memorials-create', [
            'title' => 'Create Memorial for a Client',
            'reseller' => $request->user()->reseller()->with('tier')->first(),
        ]);
    }

    /**
     * Reseller staff building a memorial on behalf of a client — the intake/service model
     * described by the business owner ("assign someone to create, gather all the
     * information"). The client becomes the memorial's actual owner (invited by email,
     * passwordless, same pattern already used for guest tribute submitters) while
     * reseller staff retain management rights via Memorial::isManagedByResellerStaff().
     */
    public function storeMemorial(Request $request)
    {
        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['required', 'email', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $reseller = $request->user()->reseller()->with('tier')->first();

        // Checked here rather than only in the view: this is the single path that ever
        // stamps reseller_id onto a memorial, so it is the only place the tier's included
        // quota can actually be held. A disabled button is a courtesy, not a control.
        if (! $reseller->hasMemorialCapacity()) {
            return back()->withInput()->with('error', sprintf(
                'You have used all %d memorial profiles included in the %s tier. Contact us to raise your allowance.',
                $reseller->memorialAllowance(),
                $reseller->tier?->name ?? 'current'
            ));
        }

        $email = strtolower($validated['client_email']);
        $client = User::where('email', $email)->first();

        // An existing account may only be used if it already belongs to this reseller.
        // Without this check, submitting any known email — another reseller's client, a
        // direct platform user, an admin — creates a memorial *inside that person's
        // account* while leaving it under this reseller's control and branding.
        if ($client && $client->reseller_id !== $reseller->id) {
            return back()->withInput()->with('error',
                'That email already belongs to an account outside your organisation. Use a different email, or ask them to be transferred to you first.');
        }

        if (! $client) {
            $client = User::create([
                'name' => $validated['client_name'],
                'email' => $email,
                'password' => null,
                'reseller_id' => $reseller->id,
                'original_reseller_id' => $reseller->id,
            ]);
            $client->assignRole('user');
        }

        $memorial = Memorial::create([
            'user_id' => $client->id,
            'reseller_id' => $reseller->id,
            'original_reseller_id' => $reseller->id,
            'slug' => $this->generateUniqueSlug($validated['full_name']),
            'title' => 'In Loving Memory of '.$validated['full_name'],
            'full_name' => $validated['full_name'],
            'short_description' => $validated['short_description'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'date_of_passing' => $validated['date_of_passing'] ?? null,
            'is_public' => $request->boolean('is_public', true),
            'status' => Memorial::STATUS_ACTIVE,
            'completion_status' => Memorial::COMPLETION_PENDING,
        ]);

        return redirect()->route('reseller.memorials')->with('success', "Memorial for \"{$memorial->full_name}\" created.");
    }

    private function generateUniqueSlug(string $fullName): string
    {
        $base = Str::slug($fullName);
        $slug = $base;
        $suffix = 1;

        while (Memorial::where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$suffix);
        }

        return $slug;
    }
}
