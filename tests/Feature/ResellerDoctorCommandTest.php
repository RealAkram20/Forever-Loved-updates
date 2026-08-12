<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use App\Support\ResellerHealthProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * reseller:doctor exists because the app hands out {slug}.{base} addresses the moment a
 * reseller is created, while the DNS record and wildcard certificate that make them
 * resolve are one-time infrastructure work outside this codebase. Without it the only
 * signal that they were never done is a reseller reporting a dead link.
 *
 * The probe is faked here — these assert the diagnosis, not the network.
 */
function fakeProbe(array $overrides = []): void
{
    $defaults = [
        'apex' => '203.0.113.10',
        'wildcard' => '203.0.113.10',
        'tlsOk' => true,
        'httpOk' => true,
    ];
    $state = array_merge($defaults, $overrides);

    app()->instance(ResellerHealthProbe::class, new class($state) extends ResellerHealthProbe
    {
        public function __construct(private array $state) {}

        public function apexIp(): ?string
        {
            return $this->state['apex'];
        }

        public function wildcardResolvedIp(): ?string
        {
            return $this->state['wildcard'];
        }

        public function resolveIp(string $host): ?string
        {
            return $this->state['wildcard'];
        }

        public function tlsReport(string $host, ?string $connectIp = null): array
        {
            return $this->state['tlsOk']
                ? ['ok' => true, 'error' => null, 'issuer' => "Let's Encrypt", 'sans' => ['*.example.test'], 'expires_at' => '2027-01-01']
                : ['ok' => false, 'error' => 'certificate covers [TRAEFIK DEFAULT CERT]', 'issuer' => 'TRAEFIK DEFAULT CERT', 'sans' => [], 'expires_at' => null];
        }

        public function httpOk(string $url): bool
        {
            return $this->state['httpOk'];
        }
    });
}

beforeEach(function () {
    // A bare, dotted base domain, so subdomain routing is considered available and the
    // command actually probes rather than reporting a config problem first.
    config(['app.url' => 'https://example.test', 'reseller.domain' => 'example.test']);
});

it('passes when DNS, TLS and HTTP are all healthy', function () {
    fakeProbe();

    $this->artisan('reseller:doctor')
        ->expectsOutputToContain('All checks passed.')
        ->assertSuccessful();
});

it('fails and names the wildcard record when it does not resolve', function () {
    fakeProbe(['wildcard' => null]);

    $this->artisan('reseller:doctor')
        ->expectsOutputToContain('Runbook §12.1')
        ->assertFailed();
});

it('fails when the wildcard points somewhere other than the apex', function () {
    fakeProbe(['wildcard' => '198.51.100.5']);

    $this->artisan('reseller:doctor')->assertFailed();
});

it('blames the missing cert resolver when TLS does not cover the host', function () {
    fakeProbe(['tlsOk' => false]);

    $this->artisan('reseller:doctor')
        ->expectsOutputToContain('Runbook §12.3')
        ->assertFailed();
});

it('probes a named reseller and fails on an unknown slug', function () {
    Role::findOrCreate('reseller', 'web');
    fakeProbe();

    $owner = User::factory()->create();
    $owner->assignRole('reseller');
    Reseller::create([
        'name' => 'Aplus', 'slug' => 'aplus', 'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $this->artisan('reseller:doctor', ['slug' => 'aplus'])
        ->expectsOutputToContain('aplus.example.test')
        ->assertSuccessful();

    $this->artisan('reseller:doctor', ['slug' => 'nobody'])->assertFailed();
});

it('reports the config problem when the base domain cannot mint subdomains', function () {
    config(['app.url' => 'http://localhost/Forever', 'reseller.domain' => 'localhost']);
    fakeProbe(['wildcard' => null, 'apex' => null]);

    // A subdirectory install on a dotless host: the address handed out is /r/{slug},
    // so this must read as configuration, not as broken DNS.
    $this->artisan('reseller:doctor')->assertFailed();
});

it('records its wildcard verdict for the admin banner to read', function () {
    fakeProbe(['wildcard' => null]);

    $this->artisan('reseller:doctor')->assertFailed();
    expect(Cache::get(ResellerHealthProbe::WILDCARD_DEAD_CACHE_KEY))->toBeTrue();

    // Running it again after the record exists clears the warning, rather than leaving
    // it up until the hourly refresh gets round to it.
    fakeProbe();
    $this->artisan('reseller:doctor')->assertSuccessful();
    expect(Cache::get(ResellerHealthProbe::WILDCARD_DEAD_CACHE_KEY))->toBeFalse();
});

it('says nothing on the admin roster until the probe has actually run', function () {
    Role::findOrCreate('super-admin', 'web');
    Cache::forget(ResellerHealthProbe::WILDCARD_DEAD_CACHE_KEY);

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    // No cached verdict means unknown, not broken — and crucially the page must not
    // block on a DNS lookup to find out.
    $this->actingAs($admin)->get('http://localhost/settings/resellers')
        ->assertOk()
        ->assertDontSee('does not resolve');
});

it('backfills a combined name into its parts', function () {
    $owner = User::factory()->create();

    $combined = Memorial::create([
        'user_id' => $owner->id, 'slug' => 'jane-amara-doe', 'title' => 'In Loving Memory of Jane Amara Doe',
        'full_name' => 'Jane Amara Doe', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $mononym = Memorial::create([
        'user_id' => $owner->id, 'slug' => 'prince', 'title' => 'In Loving Memory of Prince',
        'full_name' => 'Prince', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    // getRawOriginal, not the accessor: Memorial parses full_name on read, so the
    // accessor would report a first name that is not actually stored.
    $this->artisan('memorials:backfill-name-split', ['--dry-run' => true])->assertSuccessful();
    expect($combined->fresh()->getRawOriginal('first_name'))->toBeNull();

    $this->artisan('memorials:backfill-name-split')->assertSuccessful();

    expect($combined->fresh()->getRawOriginal('first_name'))->toBe('Jane')
        ->and($combined->fresh()->getRawOriginal('middle_name'))->toBe('Amara')
        ->and($combined->fresh()->getRawOriginal('last_name'))->toBe('Doe')
        // The case the accessor cannot cover: a single token parses to an empty last
        // name, which the edit form rejects as required. Both fields get the token.
        ->and($mononym->fresh()->getRawOriginal('first_name'))->toBe('Prince')
        ->and($mononym->fresh()->getRawOriginal('last_name'))->toBe('Prince');
});

it('leaves a memorial that already has stored name parts alone', function () {
    $owner = User::factory()->create();

    $stored = Memorial::create([
        'user_id' => $owner->id, 'slug' => 'kept', 'title' => 'In Loving Memory of Mary Jane Smith',
        'full_name' => 'Mary Jane Smith', 'first_name' => 'Mary', 'last_name' => 'Smith-Jones',
        'theme' => 'free', 'plan' => 'free', 'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $this->artisan('memorials:backfill-name-split')->assertSuccessful();

    // A deliberately different surname is data, not drift — re-splitting full_name
    // would overwrite it with "Smith".
    expect($stored->fresh()->getRawOriginal('last_name'))->toBe('Smith-Jones');
});
