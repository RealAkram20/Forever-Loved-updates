<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialSpouse extends Model
{
    protected $fillable = ['memorial_id', 'spouse_name', 'marriage_start_year', 'marriage_end_year'];

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }
}
