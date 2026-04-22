<?php

namespace HiEvents\Http\Request\Organizer\Settings;

use HiEvents\DomainObjects\Enums\AttendeeDetailsCollectionMethod;
use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\HomepageFontFamily;
use HiEvents\DomainObjects\Enums\OrganizerHomepageVisibility;
use HiEvents\DomainObjects\Enums\TrackingPixelProvider;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class PartialUpdateOrganizerSettingsRequest extends BaseRequest
{
    public function after(): array
    {
        return [
            function ($validator) {
                $pixels = $this->input('tracking_pixels', []);
                if (!is_array($pixels)) {
                    return;
                }

                $isSaasMode = config('app.saas_mode_enabled');

                foreach ($pixels as $index => $pixel) {
                    $providerValue = $pixel['provider'] ?? null;
                    $pixelId = $pixel['pixel_id'] ?? '';
                    $provider = TrackingPixelProvider::tryFrom($providerValue);

                    if ($isSaasMode && $provider === TrackingPixelProvider::GOOGLE_TAG_MANAGER) {
                        $validator->errors()->add(
                            "tracking_pixels.{$index}.provider",
                            __('Google Tag Manager is not available on hosted plans for security reasons.')
                        );
                        continue;
                    }

                    if ($provider && $pixelId !== '') {
                        if (!preg_match($provider->pixelIdPattern(), $pixelId)) {
                            $validator->errors()->add(
                                "tracking_pixels.{$index}.pixel_id",
                                $provider->pixelIdFormatDescription()
                            );
                        }
                    }
                }

                $enabledPixels = collect($pixels)->filter(fn ($p) => !empty($p['enabled']));
                if ($enabledPixels->isNotEmpty() && !$this->input('tracking_consent_acknowledged')) {
                    $validator->errors()->add(
                        'tracking_consent_acknowledged',
                        __('You must acknowledge your data controller responsibilities before enabling tracking pixels.')
                    );
                }
            },
        ];
    }

    public static function rules(): array
    {
        return [
            // Event defaults
            'default_attendee_details_collection_method' => ['sometimes', 'nullable', Rule::in(AttendeeDetailsCollectionMethod::valuesArray())],
            'default_show_marketing_opt_in' => ['sometimes', 'nullable', 'boolean'],
            'default_pass_platform_fee_to_buyer' => ['sometimes', 'nullable', 'boolean'],
            'default_allow_attendee_self_edit' => ['sometimes', 'nullable', 'boolean'],

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

            // Homepage theme settings
            'homepage_theme_settings' => ['nullable', 'array'],
            'homepage_theme_settings.accent' => ['nullable', 'string', ...RulesHelper::HEX_COLOR],
            'homepage_theme_settings.background' => ['nullable', 'string', ...RulesHelper::HEX_COLOR],
            'homepage_theme_settings.mode' => ['nullable', 'string', Rule::in(['light', 'dark'])],
            'homepage_theme_settings.background_type' => ['nullable', 'string', Rule::in(HomepageBackgroundType::valuesArray())],
            'homepage_theme_settings.font_family' => ['nullable', 'string', Rule::in(HomepageFontFamily::valuesArray())],

            // SEO
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'allow_search_engine_indexing' => ['sometimes', 'nullable', 'boolean'],

            // Password
            'homepage_password' => ['sometimes', 'nullable', 'string', 'max:100'],

            // Tracking pixels
            'tracking_pixels' => ['sometimes', 'nullable', 'array', 'max:10'],
            'tracking_pixels.*.provider' => ['required', 'string', Rule::in(TrackingPixelProvider::valuesArray())],
            'tracking_pixels.*.pixel_id' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9\-_]+$/'],
            'tracking_pixels.*.enabled' => ['required', 'boolean'],
            'tracking_consent_acknowledged' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
