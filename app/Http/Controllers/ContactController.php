<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Helpers\ThemeSetting;
use App\Models\SystemSetting;
use App\Support\StandardPages;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show()
    {
        // siteTenantId(): the page belongs to whichever site is being served, not to the
        // reseller a signed-in visitor happens to belong to.
        $tenant = ThemeSetting::siteTenant();
        $appName = $tenant?->name ?: SystemSetting::get('branding.app_name', 'Forever Loved');

        $layoutPage = StandardPages::resolve('contact', $tenant?->id);
        if ($layoutPage && $layoutPage->hasLayout()) {
            return view('pages.visitor.page-layout', [
                'title' => $layoutPage->title ?: 'Contact Us',
                'widgets' => $layoutPage->layout['widgets'],
                'layoutContext' => [],
                'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                    'Contact Us',
                    'contact',
                    [],
                    'Get in touch with our team for questions about memorials, accounts, or support.'
                ),
            ]);
        }

        return view('pages.visitor.contact', [
            'title' => 'Contact Us',
            'appName' => $appName,
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Contact Us',
                'contact',
                [],
                'Get in touch with our team for questions about memorials, accounts, or support.'
            ),
        ]);
    }

    public function send(Request $request)
    {
        // Before validation, deliberately.
        //
        // A bot that trips the trap gets the same success message a person gets, and nothing
        // is sent. Checking after validation would hand it a 422 listing every field name it
        // got wrong, which is free reconnaissance; checking first means a caught bot learns
        // exactly nothing about the form and has no failure to retry against.
        if (\App\Support\Honeypot::tripped($request)) {
            \App\Support\Honeypot::log($request, 'contact');

            return back()->with('success', 'Your message has been sent. We\'ll get back to you soon.');
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $smtpEnabled = SystemSetting::get('smtp.enabled', false);
        $smtpHost = SystemSetting::get('smtp.host');

        if (! $smtpEnabled || ! $smtpHost) {
            return back()->with('error', 'Email is not configured yet. Please try again later.');
        }

        // The enquiry belongs to whichever site it was sent from. A family writing to a
        // funeral home on that funeral home's own domain must not reach the platform's
        // inbox — and, until this page existed for tenants, there was nowhere else for it
        // to go. Their address falls back to ours if they have left it blank.
        $tenant = ThemeSetting::siteTenant();
        $toAddress = $tenant?->contact_email ?: SystemSetting::get('smtp.from_address');

        if (! $toAddress) {
            return back()->with('error', 'No recipient email configured. Please try again later.');
        }

        // Queued (or run inline when no cron) so a slow SMTP server can never
        // stall the visitor's request; retries happen in the background.
        \App\Support\ReliableDispatch::dispatch(new \App\Jobs\SendContactEmail(
            name: $request->input('name'),
            email: $request->input('email'),
            subject: $request->input('subject'),
            body: $request->input('message'),
            toAddress: $toAddress,
            siteName: $tenant?->name,
        ));

        return back()->with('success', 'Your message has been sent. We\'ll get back to you soon.');
    }

}
