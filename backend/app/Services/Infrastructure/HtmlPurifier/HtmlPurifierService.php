<?php

namespace HiEvents\Services\Infrastructure\HtmlPurifier;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlPurifierService
{
    public function __construct(
        private readonly HTMLPurifier $htmlPurifier
    )
    {
    }

    public function purify(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', base_path('storage/framework/cache'));

        return $this->htmlPurifier->purify($html);
    }
}
