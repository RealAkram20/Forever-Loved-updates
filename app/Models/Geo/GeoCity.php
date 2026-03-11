<?php

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;

class GeoCity extends Model
{
    protected $connection = 'geo';
    protected $table = 'cities';
    public $timestamps = false;

    protected $fillable = [];

    public function state()
    {
        return $this->belongsTo(GeoState::class, 'state_id');
    }
}
