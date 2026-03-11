<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialShare extends Model
{
    public $timestamps = false;

    protected $fillable = ['memorial_id', 'visitor_hash', 'share_type', 'shared_at'];

    protected function casts(): array
    {
        return ['shared_at' => 'datetime'];
    }

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }
}
