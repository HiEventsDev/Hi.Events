<?php

namespace HiEvents\Services\Application\Handlers\Organizer\DTO;

use HiEvents\DataTransferObjects\AddressDTO;
use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class PartialUpdateOrganizerSettingsDTO extends BaseDataObject
{
    public function __construct(
        public readonly int                                           $organizerId,
        public readonly string                                        $accountId,

        // Event defaults
        #[WithCast(EnumCast::class, AttendeeDetailsCollectionMethod::class)]
        public readonly AttendeeDetailsCollectionMethod|Optional|null $defaultAttendeeDetailsCollectionMethod,
        public readonly bool|Optional|null                            $defaultShowMarketingOptIn,
        public readonly bool|Optional|null                            $defaultPassPlatformFeeToBuyer,
        public readonly bool|Optional|null                            $defaultAllowAttendeeSelfEdit,

        // Social
        public readonly string|Optional|null                          $facebookHandle,
        public readonly string|Optional|null                          $instagramHandle,
        public readonly string|Optional|null                          $twitterHandle,
        public readonly string|Optional|null                          $linkedinHandle,
        public readonly string|Optional|null                          $discordHandle,
        public readonly string|Optional|null                          $tiktokHandle,
        public readonly string|Optional|null                          $youtubeHandle,
        public readonly string|Optional|null                          $snapchatHandle,
        public readonly string|Optional|null                          $twitchHandle,
        public readonly string|Optional|null                          $redditHandle,
        public readonly string|Optional|null                          $pinterestHandle,
        public readonly string|Optional|null                          $whatsappHandle,
        public readonly string|Optional|null                          $telegramHandle,
        public readonly string|Optional|null                          $vkHandle,
        public readonly string|Optional|null                          $weiboHandle,
        public readonly string|Optional|null                          $wechatHandle,
        public readonly string|Optional|null                          $flickrHandle,
        public readonly string|Optional|null                          $tumblrHandle,
        public readonly string|Optional|null                          $quoraHandle,
        public readonly string|Optional|null                          $vimeoHandle,
        public readonly string|Optional|null                          $githubHandle,

        // Website
        public readonly string|Optional|null                          $websiteUrl,

        // Location details
        public readonly AddressDTO|Optional|null                      $locationDetails,

        // Homepage settings
        public readonly OrganizerHomepageVisibility|Optional|null     $homepageVisibility,

        // Simplified homepage theme settings
        public readonly array|Optional|null                           $homepageThemeSettings,

        // SEO
        public readonly string|Optional|null                          $seoKeywords,
        public readonly string|Optional|null                          $seoTitle,
        public readonly string|Optional|null                          $seoDescription,
        public readonly bool|Optional|null                            $allowSearchEngineIndexing,

        // Password
        public readonly string|Optional|null                          $homepagePassword,
    )
    {
    }
}
