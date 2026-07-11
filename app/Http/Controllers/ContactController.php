<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Models\Page;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function show()
    {
        $appName = SystemSetting::get('branding.app_name', 'Forever Loved');

        $layoutPage = Page::getBySlug('contact');
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

        if (! SystemSetting::get('smtp.from_address')) {
            return back()->with('error', 'No recipient email configured. Please try again later.');
        }

        // Queued (or run inline when no cron) so a slow SMTP server can never
        // stall the visitor's request; retries happen in the background.
        \App\Support\ReliableDispatch::dispatch(new \App\Jobs\SendContactEmail(
            name: $request->input('name'),
            email: $request->input('email'),
            subject: $request->input('subject'),
            body: $request->input('message'),
        ));

        return back()->with('success', 'Your message has been sent. We\'ll get back to you soon.');
    }

}
