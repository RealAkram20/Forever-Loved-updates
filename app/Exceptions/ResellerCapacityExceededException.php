<?php

namespace App\Exceptions;

use App\Models\Reseller;
use RuntimeException;

/**
 * Thrown by MemorialCreationService when a creation would exceed the reseller tier's
 * included memorial allowance. An exception rather than a return value so that every
 * creation path — reseller intake, the dashboard form, the public wizard — hits the
 * same wall and merely differs in how it phrases the message.
 */
class ResellerCapacityExceededException extends RuntimeException
{
    public function __construct(public readonly Reseller $reseller)
    {
        parent::__construct(sprintf(
            'Reseller "%s" has used all %d memorial profiles included in the %s tier.',
            $reseller->name,
            $reseller->memorialAllowance(),
            $reseller->tier?->name ?? 'current'
        ));
    }

    /** The message shown to the reseller's own staff, who can act on it. */
    public function staffMessage(): string
    {
        return sprintf(
            'You have used all %d memorial profiles included in the %s tier. Contact us to raise your allowance.',
            $this->reseller->memorialAllowance(),
            $this->reseller->tier?->name ?? 'current'
        );
    }

    /** The message shown to a visiting family, who cannot. */
    public function visitorMessage(): string
    {
        return sprintf(
            '%s has reached its memorial allowance. Please contact them directly to continue.',
            $this->reseller->name
        );
    }
}
