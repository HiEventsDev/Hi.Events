<?php

namespace HiEvents\Services\Infrastructure\HtmlPurifier;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

class HtmlPurifierService
{
    private const ENCODED_LIQUID_TOKEN = '/%7B%7B((?:%20|%7C|[A-Za-z0-9_.\-])*)%7D%7D/i';

    private const DECODABLE = [
        '%20' => ' ',
        '%7C' => '|',
        '%7c' => '|',
    ];

    private HTMLPurifier_Config $config;

    public function __construct(private readonly HTMLPurifier $htmlPurifier)
    {
        $this->config = HTMLPurifier_Config::createDefault();

        $cachePath = storage_path('app/htmlpurifier');
        File::ensureDirectoryExists($cachePath, 0755);

        $this->config->set('Cache.SerializerPath', $cachePath);
        $this->config->set('HTML.Nofollow', true);
        $this->config->set('HTML.TargetBlank', true);
    }

    public function purify(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return preg_replace_callback(
            self::ENCODED_LIQUID_TOKEN,
            static fn (array $matches): string => '{{'.strtr($matches[1], self::DECODABLE).'}}',
            $this->htmlPurifier->purify($html, $this->config),
        );
    }
}
