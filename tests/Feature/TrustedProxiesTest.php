<?php

use App\Support\TrustedProxies;
/**
 * Trusted proxies.
 *
 * `trustProxies(at: '*')` trusted the client's own X-Forwarded-For, and Request::ip() is the
 * key every `throttle` on this app's public routes uses.
 */
it('does not trust every proxy by default', function () {
    expect(TrustedProxies::list())->not->toBe('*')->toBeArray();
});

it('trusts the private ranges a container-networked proxy reaches php from', function () {
    expect(TrustedProxies::list())
        ->toContain('10.0.0.0/8')
        ->toContain('172.16.0.0/12')
        ->toContain('192.168.0.0/16');
});

it('keeps one env var as the way back to the old behaviour', function () {
    putenv('TRUSTED_PROXIES=*');
    expect(TrustedProxies::list())->toBe('*');

    putenv('TRUSTED_PROXIES=203.0.113.7,198.51.100.0/24');
    expect(TrustedProxies::list())->toBe(['203.0.113.7', '198.51.100.0/24']);

    putenv('TRUSTED_PROXIES');
});
