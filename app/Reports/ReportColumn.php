<?php

namespace App\Reports;

/**
 * One column of a report.
 *
 * Carries a *semantic* type rather than a pre-formatted string, because the three
 * exporters need genuinely different things from the same value: the screen and the PDF
 * want "UGX 50,000", while Excel wants the number 50000 with a currency cell format so the
 * reseller's accountant can SUM() the column without cleaning it first.
 */
final class ReportColumn
{
    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_MONEY = 'money';

    public const TYPE_PERCENT = 'percent';

    public const TYPE_DATE = 'date';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_BOOL = 'bool';

    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TYPE_TEXT,
        /**
         * For money columns: the name of another key in the same row holding that row's
         * ISO currency code. Money is never assumed to be in one currency — payment_orders
         * and reseller_payments both store it per row.
         */
        public readonly ?string $currencyKey = null,
        /** PDF column width hint, e.g. '12%'. Null lets the table auto-size. */
        public readonly ?string $width = null,
        /** Dropped from PDF output when the table is too wide to be readable. */
        public readonly bool $secondary = false,
    ) {}

    public static function text(string $key, string $label, ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_TEXT, null, $width, $secondary);
    }

    public static function number(string $key, string $label, ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_NUMBER, null, $width, $secondary);
    }

    public static function money(string $key, string $label, ?string $currencyKey = 'currency', ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_MONEY, $currencyKey, $width, $secondary);
    }

    public static function percent(string $key, string $label, ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_PERCENT, null, $width, $secondary);
    }

    public static function date(string $key, string $label, ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_DATE, null, $width, $secondary);
    }

    public static function datetime(string $key, string $label, ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_DATETIME, null, $width, $secondary);
    }

    public static function bool(string $key, string $label, ?string $width = null, bool $secondary = false): self
    {
        return new self($key, $label, self::TYPE_BOOL, null, $width, $secondary);
    }

    /**
     * Numbers right-align so their digits line up and a column can be scanned for
     * magnitude; everything else reads from the left.
     */
    public function align(): string
    {
        return in_array($this->type, [self::TYPE_NUMBER, self::TYPE_MONEY, self::TYPE_PERCENT], true)
            ? 'right'
            : 'left';
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, [self::TYPE_NUMBER, self::TYPE_MONEY, self::TYPE_PERCENT], true);
    }
}
