<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLayout extends Model
{
    public const KEY_VISITOR_HOME = 'visitor_home';

    protected $fillable = [
        'key',
        'version',
        'json',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, array{type: string, props: array<string, mixed>}>
     */
    public function blocksArray(): array
    {
        if ($this->json === null || $this->json === '') {
            return [];
        }
        $decoded = json_decode($this->json, true);
        if (! is_array($decoded)) {
            return [];
        }
        if (isset($decoded['blocks']) && is_array($decoded['blocks'])) {
            return $decoded['blocks'];
        }

        return [];
    }

    public static function findPublished(string $key): ?self
    {
        return static::query()->where('key', $key)->whereNotNull('published_at')->first()
            ?? static::query()->where('key', $key)->first();
    }
}
