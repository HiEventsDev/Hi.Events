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

        // For testing I'm allowing all iframe sources, it's safer to lock it down to expected URLs
        $this->config->set('HTML.AllowedElements', 'p,b,i,u,s,strong,em,li,ul,ol,br,span,img,a,iframe');
        $this->config->set('HTML.SafeIframe', true);
        $this->config->set('URI.SafeIframeRegexp', '/.*/');
    }

    public function purify(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        return $this->htmlPurifier->purify($html, $this->config);
    }
}
