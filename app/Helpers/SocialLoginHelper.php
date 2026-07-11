<?php

namespace App\Helpers;

use App\Models\SystemSetting;

class SocialLoginHelper
{
    /**
     * Google OAuth is enabled in admin and has client ID + secret (from settings or .env fallback).
     */
    public static function googleLoginEnabled(): bool
    {
        if (! (bool) SystemSetting::get('oauth.google_enabled', false)) {
            return false;
        }

        $id = trim((string) (SystemSetting::get('oauth.google_client_id', '') ?: env('GOOGLE_CLIENT_ID', '')));
        $secret = trim((string) (SystemSetting::get('oauth.google_client_secret', '') ?: env('GOOGLE_CLIENT_SECRET', '')));

        return $id !== '' && $secret !== '';
    }

    /**
     * Built from APP_URL, not the incoming request: behind a TLS-terminating
     * proxy the request may look like http://, and Google rejects any
     * redirect_uri that doesn't exactly match the console registration.
     */
    public static function googleCallbackUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return ($base !== '' ? $base : url('')).'/auth/google/callback';
    }
}
