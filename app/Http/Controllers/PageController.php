<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Helpers\ThemeSetting;
use App\Models\Memorial;
use App\Models\Page;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Services\SiteLayoutService;
use App\Support\StandardPages;

class PageController extends Controller
{
    /**
     * The home page, for the platform and for a reseller's own front page alike.
     *
     * Reseller\... does not need its own design: a reseller's white-labeled front page is this
     * page, with their logo, palette, fonts, name and memorials — which is what
     * PublicMemorialController::indexForReseller() delegates here for.
     *
     * The memorial list is tenant-scoped both ways. Previously it selected every public
     * memorial regardless of owner, which meant reseller-owned memorials surfaced on the
     * *platform's* homepage — directly contradicting MemorialDirectoryController, which
     * excludes them on the grounds that they "belong on the reseller's own branded domain".
     */
    public function home()
    {
        // siteTenant(), not tenant(). Content follows the *host*; only branding follows the
        // viewer. tenant() falls back to the signed-in user's own reseller, so this served a
        // funeral home's own front page — their hero, their About, their memorials — to their
        // staff on *our* marketing site, and left them no way to read ours at all. Every
        // sibling on this controller already made the distinction; home was the one that did
        // not. See the docblock on ThemeSetting::siteTenant(), which describes this exact
        // failure.
        $reseller = ThemeSetting::siteTenant();

        $appName = SiteShareMetaHelper::appDisplayName();
        $tagline = SystemSetting::get('branding.tagline', 'Celebrate lives that matter');

        $popularMemorials = Memorial::where('is_public', true)
            ->where('status', Memorial::STATUS_ACTIVE)
            ->when($reseller, fn ($q) => $q->where('reseller_id', $reseller->id))
            ->unless($reseller, fn ($q) => $q->whereNull('reseller_id'))
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->withCount(['views as view_count', 'tributes as tribute_count'])
            ->orderByDesc('view_count')
            ->limit(12)
            ->get()
            ->filter(fn ($m) => $m->completion_percentage >= 40)
            ->take(8);

        // A reseller who has built their own homepage layout gets it on their front page,
        // rendered with their plans and memorials as context. Until they add sections, this
        // falls through to the shared branded home layout below.
        if ($reseller) {
            $resellerHome = Page::getBySlugForReseller(Page::SLUG_VISITOR_HOME, $reseller->id);
            if ($resellerHome && $resellerHome->hasLayout()) {
                $widgets = $resellerHome->layout['widgets'];

                return view('pages.visitor.page-layout', [
                    'title' => $resellerHome->title ?: 'Home',
                    'widgets' => $widgets,
                    'layoutContext' => array_merge(
                        \App\Support\ResellerPageContext::forWidgets($reseller, array_column($widgets, 'type')),
                        ['popularMemorials' => $popularMemorials, 'tagline' => $tagline]
                    ),
                    'shareMeta' => SiteShareMetaHelper::forHome(),
                ]);
            }
        }

        // A template that ships its own front page outranks the *platform's* builder layout.
        //
        // That layout is our fallback for anyone who has built nothing, and serving it here
        // put our arrangement of blocks — our hero, our showcase — inside a reseller's themed
        // site, which is the one thing a theme exists to prevent. It does not outrank the
        // reseller's own layout, checked above: that is something they built deliberately.
        $themeOwnsHome = app(\App\Themes\ActiveTheme::class)->ownsView('pages.visitor.home');

        $layoutPage = Page::getBySlug(Page::SLUG_VISITOR_HOME);
        if (! $themeOwnsHome && $layoutPage && $layoutPage->hasLayout()) {
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
        $tenantId = ThemeSetting::siteTenantId();

        // sellableOnHost, not sellableTo: this page is public, so the visitor is usually
        // anonymous and their own reseller_id answers nothing. Keyed off the viewer it would
        // print the platform's prices on a reseller's own pricing page.
        $plans = SubscriptionPlan::where('is_active', true)
            ->sellableOnHost($tenantId, auth()->user())
            ->orderBy('sort_order')
            ->get();

        $currency = SystemSetting::get('payments.currency', 'USD');

        $layoutPage = StandardPages::resolve('pricing', $tenantId);
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

    /**
     * The three plain content pages, which differ only in slug, heading and fallback blade.
     *
     * All of them resolve for whichever site is being served. They used to call
     * Page::getBySlug() with no tenant, so on a reseller's own domain these routes could only
     * ever find *our* page — which is why About was redirected away entirely and why the legal
     * pages showed the platform's text on a white-labeled site.
     *
     * StandardPages::resolve() handles the one deliberate exception: privacy-policy and
     * terms-of-use fall back to ours when a tenant has none of their own, because a site with
     * no policy at all is worse than one with generic text.
     */
    private function contentPage(string $slug, string $defaultTitle, string $fallbackView)
    {
        $tenantId = ThemeSetting::siteTenantId();
        $page = StandardPages::resolve($slug, $tenantId);
        $shareMeta = SiteShareMetaHelper::forCmsPage($page, $defaultTitle, $slug);

        if ($page && $page->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $page->title ?: $defaultTitle,
                'widgets' => $page->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => $shareMeta,
            ]);
        }

        return view($fallbackView, [
            'title' => $page?->title ?? $defaultTitle,
            'page' => $page,
            'shareMeta' => $shareMeta,
        ]);
    }

    public function about()
    {
        return $this->contentPage('about', 'About Us', 'pages.visitor.about');
    }

    public function privacyPolicy()
    {
        return $this->contentPage('privacy-policy', 'Privacy Policy', 'pages.visitor.privacy-policy');
    }

    public function termsOfUse()
    {
        return $this->contentPage('terms-of-use', 'Terms of Use', 'pages.visitor.terms-of-use');
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
