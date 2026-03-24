<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SystemMailConfigurator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show()
    {
        $appName = SystemSetting::get('branding.app_name', 'Forever Loved');

        return view('pages.visitor.contact', [
            'title' => 'Contact Us',
            'appName' => $appName,
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

        if (!$smtpEnabled || !$smtpHost) {
            return back()->with('error', 'Email is not configured yet. Please try again later.');
        }

        try {
            SystemMailConfigurator::applyFromSettings();

            $appName = SystemSetting::get('branding.app_name', config('app.name'));
            $toAddress = SystemSetting::get('smtp.from_address');

            if (!$toAddress) {
                return back()->with('error', 'No recipient email configured. Please try again later.');
            }

            $name = $request->input('name');
            $email = $request->input('email');
            $subject = $request->input('subject');
            $body = $request->input('message');

            $html = $this->buildContactEmailHtml($name, $email, $subject, $body, $appName);

            Mail::html($html, function ($msg) use ($toAddress, $appName, $subject, $email, $name) {
                $msg->to($toAddress)
                    ->replyTo($email, $name)
                    ->subject("{$appName} - Contact: {$subject}");
            });

            return back()->with('success', 'Your message has been sent. We\'ll get back to you soon.');
        } catch (\Throwable $e) {
            Log::error('Contact form email failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to send your message. Please try again later.');
        }
    }

    private function buildContactEmailHtml(string $name, string $email, string $subject, string $body, string $appName): string
    {
        $safeName = e($name);
        $safeEmail = e($email);
        $safeSubject = e($subject);
        $safeBody = nl2br(e($body));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
                <tr>
                    <td align="center">
                        <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                            <tr>
                                <td style="background:#465fff;padding:24px 32px;">
                                    <h1 style="margin:0;color:#ffffff;font-size:18px;font-weight:600;">{$appName} - Contact Form</h1>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td>
                                                <h2 style="margin:0 0 16px;color:#1f2937;font-size:18px;font-weight:600;">{$safeSubject}</h2>
                                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                                    <tr>
                                                        <td style="padding:4px 0;color:#6b7280;font-size:14px;"><strong>From:</strong> {$safeName}</td>
                                                    </tr>
                                                    <tr>
                                                        <td style="padding:4px 0;color:#6b7280;font-size:14px;"><strong>Email:</strong> <a href="mailto:{$safeEmail}" style="color:#465fff;">{$safeEmail}</a></td>
                                                    </tr>
                                                </table>
                                                <div style="padding:16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;">
                                                    <p style="margin:0;color:#374151;font-size:15px;line-height:1.6;">{$safeBody}</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
                                    <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                                        This message was sent via the contact form on {$appName}.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
