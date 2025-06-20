<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Settings;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\Organizer\DTO\PartialUpdateOrganizerSettingsDTO;
use Spatie\LaravelData\Data;

class PartialUpdateOrganizerSettingsHandler
{
    public function __construct(
        private readonly OrganizerSettingsRepositoryInterface $organizerSettingsRepository,
        private readonly OrganizerRepositoryInterface         $organizerRepository,
    )
    {
    }

    public function handle(PartialUpdateOrganizerSettingsDTO $dto): OrganizerSettingDomainObject
    {
        /** @var OrganizerDomainObject $organizer */
        $organizer = $this->organizerRepository->findFirstWhere([
            'id' => $dto->organizerId,
            'account_id' => $dto->accountId,
        ]);

        /** @var OrganizerSettingDomainObject $organizerSettings */
        $organizerSettings = $this->organizerSettingsRepository->findFirstWhere([
            'organizer_id' => $organizer->getId(),
        ]);

        $locationDetails = $dto->getProvided('locationDetails', $organizerSettings->getLocationDetails());

        if ($locationDetails instanceof Data) {
            $locationDetails = $locationDetails->toArray();
        } elseif (is_array($locationDetails)) {
            $locationDetails = array_filter($locationDetails);
        } else {
            $locationDetails = [];
        }

        $this->organizerSettingsRepository->updateWhere([
            'social_media_handles' => array_filter([
                'facebook' => $dto->getProvided('facebookHandle', $organizerSettings->getSocialMediaHandle('facebook')),
                'instagram' => $dto->getProvided('instagramHandle', $organizerSettings->getSocialMediaHandle('instagram')),
                'twitter' => $dto->getProvided('twitterHandle', $organizerSettings->getSocialMediaHandle('twitter')),
                'linkedin' => $dto->getProvided('linkedinHandle', $organizerSettings->getSocialMediaHandle('linkedin')),
                'discord' => $dto->getProvided('discordHandle', $organizerSettings->getSocialMediaHandle('discord')),
                'tiktok' => $dto->getProvided('tiktokHandle', $organizerSettings->getSocialMediaHandle('tiktok')),
                'youtube' => $dto->getProvided('youtubeHandle', $organizerSettings->getSocialMediaHandle('youtube')),
                'snapchat' => $dto->getProvided('snapchatHandle', $organizerSettings->getSocialMediaHandle('snapchat')),
                'twitch' => $dto->getProvided('twitchHandle', $organizerSettings->getSocialMediaHandle('twitch')),
                'reddit' => $dto->getProvided('redditHandle', $organizerSettings->getSocialMediaHandle('reddit')),
                'pinterest' => $dto->getProvided('pinterestHandle', $organizerSettings->getSocialMediaHandle('pinterest')),
                'whatsapp' => $dto->getProvided('whatsappHandle', $organizerSettings->getSocialMediaHandle('whatsapp')),
                'telegram' => $dto->getProvided('telegramHandle', $organizerSettings->getSocialMediaHandle('telegram')),
                'vk' => $dto->getProvided('vkHandle', $organizerSettings->getSocialMediaHandle('vk')),
                'weibo' => $dto->getProvided('weiboHandle', $organizerSettings->getSocialMediaHandle('weibo')),
                'wechat' => $dto->getProvided('wechatHandle', $organizerSettings->getSocialMediaHandle('wechat')),
                'flickr' => $dto->getProvided('flickrHandle', $organizerSettings->getSocialMediaHandle('flickr')),
                'tumblr' => $dto->getProvided('tumblrHandle', $organizerSettings->getSocialMediaHandle('tumblr')),
                'quora' => $dto->getProvided('quoraHandle', $organizerSettings->getSocialMediaHandle('quora')),
                'vimeo' => $dto->getProvided('vimeoHandle', $organizerSettings->getSocialMediaHandle('vimeo')),
                'github' => $dto->getProvided('githubHandle', $organizerSettings->getSocialMediaHandle('github')),
            ]),

            'website_url' => $dto->getProvided('websiteUrl', $organizerSettings->getWebsiteUrl()),

            'location_details' => $locationDetails,

            'homepage_visibility' => $dto->getProvided('homepageVisibility', $organizerSettings->getHomepageVisibility()),

            'homepage_theme_settings' => [
                'homepage_background_color' => $dto->getProvided('homepageBackgroundColor', $organizerSettings->getHomepageThemeSetting('homepage_background_color')),
                'homepage_content_background_color' => $dto->getProvided('homepageContentBackgroundColor', $organizerSettings->getHomepageThemeSetting('homepage_content_background_color')),
                'homepage_primary_color' => $dto->getProvided('homepagePrimaryColor', $organizerSettings->getHomepageThemeSetting('homepage_primary_color')),
                'homepage_primary_text_color' => $dto->getProvided('homepagePrimaryTextColor', $organizerSettings->getHomepageThemeSetting('homepage_primary_text_color')),
                'homepage_secondary_color' => $dto->getProvided('homepageSecondaryColor', $organizerSettings->getHomepageThemeSetting('homepage_secondary_color')),
                'homepage_secondary_text_color' => $dto->getProvided('homepageSecondaryTextColor', $organizerSettings->getHomepageThemeSetting('homepage_secondary_text_color')),
                'homepage_background_type' => $dto->getProvided('homepageBackgroundType', $organizerSettings->getHomepageThemeSetting('homepage_background_type')),
            ],

            'seo_keywords' => $dto->getProvided('seoKeywords', $organizerSettings->getSeoKeywords()),
            'seo_title' => $dto->getProvided('seoTitle', $organizerSettings->getSeoTitle()),
            'seo_description' => $dto->getProvided('seoDescription', $organizerSettings->getSeoDescription()),
            'allow_search_engine_indexing' => $dto->getProvided('allowSearchEngineIndexing', $organizerSettings->getAllowSearchEngineIndexing()),

            'homepage_password' => $dto->getProvided('homepagePassword', $organizerSettings->getHomepagePassword()),
        ], [
            'organizer_id' => $dto->organizerId,
            'id' => $organizerSettings->getId(),
        ]);

        return $this->organizerSettingsRepository->findFirst($organizerSettings->getId());
    }
}
