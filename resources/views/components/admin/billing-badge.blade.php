@props(['reseller'])

@php
    use App\Models\Reseller;

    $days = $reseller->daysUntilRenewal();

    [$styles, $label] = match ($reseller->billingStatus()) {
        Reseller::BILLING_OVERDUE => [
            'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-900/25 dark:text-red-400 dark:ring-red-500/20',
            abs($days).' '.\Illuminate\Support\Str::plural('day', abs($days)).' overdue',
        ],
        Reseller::BILLING_DUE_SOON => [
            'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/25 dark:text-amber-400 dark:ring-amber-500/20',
            $days === 0 ? 'Due today' : 'Due in '.$days.' '.\Illuminate\Support\Str::plural('day', $days),
        ],
        Reseller::BILLING_ACTIVE => [
            'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-900/25 dark:text-green-400 dark:ring-green-500/20',
            'Paid',
        ],
        // Not a failure state — a reseller can be set up well before their contract starts.
        default => [
            'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600/30',
            'Not invoiced',
        ],
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$styles]) }}>{{ $label }}</span>
