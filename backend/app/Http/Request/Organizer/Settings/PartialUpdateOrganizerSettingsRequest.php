<?php

namespace HiEvents\Http\Request\Organizer\Settings;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class PartialUpdateOrganizerSettingsRequest extends BaseRequest
{
    public static function rules(): array
    {
        return [
            // Social handles
            'facebook_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instagram_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twitter_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'linkedin_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'discord_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tiktok_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'youtube_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'snapchat_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twitch_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reddit_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pinterest_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telegram_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vk_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'weibo_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'wechat_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'flickr_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tumblr_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quora_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'vimeo_handle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'github_handle' => ['sometimes', 'nullable', 'string', 'max:255'],

            'website_url' => ['sometimes', 'nullable', 'url'],

            // Location details
            'location_details' => ['sometimes', 'array'],
            'location_details.venue_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_details.address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_details.address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_details.city' => ['sometimes', 'nullable', 'string', 'max:85'],
            'location_details.state_or_region' => ['sometimes', 'nullable', 'string', 'max:85'],
            'location_details.zip_or_postal_code' => ['sometimes', 'nullable', 'string', 'max:85'],
            'location_details.country' => ['sometimes', 'nullable', 'string', 'max:2'],

            // Homepage
            'homepage_visibility' => ['nullable', Rule::in(OrganizerHomepageVisibility::valuesArray())],
            'homepage_background_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_primary_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_primary_text_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_secondary_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_secondary_text_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_content_background_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_background_type' => ['nullable', Rule::in(HomepageBackgroundType::valuesArray())],

            // SEO
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'allow_search_engine_indexing' => ['sometimes', 'nullable', 'boolean'],

            // Password
            'homepage_password' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
