<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

class CampaignButtonStyler
{
    public const BUTTON_CLASS = 'he-button';

    public const COLOR = '#40296c';

    public function style(string $html): string
    {
        return (string) preg_replace_callback(
            '/<a\b([^>]*\bclass="[^"]*\b'.self::BUTTON_CLASS.'\b[^"]*"[^>]*)>(.*?)<\/a>/is',
            static function (array $match): string {
                if (! preg_match('/\bhref="([^"]*)"/i', $match[1], $href)) {
                    return $match[0];
                }

                $label = trim(strip_tags($match[2]));

                return '<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 16px 0;"><tr>'
                    .'<td style="border-radius: 8px; background-color: '.self::COLOR.';">'
                    .'<a href="'.$href[1].'" style="display: inline-block; padding: 12px 24px; color: #ffffff; font-weight: 600; text-decoration: none; border-radius: 8px;">'
                    .$label
                    .'</a></td></tr></table>';
            },
            $html,
        );
    }
}
