<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\Helper\Url;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class CampaignHtmlSanitizer
{
    private const PATH_LEAVING_STORAGE = '#\\\\|/\.{1,2}(?:[/?\#]|$)#';

    public function __construct(
        private readonly HtmlPurifierService $purifier,
    ) {}

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return (string) preg_replace_callback(
            '/<img\b[^>]*>/i',
            fn (array $match): string => $this->isOwnImage($match[0]) ? $match[0] : '',
            (string) $this->purifier->purifyWithoutCssImages($html),
        );
    }

    public function countImagesWithoutAlt(?string $html): int
    {
        preg_match_all('/<img\b[^>]*>/i', (string) $html, $matches);

        return count(array_filter($matches[0], static fn (string $tag) => ! preg_match('/\balt\s*=\s*"[^"]*\S[^"]*"/i', $tag)));
    }

    private function isOwnImage(string $tag): bool
    {
        if (! preg_match('/\bsrc\s*=\s*"([^"]+)"/i', $tag, $match)) {
            return false;
        }

        $source = rawurldecode(html_entity_decode($match[1]));
        $base = Url::getCdnBaseUrl();

        return str_starts_with($source, $base) && ! preg_match(self::PATH_LEAVING_STORAGE, '/'.substr($source, strlen($base)));
    }
}
