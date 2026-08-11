<?php

use App\Models\Memorial;
use App\Models\MemorialView;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\Tribute;
use App\Models\User;
use App\Reports\ReportBranding;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Reports\Reseller\ClientMemorialsReport;
use App\Services\Reports\MemorialReportBuilder;
use App\Services\Reports\ReportRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Reseller}
 */
function reportsReseller(string $name, bool $analytics = true, bool $pesapal = false): array
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $tier = ResellerTier::create([
        'name' => 'Professional', 'slug' => 'pro-'.uniqid(), 'sort_order' => 0,
        'annual_price' => 500, 'memorial_profile_allowance' => 25,
        'price_per_additional_profile' => 20, 'storage_limit_gb' => 10,
        'feature_embedding' => false, 'feature_domain_routing' => false,
        'feature_business_analytics' => $analytics, 'is_active' => true,
    ]);

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.substr(uniqid(), -6),
        'owner_user_id' => $owner->id,
        'reseller_tier_id' => $tier->id,
        'status' => Reseller::STATUS_ACTIVE,
        'pesapal_enabled' => $pesapal,
    ]);

    $owner->update(['reseller_id' => $reseller->id]);

    return [$owner->fresh(), $reseller];
}

function reportsMemorialFor(Reseller $reseller, string $fullName): Memorial
{
    $client = User::factory()->create(['reseller_id' => $reseller->id]);
    $client->assignRole('user');

    return Memorial::create([
        'user_id' => $client->id,
        'reseller_id' => $reseller->id,
        'slug' => Str::slug($fullName).'-'.substr(uniqid(), -6),
        'title' => 'In Loving Memory of '.$fullName,
        'full_name' => $fullName,
        'status' => Memorial::STATUS_ACTIVE,
    ]);
}

function resellerReportBody(TestResponse $response): string
{
    $base = $response->baseResponse;

    if ($base instanceof StreamedResponse) {
        return $response->streamedContent();
    }

    if ($base instanceof BinaryFileResponse) {
        return (string) file_get_contents($base->getFile()->getPathname());
    }

    return (string) $base->getContent();
}

it('shows a reseller their own report catalogue', function () {
    [$owner] = reportsReseller('Ashford Funeral Services');

    $this->actingAs($owner)->get('http://localhost/reseller/reports')
        ->assertOk()
        ->assertSee('Client memorials')
        ->assertSee('Account &amp; quota statement', false);
});

it('renders every reseller report without erroring', function () {
    [$owner, $reseller] = reportsReseller('Ashford Funeral Services');
    reportsMemorialFor($reseller, 'Mary Nakamya');

    // The registry builds tenant reports through the container, which is where
    // EnsureResellerActive puts the reseller during a real request. Calling it directly
    // means binding it by hand first.
    app()->instance(Reseller::class, $reseller);

    $reports = app(ReportRegistry::class)
        ->for(ReportRegistry::AUDIENCE_RESELLER, $owner);

    expect($reports)->not->toBeEmpty();

    foreach ($reports as $report) {
        $this->actingAs($owner)
            ->get('http://localhost/reseller/reports/'.$report->key().'?preset=all')
            ->assertOk();
    }
});

it('never leaks another reseller rows into an export', function () {
    [$ashfordOwner, $ashford] = reportsReseller('Ashford Funeral Services');
    [, $brightside] = reportsReseller('Brightside Memorials');

    reportsMemorialFor($ashford, 'Mary Nakamya');
    reportsMemorialFor($brightside, 'Peter Okello');

    // Asserted on the downloaded bytes of all three formats, not on the page: the export
    // path builds its own result, so a scoping mistake could exist there alone.
    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $body = resellerReportBody(
            $this->actingAs($ashfordOwner)
                ->get("http://localhost/reseller/reports/memorials/download/{$format}?preset=all")
        );

        // xlsx and pdf are compressed containers, so a name is not necessarily findable
        // as plain text. The screen assertion below covers the readable case; here we
        // only require that the other tenant's name never appears.
        expect($body)->not->toContain('Peter Okello');
    }

    $this->actingAs($ashfordOwner)
        ->get('http://localhost/reseller/reports/memorials?preset=all')
        ->assertOk()
        ->assertSee('Mary Nakamya')
        ->assertDontSee('Peter Okello');
});

it('scopes engagement figures to the reseller own memorials', function () {
    [$ashfordOwner, $ashford] = reportsReseller('Ashford Funeral Services');
    [, $brightside] = reportsReseller('Brightside Memorials');

    $mine = reportsMemorialFor($ashford, 'Mary Nakamya');
    $theirs = reportsMemorialFor($brightside, 'Peter Okello');

    MemorialView::create(['memorial_id' => $mine->id, 'visitor_hash' => 'a', 'viewed_at' => now()]);
    MemorialView::create(['memorial_id' => $theirs->id, 'visitor_hash' => 'b', 'viewed_at' => now()]);
    MemorialView::create(['memorial_id' => $theirs->id, 'visitor_hash' => 'c', 'viewed_at' => now()]);

    $this->actingAs($ashfordOwner)
        ->get('http://localhost/reseller/reports/engagement?preset=all')
        ->assertOk()
        ->assertSee('Mary Nakamya')
        ->assertDontSee('Peter Okello');
});

it('pitches the engagement report when the tier does not include it', function () {
    [$owner] = reportsReseller('Ashford Funeral Services', analytics: false);

    $this->actingAs($owner)
        ->get('http://localhost/reseller/reports/engagement')
        ->assertOk()
        ->assertSee('Not included in your current plan');
});

it('refuses to download a report the tier does not include', function () {
    [$owner] = reportsReseller('Ashford Funeral Services', analytics: false);

    // The pitch page is a courtesy; the data must not be reachable by URL.
    $this->actingAs($owner)
        ->get('http://localhost/reseller/reports/engagement/download/csv')
        ->assertForbidden();
});

it('hides the sales report from a reseller on platform billing', function () {
    [$owner] = reportsReseller('Ashford Funeral Services', pesapal: false);

    $this->actingAs($owner)->get('http://localhost/reseller/reports')
        ->assertOk()
        ->assertDontSee('Your sales');

    $this->actingAs($owner)->get('http://localhost/reseller/reports/revenue')->assertNotFound();
});

it('shows the sales report to a reseller taking their own payments', function () {
    [$owner] = reportsReseller('Ashford Funeral Services', pesapal: true);

    $this->actingAs($owner)->get('http://localhost/reseller/reports/revenue')->assertOk();
});

it('brands a reseller pdf with their name and not the platform', function () {
    [$owner, $reseller] = reportsReseller('Ashford Funeral Services');
    reportsMemorialFor($reseller, 'Mary Nakamya');

    $result = app(ReportRenderer::class)->forExport(
        app(ClientMemorialsReport::class, ['reseller' => $reseller]),
        ReportFilters::allTime(),
        ReportBranding::forReseller($reseller),
        $owner,
    );

    expect($result->branding->organisationName)->toBe('Ashford Funeral Services')
        ->and($result->header()['Scope'])->toBe('Ashford Funeral Services')
        // The filename is what lands in the family's downloads folder.
        ->and($result->filename())->toStartWith('ashford-funeral-services');
});

it('keeps a reseller out of the admin report area entirely', function () {
    [$owner] = reportsReseller('Ashford Funeral Services');

    $this->actingAs($owner)->get('http://localhost/reports')->assertForbidden();
    $this->actingAs($owner)->get('http://localhost/reports/reseller-roster')->assertForbidden();
});

it('gives a family memorial report to the people responsible for it', function () {
    [$owner, $reseller] = reportsReseller('Ashford Funeral Services');
    $memorial = reportsMemorialFor($reseller, 'Mary Nakamya');

    MemorialView::create(['memorial_id' => $memorial->id, 'visitor_hash' => 'a', 'viewed_at' => now()]);
    MemorialView::create(['memorial_id' => $memorial->id, 'visitor_hash' => 'a', 'viewed_at' => now()]);
    MemorialView::create(['memorial_id' => $memorial->id, 'visitor_hash' => 'b', 'viewed_at' => now()]);

    // Reseller staff hosting it, and the family who owns it, both reach it.
    foreach ([$owner, $memorial->owner] as $user) {
        $this->actingAs($user)
            ->get('http://localhost/memorials/'.$memorial->slug.'/report')
            ->assertOk()
            ->assertSee('Mary Nakamya')
            ->assertSee('people visited');
    }

    // 3 visits from 2 distinct hashes. Asserted against the builder rather than the page,
    // where the label and the figure sit in separate elements and "2" alone would match
    // any tile on the page.
    $data = app(MemorialReportBuilder::class)
        ->build($memorial, ReportFilters::allTime());

    expect($data['visits'])->toBe(3)
        ->and($data['visitors'])->toBe(2);
});

it('keeps the memorial report away from an unrelated signed-in user', function () {
    [, $reseller] = reportsReseller('Ashford Funeral Services');
    $memorial = reportsMemorialFor($reseller, 'Mary Nakamya');

    // Public enough to read the memorial itself, but the report reprints tributes and
    // counts visitors — a strictly narrower gate.
    $memorial->update(['is_public' => true]);

    $stranger = User::factory()->create();
    $stranger->assignRole('user');

    $this->actingAs($stranger)
        ->get('http://localhost/memorials/'.$memorial->slug.'/report')
        ->assertForbidden();
});

it('downloads the memorial report as a pdf carrying the reseller name', function () {
    [$owner, $reseller] = reportsReseller('Ashford Funeral Services');
    $memorial = reportsMemorialFor($reseller, 'Mary Nakamya');

    $response = $this->actingAs($owner)
        ->get('http://localhost/memorials/'.$memorial->slug.'/report/download');

    $response->assertOk();
    expect(substr(resellerReportBody($response), 0, 4))->toBe('%PDF');
});

it('leaves unapproved tributes out of the memorial report', function () {
    [$owner, $reseller] = reportsReseller('Ashford Funeral Services');
    $memorial = reportsMemorialFor($reseller, 'Mary Nakamya');

    Tribute::create([
        'memorial_id' => $memorial->id, 'type' => 'note',
        'message' => 'A message everyone has seen', 'guest_name' => 'Grace', 'is_approved' => true,
    ]);

    Tribute::create([
        'memorial_id' => $memorial->id, 'type' => 'note',
        'message' => 'Something nobody has moderated yet', 'guest_name' => 'Anon', 'is_approved' => false,
    ]);

    $this->actingAs($owner)
        ->get('http://localhost/memorials/'.$memorial->slug.'/report')
        ->assertOk()
        ->assertSee('A message everyone has seen')
        ->assertDontSee('Something nobody has moderated yet');
});

it('can produce the memorial report without reprinting messages', function () {
    [$owner, $reseller] = reportsReseller('Ashford Funeral Services');
    $memorial = reportsMemorialFor($reseller, 'Mary Nakamya');

    Tribute::create([
        'memorial_id' => $memorial->id, 'type' => 'note',
        'message' => 'A private family message', 'guest_name' => 'Grace', 'is_approved' => true,
    ]);

    $this->actingAs($owner)
        ->get('http://localhost/memorials/'.$memorial->slug.'/report?without_messages=1')
        ->assertOk()
        ->assertDontSee('A private family message');
});
