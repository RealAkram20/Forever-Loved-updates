<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LoginCode extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'used'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used' => 'boolean',
        ];
    }

    /**
     * Issue a code, retiring any the address already has outstanding.
     *
     * Verification looks a code up by (email, code), so every unused code was independently
     * valid until it expired. Requesting more simply widened the target: fifteen minutes of
     * repeated requests left dozens of live six-digit codes on one address, and a guess only
     * had to match any one of them. One address, one live code.
     */
    public static function generate(string $email): self
    {
        $email = strtolower($email);

        self::where('email', $email)->where('used', false)->update(['used' => true]);

        return self::create([
            'email' => $email,
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function isValid(): bool
    {
        return !$this->used && $this->expires_at->isFuture();
    }

    public function markUsed(): void
    {
        $this->update(['used' => true]);
    }
}
