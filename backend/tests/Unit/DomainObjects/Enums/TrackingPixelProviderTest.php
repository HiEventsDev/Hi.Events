<?php

namespace Tests\Unit\DomainObjects\Enums;

use HiEvents\DomainObjects\Enums\TrackingPixelProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrackingPixelProviderTest extends TestCase
{
    #[DataProvider('validPixelIdProvider')]
    public function testValidPixelIdsPassValidation(TrackingPixelProvider $provider, string $pixelId): void
    {
        $this->assertMatchesRegularExpression($provider->pixelIdPattern(), $pixelId);
    }

    #[DataProvider('invalidPixelIdProvider')]
    public function testInvalidPixelIdsFailValidation(TrackingPixelProvider $provider, string $pixelId): void
    {
        $this->assertDoesNotMatchRegularExpression($provider->pixelIdPattern(), $pixelId);
    }

    public static function validPixelIdProvider(): array
    {
        return [
            'facebook 10 digits' => [TrackingPixelProvider::FACEBOOK_PIXEL, '1234567890'],
            'facebook 15 digits' => [TrackingPixelProvider::FACEBOOK_PIXEL, '123456789012345'],
            'ga4 standard' => [TrackingPixelProvider::GOOGLE_ANALYTICS_4, 'G-ABC1234567'],
            'ga4 short' => [TrackingPixelProvider::GOOGLE_ANALYTICS_4, 'G-ABCDEF'],
            'gtm standard' => [TrackingPixelProvider::GOOGLE_TAG_MANAGER, 'GTM-ABCDEF'],
            'gtm short' => [TrackingPixelProvider::GOOGLE_TAG_MANAGER, 'GTM-ABCD'],
            'tiktok standard' => [TrackingPixelProvider::TIKTOK_PIXEL, 'ABCDEF123456'],
            'tiktok short' => [TrackingPixelProvider::TIKTOK_PIXEL, 'ABC123'],
        ];
    }

    public static function invalidPixelIdProvider(): array
    {
        return [
            'facebook too short' => [TrackingPixelProvider::FACEBOOK_PIXEL, '12345678'],
            'facebook with letters' => [TrackingPixelProvider::FACEBOOK_PIXEL, '123456789a'],
            'facebook with script' => [TrackingPixelProvider::FACEBOOK_PIXEL, '<script>alert(1)</script>'],
            'ga4 missing prefix' => [TrackingPixelProvider::GOOGLE_ANALYTICS_4, 'ABC1234567'],
            'ga4 wrong prefix' => [TrackingPixelProvider::GOOGLE_ANALYTICS_4, 'UA-1234567'],
            'ga4 with special chars' => [TrackingPixelProvider::GOOGLE_ANALYTICS_4, 'G-ABC<script>'],
            'gtm missing prefix' => [TrackingPixelProvider::GOOGLE_TAG_MANAGER, 'ABCDEF'],
            'gtm too short' => [TrackingPixelProvider::GOOGLE_TAG_MANAGER, 'GTM-AB'],
            'gtm with injection' => [TrackingPixelProvider::GOOGLE_TAG_MANAGER, 'GTM-";alert(1)//'],
            'tiktok too short' => [TrackingPixelProvider::TIKTOK_PIXEL, 'ABC'],
            'tiktok with special chars' => [TrackingPixelProvider::TIKTOK_PIXEL, 'ABC!@#$%^'],
        ];
    }
}
