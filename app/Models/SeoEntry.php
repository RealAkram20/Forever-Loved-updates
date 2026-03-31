<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoEntry extends Model
{
    protected $fillable = [
        'route_key',
        'label',
        'meta_title',
        'meta_description',
        'og_image',
    ];

    /**
     * @return array<string, string>
     */
    public static function routeLabels(): array
    {
        return [
            'home' => 'Home',
            'pricing' => 'Pricing',
            'about' => 'About',
            'privacy-policy' => 'Privacy Policy',
            'terms-of-use' => 'Terms of Use',
            'contact' => 'Contact',
            'memorial.directory' => 'Find Memorial',
        ];
    }
}
