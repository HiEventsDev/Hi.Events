<?php

namespace HiEvents\Services\Infrastructure\HtmlPurifier;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\File;

class HtmlPurifierService
{
    private const URI_DEFINITION_ID = 'hievents.uri';

    private const URI_DEFINITION_REV = 1;

    private HTMLPurifier_Config $config;

    public function __construct(private readonly HTMLPurifier $htmlPurifier)
    {
        $this->config = HTMLPurifier_Config::createDefault();

        $cachePath = storage_path('app/htmlpurifier');
        File::ensureDirectoryExists($cachePath, 0755);

        $this->config->set('Cache.SerializerPath', $cachePath);
        $this->config->set('HTML.Nofollow', true);
        $this->config->set('HTML.TargetBlank', true);
        $this->config->set('URI.DefinitionID', self::URI_DEFINITION_ID);
        $this->config->set('URI.DefinitionRev', self::URI_DEFINITION_REV);

        $this->config->maybeGetRawURIDefinition()?->addFilter(new LiquidTokenUriFilter, $this->config);
    }

    public function purify(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        return $this->htmlPurifier->purify($html, $this->config);
    }
}
