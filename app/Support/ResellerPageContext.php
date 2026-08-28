<?php

namespace App\Support;

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\PageBuilder\Widgets\MemorialShowcaseWidget;
use App\PageBuilder\Widgets\PricingPlansWidget;

/**
 * The layout context a reseller's page renders with — the tenant-scoped equivalent of the
 * context Admin\PageController::preview() and PageController build for the platform. Data
 * only appears when a widget on the page actually needs it (a Pricing widget pulls plans, a
 * Showcase widget pulls memorials), and every query is constrained to this reseller so their
 * page can never surface the platform's plans or another tenant's memorials.
 *
 * Shared by the live editor preview and the public page render so the two always agree.
 */
class ResellerPageContext
{
    /**
     * @param  array<int, string>  $widgetTypes  the `type` of every widget on the page
     * @return array<string, mixed>
     */
    public static function forWidgets(Reseller $reseller, array $widgetTypes): array
    {
        $context = [
            // This context is only ever built for a reseller's page, so the platform's line
            // has no business in it — an empty default means their hero simply omits it rather
            // than carrying our marketing on their site.
            // Parenthesised: a cast binds tighter than ??, so `(string) $a['k'] ?? ''` still
            // reads the key unguarded and throws when it is absent.
            'tagline' => (string) (\App\Models\ResellerSetting::allFor($reseller->id)['branding.tagline']['value'] ?? ''),
        ];

        if (in_array(PricingPlansWidget::type(), $widgetTypes, true)) {
            $context['plans'] = SubscriptionPlan::where('reseller_id', $reseller->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            $context['currency'] = SystemSetting::get('payments.currency', 'USD');
        }

        if (in_array(MemorialShowcaseWidget::type(), $widgetTypes, true)) {
            $context['popularMemorials'] = Memorial::where('is_public', true)
                ->where('status', Memorial::STATUS_ACTIVE)
                ->where('reseller_id', $reseller->id)
                ->whereNotNull('first_name')
                ->whereNotNull('last_name')
                ->withCount(['views as view_count', 'tributes as tribute_count'])
                ->orderByDesc('view_count')
                ->limit(12)
                ->get()
                ->filter(fn ($m) => $m->completion_percentage >= 40)
                ->take(8);
        }

        return $context;
    }
}
