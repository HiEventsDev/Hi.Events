<?php

namespace HiEvents\Helper;

class StringHelper
{
    /**
     * Remove control characters and unicode line/paragraph separators from a plain-text value.
     */
    public static function stripControlCharacters(string $text): string
    {
        return preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}]/u', '', $text) ?? $text;
    }

    public static function previewFromHtml(string $text, int $length = 100): string
    {
        $textWithSpaces = preg_replace('/<[^>]+>/', ' ', $text);

        $text = strip_tags($textWithSpaces);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if (strlen($text) > $length) {
            $text = mb_substr($text, 0, $length - 3) . '...';
        }

        return $text;
    }
}
