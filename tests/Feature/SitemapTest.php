<?php

use App\Models\Memorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('lists public memorials and keeps private ones out', function () {
    Cache::forget('sitemap.xml');

    $public = Memorial::factory()->create(['is_public' => true]);
    $private = Memorial::factory()->create(['is_public' => false]);
    $suspended = Memorial::factory()->create(['is_public' => true, 'status' => 'suspended']);

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)->toContain('/'.$public->slug)
        ->and($xml)->toContain(route('memorial.directory'))
        ->and($xml)->not->toContain('/'.$private->slug)
        ->and($xml)->not->toContain('/'.$suspended->slug);
});
