<?php

declare(strict_types=1);

namespace Tests\Unit\Enterprise\Crm;

use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignStarterTemplates;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\CampaignTokenCatalog;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampaignStarterTemplatesTest extends TestCase
{
    public static function locales(): array
    {
        return array_map(static fn (string $locale) => [$locale], ['en', 'de', 'fr', 'es', 'ja', 'zh-cn', 'ru', 'se']);
    }

    #[DataProvider('locales')]
    public function test_every_starter_renders_in_every_language_with_known_placeholders_and_fits_its_columns(string $locale): void
    {
        App::setLocale($locale);

        foreach ((new CampaignStarterTemplates)->all() as $starter) {
            $this->assertNotSame('', $starter->name, $starter->key);
            $this->assertLessThanOrEqual(200, mb_strlen($starter->subject), $starter->key);
            $this->assertLessThanOrEqual(150, mb_strlen($starter->preheader), $starter->key);
            $this->assertSame([], CampaignTokenCatalog::unknown($starter->subject.' '.$starter->preheader.' '.$starter->body, false), $starter->key);
            $this->assertStringStartsWith('<', $starter->body, $starter->key);
        }

        App::setLocale('en');
    }
}
