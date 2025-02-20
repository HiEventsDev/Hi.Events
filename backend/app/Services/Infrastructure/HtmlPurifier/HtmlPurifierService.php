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

    public function purify(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        return $this->htmlPurifier->purify($html, $this->config);
    }
}
