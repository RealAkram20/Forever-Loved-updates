<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Models\Memorial;
use App\Models\Page;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function home()
    {
        $appName = SystemSetting::get('branding.app_name', 'Forever Loved');
        $tagline = SystemSetting::get('branding.tagline', 'Celebrate lives that matter');

        $popularMemorials = Memorial::where('is_public', true)
            ->where('status', Memorial::STATUS_ACTIVE)
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->withCount(['views as view_count'])
            ->orderByDesc('view_count')
            ->limit(12)
            ->get()
            ->filter(fn ($m) => $m->completion_percentage >= 40)
            ->take(8);

        $designations = [
            ['name' => 'COVID-19 Victims', 'value' => 'COVID-19 victim', 'icon' => 'shield'],
            ['name' => 'War Veterans', 'value' => 'War veteran', 'icon' => 'star'],
            ['name' => 'First Responders', 'value' => 'First responder', 'icon' => 'heart'],
            ['name' => 'Cancer Victims', 'value' => 'Cancer victim', 'icon' => 'ribbon'],
            ['name' => 'Child Loss', 'value' => 'Child loss', 'icon' => 'flower'],
            ['name' => 'Infant Loss', 'value' => 'Miscarriage, stillborn and infant loss', 'icon' => 'baby'],
        ];

        foreach ($designations as &$d) {
            $d['count'] = Memorial::where('is_public', true)
                ->where('status', Memorial::STATUS_ACTIVE)
                ->where(function ($q) use ($d) {
                    $q->where('cause_of_death', $d['value'])
                      ->orWhere('designation', 'like', '%' . $d['value'] . '%');
                })->count();
        }
        unset($d);

        return view('pages.visitor.home', [
            'title' => 'Home',
            'appName' => $appName,
            'tagline' => $tagline,
            'popularMemorials' => $popularMemorials,
            'designations' => $designations,
            'shareMeta' => SiteShareMetaHelper::forHome(),
        ]);
    }

    public function pricing()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $currency = SystemSetting::get('payments.currency', 'USD');

        return view('pages.visitor.pricing', [
            'title' => 'Pricing & Features',
            'plans' => $plans,
            'currency' => $currency,
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Pricing & Features',
                'pricing',
                [],
                'Compare memorial plans and features. Choose the right way to honor and celebrate a life online.'
            ),
        ]);
    }

    public function about()
    {
        $page = Page::getBySlug('about');

        return view('pages.visitor.about', [
            'title' => $page?->title ?? 'About Us',
            'page' => $page,
            'shareMeta' => SiteShareMetaHelper::forCmsPage($page, 'About Us', 'about'),
        ]);
    }

    public function privacyPolicy()
    {
        $page = Page::getBySlug('privacy-policy');

        return view('pages.visitor.privacy-policy', [
            'title' => $page?->title ?? 'Privacy Policy',
            'page' => $page,
            'shareMeta' => SiteShareMetaHelper::forCmsPage($page, 'Privacy Policy', 'privacy-policy'),
        ]);
    }

    public function termsOfUse()
    {
        $page = Page::getBySlug('terms-of-use');

        return view('pages.visitor.terms-of-use', [
            'title' => $page?->title ?? 'Terms of Use',
            'page' => $page,
            'shareMeta' => SiteShareMetaHelper::forCmsPage($page, 'Terms of Use', 'terms-of-use'),
        ]);
    }
}
