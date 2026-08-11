<?php

use App\Models\Reseller;
use App\Services\DomainVerificationService;
use App\Support\ResellerDomain;

/**
 * The reseller base domain must always describe the install it is running on.
 *
 * It used to fall back to a hardcoded domain, so any install that had not set
 * RESELLER_APP_DOMAIN handed resellers and their families addresses on a domain nobody
 * owned. These tests pin the replacement behaviour.
 */
it('derives the base domain from APP_URL', function (string $appUrl, string $expected) {
    expect(ResellerDomain::fromAppUrl($appUrl))->toBe($expected);
})->with([
    'plain domain' => ['https://mysite.com', 'mysite.com'],
    'www is stripped' => ['https://www.mysite.co.ug', 'mysite.co.ug'],
    'port is not part of the domain' => ['https://mysite.com:8443', 'mysite.com'],
    'subdomain host is kept whole' => ['https://memorials.example.org', 'memorials.example.org'],
    'path is irrelevant' => ['http://localhost/Forever', 'localhost'],
    'uppercase is normalised' => ['https://MySite.COM', 'mysite.com'],
    'missing APP_URL degrades to localhost' => ['', 'localhost'],
    'unparseable APP_URL degrades to localhost' => ['not a url', 'localhost'],
]);

it('namespaces the DNS challenge record by the app own domain', function (string $appUrl, string $expected) {
    expect(ResellerDomain::verificationPrefix($appUrl))->toBe($expected);
})->with([
    ['https://mysite.com', '_mysite-verify'],
    ['https://www.mysite.co.ug', '_mysite-verify'],
    ['https://memorials.example.org', '_memorials-verify'],
    // Punctuation is stripped rather than passed into a DNS label.
    ['https://my-site.com', '_mysite-verify'],
    ['', '_localhost-verify'],
]);

it('names no hardcoded domain anywhere in the shipped configuration', function () {
    // The whole point of the change. A grep test rather than a behavioural one, because
    // the failure mode is someone reintroducing a literal, not the logic breaking.
    $sources = array_merge(
        glob(base_path('config/*.php')) ?: [],
        glob(app_path('Support/*.php')) ?: [],
        glob(app_path('Services/*.php')) ?: [],
        [base_path('.env.example')],
    );

    foreach ($sources as $file) {
        expect(strtolower((string) file_get_contents($file)))
            ->not->toContain('foreverloved.com', "{$file} still names a hardcoded domain");
    }
});

it('tells the settings screen the same record it will later look up', function () {
    config(['reseller.verification_prefix' => '_mysite-verify']);

    expect(app(DomainVerificationService::class)->txtHost('acmefuneral.co.ug'))
        ->toBe('_mysite-verify.acmefuneral.co.ug');
});

it('refuses to mint subdomains against a bare hostname', function () {
    // acme.localhost resolves in some browsers and on almost no other system, so the app
    // falls back to the /r/{slug} path rather than handing anyone that address.
    config(['reseller.domain' => 'localhost', 'app.url' => 'http://localhost']);

    expect(Reseller::hostRoutingAvailable())->toBeTrue()
        ->and(Reseller::subdomainRoutingAvailable())->toBeFalse();
});

it('mints subdomains once APP_URL is a real domain', function () {
    config(['reseller.domain' => 'mysite.com', 'app.url' => 'https://mysite.com']);

    expect(Reseller::subdomainRoutingAvailable())->toBeTrue();
});
