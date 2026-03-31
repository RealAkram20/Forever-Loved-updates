<?php

namespace Database\Seeders;

use App\Models\SiteLayout;
use App\Services\SiteLayoutService;
use Illuminate\Database\Seeder;

class SiteLayoutSeeder extends Seeder
{
    public function run(): void
    {
        if (SiteLayout::query()->where('key', SiteLayout::KEY_VISITOR_HOME)->exists()) {
            return;
        }

        $doc = app(SiteLayoutService::class)->defaultHomeDocument();

        SiteLayout::query()->create([
            'key' => SiteLayout::KEY_VISITOR_HOME,
            'version' => $doc['version'],
            'json' => json_encode($doc, JSON_THROW_ON_ERROR),
            'published_at' => now(),
        ]);
    }
}
