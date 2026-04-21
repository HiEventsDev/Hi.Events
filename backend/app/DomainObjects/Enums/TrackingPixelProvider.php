<?php

namespace HiEvents\DomainObjects\Enums;

enum TrackingPixelProvider: string
{
    use BaseEnum;

    case FACEBOOK_PIXEL = 'facebook_pixel';
    case GOOGLE_ANALYTICS_4 = 'google_analytics_4';
    case GOOGLE_TAG_MANAGER = 'google_tag_manager';
    case TIKTOK_PIXEL = 'tiktok_pixel';

    public function pixelIdPattern(): string
    {
        return match ($this) {
            self::FACEBOOK_PIXEL => '/^\d{9,20}$/',
            self::GOOGLE_ANALYTICS_4 => '/^G-[a-zA-Z0-9]{6,20}$/',
            self::GOOGLE_TAG_MANAGER => '/^GTM-[a-zA-Z0-9]{4,20}$/',
            self::TIKTOK_PIXEL => '/^[a-zA-Z0-9]{6,30}$/',
        };
    }

    public function pixelIdFormatDescription(): string
    {
        return match ($this) {
            self::FACEBOOK_PIXEL => __('Must be 9-20 digits (e.g., 1234567890)'),
            self::GOOGLE_ANALYTICS_4 => __('Must start with G- followed by 6-20 characters (e.g., G-XXXXXXXXXX)'),
            self::GOOGLE_TAG_MANAGER => __('Must start with GTM- followed by 4-20 characters (e.g., GTM-XXXXXXX)'),
            self::TIKTOK_PIXEL => __('Must be 6-30 alphanumeric characters'),
        };
    }
}
