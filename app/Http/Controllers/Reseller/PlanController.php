<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
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
        $request->validate([
            'name' => 'required|string|max:50',
            'slug' => 'required|string|max:50|unique:subscription_plans,slug',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'memorial_limit' => 'required|integer|min:1',
            'storage_limit_mb' => 'required|integer|min:10',
            'max_gallery_images' => 'required|integer|min:0',
            'max_gallery_videos' => 'required|integer|min:0',
            'max_tributes' => 'required|integer|min:0',
            'max_chapters' => 'required|integer|min:0',
            'max_ai_bio_per_day' => 'required|integer|min:0',
            'feature_background_music' => 'boolean',
            'feature_advanced_privacy' => 'boolean',
            'feature_guest_notifications' => 'boolean',
            'feature_never_expires' => 'boolean',
            'feature_no_ads' => 'boolean',
            'feature_share_memories' => 'boolean',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $plan = SubscriptionPlan::create(array_merge($request->only([
            'name', 'slug', 'description', 'price', 'interval',
            'memorial_limit', 'storage_limit_mb',
            'max_gallery_images', 'max_gallery_videos', 'max_tributes', 'max_chapters', 'max_ai_bio_per_day',
            'feature_background_music', 'feature_advanced_privacy', 'feature_guest_notifications',
            'feature_never_expires', 'feature_no_ads', 'feature_share_memories',
            'is_active', 'sort_order',
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

        $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly,lifetime',
            'memorial_limit' => 'required|integer|min:1',
            'storage_limit_mb' => 'required|integer|min:10',
            'max_gallery_images' => 'required|integer|min:0',
            'max_gallery_videos' => 'required|integer|min:0',
            'max_tributes' => 'required|integer|min:0',
            'max_chapters' => 'required|integer|min:0',
            'max_ai_bio_per_day' => 'required|integer|min:0',
            'feature_background_music' => 'boolean',
            'feature_advanced_privacy' => 'boolean',
            'feature_guest_notifications' => 'boolean',
            'feature_never_expires' => 'boolean',
            'feature_no_ads' => 'boolean',
            'feature_share_memories' => 'boolean',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $plan->update(array_merge($request->only([
            'name', 'description', 'price', 'interval',
            'memorial_limit', 'storage_limit_mb',
            'max_gallery_images', 'max_gallery_videos', 'max_tributes', 'max_chapters', 'max_ai_bio_per_day',
            'feature_background_music', 'feature_advanced_privacy', 'feature_guest_notifications',
            'feature_never_expires', 'feature_no_ads', 'feature_share_memories',
            'is_active', 'sort_order',
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
