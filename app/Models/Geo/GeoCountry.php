<?php

namespace App\Models\Geo;

use Illuminate\Database\Eloquent\Model;

class GeoCountry extends Model
{
    protected $connection = 'geo';
    protected $table = 'countries';
    public $timestamps = false;

    protected $fillable = [];

    public function states()
    {
        return $this->hasMany(GeoState::class, 'country_id');
    }
}
