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

    public static function googleCallbackUrl(): string
    {
        return url('/auth/google/callback');
    }
}
