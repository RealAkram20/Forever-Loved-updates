<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialCoFounder extends Model
{
    protected $fillable = ['memorial_id', 'name', 'sort_order'];

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }
}
