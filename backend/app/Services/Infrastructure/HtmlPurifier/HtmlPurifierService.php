<?php

namespace HiEvents\Services\Infrastructure\HtmlPurifier;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

    public function purifyPreservingLiquid(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $prefix = 'LQ'.Str::lower(Str::random(12));
        $tokens = [];
        $protected = preg_replace_callback(
            '/\{\{[\s\S]*?\}\}|\{%[\s\S]*?%\}/',
            static function (array $matches) use (&$tokens, $prefix): string {
                $placeholder = $prefix.count($tokens).'X';
                $tokens[$placeholder] = $matches[0];

                return $placeholder;
            },
            $html,
        );

        if ($protected === null) {
            return $this->purify($html);
        }

        $purified = $this->htmlPurifier->purify($protected, $this->config);

        return strtr($purified, $tokens);
    }
}
