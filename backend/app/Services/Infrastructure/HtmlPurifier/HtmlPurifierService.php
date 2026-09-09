<?php

namespace HiEvents\Services\Infrastructure\HtmlPurifier;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

class HtmlPurifierService
{
    private HTMLPurifier_Config $config;

    public function __construct(private readonly HTMLPurifier $htmlPurifier)
    {
        $this->config = HTMLPurifier_Config::createDefault();

        $cachePath = storage_path('app/htmlpurifier');
        File::ensureDirectoryExists($cachePath, 0755);

        $this->config->set('Cache.SerializerPath', $cachePath);
    }

    public function purify(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return $this->htmlPurifier->purify($html, $this->config);
    }

    /**
     * Purify HTML while preserving Liquid template tokens such as
     * `{{ order.number }}` and `{% if ... %}` so URI encoding does not turn
     * them into `%7B%7B...%7D%7D` inside hrefs and other attributes.
     */
    public function purifyPreservingLiquid(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $tokens = [];
        $protected = preg_replace_callback(
            '/\{\{[\s\S]*?\}\}|\{%[\s\S]*?%\}/',
            static function (array $matches) use (&$tokens): string {
                $placeholder = 'LIQUIDTOKEN'.count($tokens).'X';
                $tokens[$placeholder] = $matches[0];

                return $placeholder;
            },
            $html,
        );

        $purified = $this->htmlPurifier->purify($protected, $this->config);

        return strtr($purified, $tokens);
    }
}
