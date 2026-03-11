<?php

namespace App\Helpers;

class BiographyFormatter
{
    /**
     * Format biography for display.
     * - If content contains HTML (from Quill editor): sanitize and return.
     * - Otherwise: convert **bold** to HTML, preserve line breaks (legacy plain text).
     */
    public static function format(?string $biography): string
    {
        if (empty(trim($biography ?? ''))) {
            return '';
        }
        // Rich HTML from Quill editor - sanitize only
        if (str_contains($biography, '<')) {
            return HtmlHelper::sanitize($biography);
        }
        // Legacy plain text: **bold**, line breaks
        $text = e($biography);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
        $text = nl2br($text, false);
        return HtmlHelper::sanitize($text);
    }
}
