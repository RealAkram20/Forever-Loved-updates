<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialView extends Model
{
    public $timestamps = false;

    protected $fillable = ['memorial_id', 'visitor_hash', 'viewed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }
}
