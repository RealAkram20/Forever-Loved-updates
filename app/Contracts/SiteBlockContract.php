<?php

namespace App\Contracts;

interface SiteBlockContract
{
    public static function type(): string;

    public static function label(): string;

    public static function category(): string;

    /**
     * @return array<string, mixed>
     */
    public static function defaultProps(): array;

    /**
     * Laravel validation rules for props (flat keys).
     *
     * @return array<string, mixed>
     */
    public static function rules(): array;

    public static function viewName(): string;
}
