<?php

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;

class GeoState extends Model
{
    protected $connection = 'geo';
    protected $table = 'states';
    public $timestamps = false;

    protected $fillable = [];

    public function country()
    {
        return $this->belongsTo(GeoCountry::class, 'country_id');
    }

    public function cities()
    {
        return $this->hasMany(GeoCity::class, 'state_id');
    }
}
