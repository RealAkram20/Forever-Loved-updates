<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResellerTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'annual_price',
        'memorial_profile_allowance',
        'price_per_additional_profile',
        'storage_limit_gb',
        'feature_embedding',
        'feature_domain_routing',
        'feature_business_analytics',
        'feature_page_builder',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_price' => 'decimal:2',
            'memorial_profile_allowance' => 'integer',
            'price_per_additional_profile' => 'decimal:2',
            'storage_limit_gb' => 'integer',
            'feature_embedding' => 'boolean',
            'feature_domain_routing' => 'boolean',
            'feature_business_analytics' => 'boolean',
            'feature_page_builder' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function resellers(): HasMany
    {
        return $this->hasMany(Reseller::class);
    }
}
