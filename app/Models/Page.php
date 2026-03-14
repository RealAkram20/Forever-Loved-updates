<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'meta_description',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public static function getBySlug(string $slug): ?self
    {
        return Cache::remember("page.{$slug}", 3600, function () use ($slug) {
            return static::where('slug', $slug)->first();
        });
    }

    public static function clearSlugCache(string $slug): void
    {
        Cache::forget("page.{$slug}");
    }
}
