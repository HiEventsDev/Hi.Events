<?php

namespace HiEvents\Helper;

class StringHelper
{
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
