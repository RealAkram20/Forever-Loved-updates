<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Support\PlanFeatures;
use Illuminate\Http\Request;

/**
 * Mirrors Admin\SettingsController's plan CRUD, but every query is scoped to the
 * authenticated reseller staff member's own reseller_id. Unlike the admin controller
 * (which trusts the route-level role middleware alone), every mutating method here
 * additionally asserts the route-bound plan actually belongs to this reseller — a
 * reseller-admin role is not a global admin, so route-model-binding by id alone would
 * otherwise let one reseller edit another's plan by guessing an id.
 */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        $plans = SubscriptionPlan::forReseller($request->user()->reseller_id)
            ->orderBy('sort_order')
            ->get();

        return view('pages.reseller.plans', [
            'title' => 'Client Plans',
            'plans' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(array_merge([
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|unique:subscription_plans,slug',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
        ], PlanFeatures::rules()));

        $plan = SubscriptionPlan::create(array_merge($request->only([
            'name', 'slug', 'description', 'price', 'interval', 'is_active', 'sort_order',
            ...PlanFeatures::columns(),
        ]), [
            'is_popular' => $request->boolean('is_popular'),
            'reseller_id' => $request->user()->reseller_id,
        ]));

        if ($plan->is_popular) {
            $plan->makeSolePopular();
        }

        return back()->with('success', 'Plan created successfully.');
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        abort_unless($plan->reseller_id === $request->user()->reseller_id, 403);

        $request->validate(array_merge([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
        ], PlanFeatures::rules()));

        $plan->update(array_merge($request->only([
            'name', 'description', 'price', 'interval', 'is_active', 'sort_order',
            ...PlanFeatures::columns(),
        ]), ['is_popular' => $request->boolean('is_popular')]));

        if ($plan->is_popular) {
            $plan->makeSolePopular();
        }

        return back()->with('success', 'Plan updated successfully.');
    }

    public function destroy(Request $request, SubscriptionPlan $plan)
    {
        abort_unless($plan->reseller_id === $request->user()->reseller_id, 403);

        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active subscriptions.');
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted successfully.');
    }
}
