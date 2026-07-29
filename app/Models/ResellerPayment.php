<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment the reseller made to the platform. The tier name, annual price and overage
 * are snapshotted onto each row so a past payment still explains itself after the tier
 * it was priced from has been edited or deleted.
 */
class ResellerPayment extends Model
{
    use HasFactory;

    public const METHOD_MANUAL = 'manual';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_PESAPAL = 'pesapal';

    protected $fillable = [
        'reseller_id',
        'amount',
        'currency',
        'period_start',
        'period_end',
        'tier_name',
        'tier_annual_price',
        'overage_profiles',
        'overage_amount',
        'method',
        'reference',
        'notes',
        'paid_at',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tier_annual_price' => 'decimal:2',
            'overage_amount' => 'decimal:2',
            'overage_profiles' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public static function methods(): array
    {
        return [
            self::METHOD_TRANSFER => 'Bank transfer',
            self::METHOD_MANUAL => 'Other / manual',
            self::METHOD_PESAPAL => 'Pesapal',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
