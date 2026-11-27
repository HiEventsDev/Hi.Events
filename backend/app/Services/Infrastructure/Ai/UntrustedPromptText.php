<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Ai;

class UntrustedPromptText
{
    private const LINK_MAX_LENGTH = 300;

    private const MAX_LINKS = 40;

    public function clip(string $text, int $maxLength): string
    {
        $withoutTags = str_replace(['<', '>'], ['‹', '›'], $text);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $withoutTags)), 0, $maxLength);
    }

    public function htmlToPlainText(string $html, int $maxLength): string
    {
        $withoutHidden = preg_replace('/<(style|script|head)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $withInlineUrls = preg_replace_callback(
            '/<a\b[^>]*\bhref=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is',
            fn (array $anchor): string => strip_tags($anchor[2]).' ('.$this->sanitiseUrl($anchor[1]).')',
            $withoutHidden,
        ) ?? $withoutHidden;

        return $this->clip(html_entity_decode(strip_tags($withInlineUrls), ENT_QUOTES | ENT_HTML5), $maxLength);
    }

    /**
     * @return string[]
     */
    public function extractLinks(string $content): array
    {
        return array_slice($this->everyLinkIn($content), 0, self::MAX_LINKS);
    }

    public function holdsMoreThanIsRead(string $html, int $maxLength): bool
    {
        return mb_strlen($this->htmlToPlainText($html, PHP_INT_MAX)) > $maxLength
            || count($this->everyLinkIn($html)) > self::MAX_LINKS;
    }

    /**
     * @return string[]
     */
    private function everyLinkIn(string $content): array
    {
        preg_match_all('/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $content, $anchors);
        preg_match_all('/\bhttps?:\/\/[^\s"\'<>)]+/i', strip_tags($content), $bare);

        $links = array_map($this->sanitiseUrl(...), [...$anchors[1], ...$bare[0]]);

        return array_values(array_unique(array_filter($links)));
    }

    private function sanitiseUrl(string $url): string
    {
        return mb_substr(trim(str_replace(['<', '>'], '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5))), 0, self::LINK_MAX_LENGTH);
    }
}
