<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Models\Memorial;
use App\Models\Page;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Services\SiteLayoutService;

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
            ->withCount(['views as view_count', 'tributes as tribute_count'])
            ->orderByDesc('view_count')
            ->limit(12)
            ->get()
            ->filter(fn ($m) => $m->completion_percentage >= 40)
            ->take(8);

        $layoutPage = Page::getBySlug(Page::SLUG_VISITOR_HOME);
        if ($layoutPage && $layoutPage->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => 'Home',
                'widgets' => $layoutPage->layout['widgets'],
                'layoutContext' => [
                    'popularMemorials' => $popularMemorials,
                    'tagline' => $tagline,
                ],
                'shareMeta' => SiteShareMetaHelper::forHome(),
            ]);
        }

        return view('pages.visitor.home', [
            'title' => 'Home',
            'appName' => $appName,
            'tagline' => $tagline,
            'popularMemorials' => $popularMemorials,
            'homeBlocks' => app(SiteLayoutService::class)->blocksForHome(),
            'shareMeta' => SiteShareMetaHelper::forHome(),
        ]);
    }

    public function pricing()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $currency = SystemSetting::get('payments.currency', 'USD');

        $layoutPage = Page::getBySlug('pricing');
        if ($layoutPage && $layoutPage->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $layoutPage->title ?: 'Pricing & Features',
                'widgets' => $layoutPage->layout['widgets'],
                'layoutContext' => [
                    'plans' => $plans,
                    'currency' => $currency,
                ],
                'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                    'Pricing & Features',
                    'pricing',
                    [],
                    'Compare memorial plans and features. Choose the right way to honor and celebrate a life online.'
                ),
            ]);
        }

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
        $shareMeta = SiteShareMetaHelper::forCmsPage($page, 'About Us', 'about');

        if ($page && $page->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $page->title ?: 'About Us',
                'widgets' => $page->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => $shareMeta,
            ]);
        }

        return view('pages.visitor.about', [
            'title' => $page?->title ?? 'About Us',
            'page' => $page,
            'shareMeta' => $shareMeta,
        ]);
    }

    public function privacyPolicy()
    {
        $page = Page::getBySlug('privacy-policy');
        $shareMeta = SiteShareMetaHelper::forCmsPage($page, 'Privacy Policy', 'privacy-policy');

        if ($page && $page->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $page->title ?: 'Privacy Policy',
                'widgets' => $page->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => $shareMeta,
            ]);
        }

        return view('pages.visitor.privacy-policy', [
            'title' => $page?->title ?? 'Privacy Policy',
            'page' => $page,
            'shareMeta' => $shareMeta,
        ]);
    }

    public function termsOfUse()
    {
        $page = Page::getBySlug('terms-of-use');
        $shareMeta = SiteShareMetaHelper::forCmsPage($page, 'Terms of Use', 'terms-of-use');

        if ($page && $page->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $page->title ?: 'Terms of Use',
                'widgets' => $page->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => $shareMeta,
            ]);
        }

        return view('pages.visitor.terms-of-use', [
            'title' => $page?->title ?? 'Terms of Use',
            'page' => $page,
            'shareMeta' => $shareMeta,
        ]);
    }

    /**
     * Generic CMS page at /p/{slug} (custom pages created in admin).
     */
    public function showPage(string $slug)
    {
        $page = Page::getBySlug($slug);

        if (! $page || ! $page->is_published) {
            abort(404);
        }

        $canonicalSlugs = [
            'about',
            'privacy-policy',
            'terms-of-use',
            Page::SLUG_VISITOR_HOME,
            'pricing',
            'contact',
            Page::SLUG_FIND_MEMORIAL,
        ];
        if (in_array($slug, $canonicalSlugs, true)) {
            return redirect()->to($page->publicUrl(), 301);
        }

        if ($page->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $page->title,
                'widgets' => $page->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => SiteShareMetaHelper::forCmsPageDirect($page, $page->publicUrl()),
            ]);
        }

        return view('pages.visitor.cms-page', [
            'title' => $page->title,
            'page' => $page,
            'shareMeta' => SiteShareMetaHelper::forCmsPageDirect($page, $page->publicUrl()),
        ]);
    }
}
