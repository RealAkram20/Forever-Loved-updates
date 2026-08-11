<?php

use App\Models\Reseller;
use App\Services\CustomDomainProxySync;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The sync that tells Traefik which reseller custom domains to route: one router
 * file per verified domain in the folder the proxy watches, nothing for anything
 * unverified, platform-owned, or removed.
 */
function proxyDir(): string
{
    $dir = storage_path('framework/testing/proxy-domains');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    foreach (glob($dir.'/*.yaml') ?: [] as $f) {
        unlink($f);
    }
    config(['proxy.custom_domains_dir' => $dir]);

    return $dir;
}

function domainReseller(string $slug, ?string $domain, string $status = Reseller::DOMAIN_VERIFIED): Reseller
{
    return Reseller::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'status' => Reseller::STATUS_ACTIVE,
        'custom_domain' => $domain,
        'custom_domain_status' => $status,
    ]);
}

it('writes a router file per verified domain and removes it when the domain goes', function () {
    $dir = proxyDir();

    $acme = domainReseller('acme', 'kangaruride.com');
    domainReseller('unproven', 'not-yet-verified.com', Reseller::DOMAIN_UNVERIFIED);

    $sync = app(CustomDomainProxySync::class);

    $result = $sync->sync();

    expect($result['written'])->toBe(1)
        ->and(is_file($dir.'/kangaruride.com.yaml'))->toBeTrue()
        ->and(is_file($dir.'/not-yet-verified.com.yaml'))->toBeFalse();

    $yaml = file_get_contents($dir.'/kangaruride.com.yaml');
    expect($yaml)->toContain('Host(`kangaruride.com`)')
        ->and($yaml)->toContain('Host(`www.kangaruride.com`)')
        ->and($yaml)->toContain('certResolver: letsencrypt')
        ->and($yaml)->toContain('url: http://app:8080');

    // A second pass changes nothing — the scheduler runs this every minute.
    expect($sync->sync())->toBe(['written' => 0, 'removed' => 0, 'kept' => 1]);

    $acme->update(['custom_domain' => null]);

    expect($sync->sync()['removed'])->toBe(1)
        ->and(is_file($dir.'/kangaruride.com.yaml'))->toBeFalse();
});

it('never writes a file that could shadow the platform own hosts', function () {
    $dir = proxyDir();

    config(['app.url' => 'https://alwaysforeverloved.com', 'reseller.domain' => 'alwaysforeverloved.com']);

    domainReseller('sneaky', 'alwaysforeverloved.com');
    domainReseller('sneakier', 'evil.alwaysforeverloved.com');
    domainReseller('sneakiest', 'www.alwaysforeverloved.com');

    app(CustomDomainProxySync::class)->sync();

    expect(glob($dir.'/*.yaml'))->toBeEmpty();
});

it('is a no-op when no proxy directory is configured', function () {
    config(['proxy.custom_domains_dir' => null]);

    domainReseller('acme', 'kangaruride.com');

    expect(app(CustomDomainProxySync::class)->sync())->toBeNull();
});

it('redirects www of a verified custom domain to the bare form, path and query intact', function () {
    domainReseller('acme', 'kangaruride.com');

    $this->get('http://www.kangaruride.com/login?next=1')
        ->assertRedirect('http://kangaruride.com/login?next=1')
        ->assertStatus(301);
});
