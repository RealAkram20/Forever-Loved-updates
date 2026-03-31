<?php

namespace App\PageBuilder\Contracts;

interface PageWidgetContract
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

    /**
     * Field definitions for the admin builder (Alpine property panel).
     * Each entry may include a 'tab' key: 'content' (default), 'style', or 'advanced'.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fieldSchema(): array;

    /**
     * Prop names whose values should be shown as canvas preview text.
     * Values are joined, HTML-stripped, and truncated automatically.
     * Return an empty array to fall back to the widget label.
     *
     * @return list<string>
     */
    public static function previewFields(): array;
}
