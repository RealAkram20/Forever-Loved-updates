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
        // Rich HTML from Quill editor. sanitize() also drops the empty paragraphs Quill
        // leaves for blank lines — one implementation, shared with stories and comments.
        if (str_contains($biography, '<')) {
            return HtmlHelper::sanitize($biography);
        }
        // Legacy plain text: **bold**, line breaks
        $text = e($biography);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

        // Real paragraphs, not <br><br>: a blank line used to become a full empty line
        // of whitespace, which is why plain-text biographies read with holes between
        // paragraphs. As <p>s, the display CSS decides the gap — one rhythm for every
        // biography, whether it came from Quill or a plain textarea.
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = implode('', array_map(fn ($p) => '<p>'.nl2br($p, false).'</p>', $paragraphs));

        return HtmlHelper::sanitize($html);
    }
}
