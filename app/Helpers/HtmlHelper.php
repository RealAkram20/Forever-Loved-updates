<?php

namespace App\Helpers;

class HtmlHelper
{
    /** Allowed HTML tags for rich text content (Quill output). */
    protected static array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'ul', 'ol', 'li', 'blockquote', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'span', 'div', 'img', 'pre', 'code',
    ];

    public static function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }
        return strip_tags($html, '<' . implode('><', self::$allowedTags) . '>');
    }
}
