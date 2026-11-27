<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignTokenCatalogTest extends TestCase
{
    public static function unknownPlaceholders(): array
    {
        return [
            'typo' => ['{{ fist_name }}', false, ['fist_name']],
            'event without an event' => ['{{ event_title }}', false, ['event_title']],
            'event with an event' => ['{{ event_title }} {{ event_date }}', true, []],
            'filters are fine' => ['{{ first_name | default: "there" }} {{last_name}}', false, []],
            'encoded quotes are fine' => ['{{ last_event_title | default: &quot;our show&quot; }}', false, []],
            'empty braces' => ['{{ }}', false, ['']],
        ];
    }

    #[DataProvider('unknownPlaceholders')]
    public function test_unknown_placeholders_are_found(string $text, bool $hasEvent, array $expected): void
    {
        $this->assertSame($expected, CampaignTokenCatalog::unknown($text, $hasEvent));
    }

    public function test_a_close_misspelling_gets_a_suggestion_and_nonsense_does_not(): void
    {
        $this->assertSame('first_name', CampaignTokenCatalog::suggestion('firstname', false));
        $this->assertSame('last_event_title', CampaignTokenCatalog::suggestion('last_event_titel', false));
        $this->assertNull(CampaignTokenCatalog::suggestion('favourite_colour', false));
    }
}
