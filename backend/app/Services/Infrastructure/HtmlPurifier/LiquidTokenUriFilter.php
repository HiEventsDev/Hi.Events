<?php

namespace HiEvents\Services\Infrastructure\HtmlPurifier;

use HTMLPurifier_URI;
use HTMLPurifier_URIFilter;

class LiquidTokenUriFilter extends HTMLPurifier_URIFilter
{
    private const ENCODED_TOKEN = '/%7B%7B((?:%20|%7C|[A-Za-z0-9_.\-])*)%7D%7D/i';

    private const DECODABLE = [
        '%20' => ' ',
        '%7C' => '|',
        '%7c' => '|',
    ];

    public $name = 'LiquidToken';

    public $post = true;

    /**
     * @param  HTMLPurifier_URI  $uri
     */
    public function filter(&$uri, $config, $context): bool
    {
        foreach (['path', 'query', 'fragment'] as $component) {
            if ($uri->$component === null) {
                continue;
            }

            $uri->$component = preg_replace_callback(
                self::ENCODED_TOKEN,
                static fn (array $matches): string => '{{'.strtr($matches[1], self::DECODABLE).'}}',
                $uri->$component,
            );
        }

        return true;
    }
}
