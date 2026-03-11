<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemorialEducation extends Model
{
    protected $table = 'memorial_education';

    protected $fillable = ['memorial_id', 'institution_name', 'start_year', 'end_year', 'degree'];

    public function memorial(): BelongsTo
    {
        return $this->belongsTo(Memorial::class);
    }
}
