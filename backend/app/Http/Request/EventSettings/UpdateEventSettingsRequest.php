<?php

namespace HiEvents\Http\Request\EventSettings;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\PriceDisplayMode;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Validators\Rules\RulesHelper;
use Illuminate\Validation\Rule;

class UpdateEventSettingsRequest extends BaseRequest
{
    // @todo these should all be required for the update request. They should only be nullable for the PATCH request
    public function rules(): array
    {
        return [
            'post_checkout_message' => ['string', "nullable"],
            'pre_checkout_message' => ['string', "nullable"],
            'email_footer_message' => ['string', "nullable"],

            'continue_button_text' => ['string', 'nullable', 'max:100'],
            'support_email' => ['email', 'nullable'],
            'require_attendee_details' => ['boolean'],
            'order_timeout_in_minutes' => ['numeric', "min:1", "max:120"],

            'homepage_background_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_primary_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_primary_text_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_secondary_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_secondary_text_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_body_background_color' => ['nullable', ...RulesHelper::HEX_COLOR],
            'homepage_background_type' => ['nullable', Rule::in(HomepageBackgroundType::valuesArray())],

            'website_url' => ['url', 'nullable'],
            'maps_url' => ['url', 'nullable'],

            'location_details' => ['array'],
            'location_details.venue_name' => ['string', 'max:255', 'nullable'],
            'location_details.address_line_1' => ['required_with:location_details', 'string', 'max:255'],
            'location_details.address_line_2' => ['string', 'max:255', 'nullable'],
            'location_details.city' => ['required_with:location_details', 'string', 'max:85'],
            'location_details.state_or_region' => ['string', 'max:85', 'nullable'],
            'location_details.zip_or_postal_code' => ['required_with:location_details', 'string', 'max:85'],
            'location_details.country' => ['required_with:location_details', 'string', 'max:2'],

            'is_online_event' => ['boolean'],
            'online_event_connection_details' => ['string', 'nullable'],

            'seo_title' => ['string', 'max:255', 'nullable'],
            'seo_description' => ['string', 'max:255', 'nullable'],
            'seo_keywords' => ['string', 'max:255', 'nullable'],
            'allow_search_engine_indexing' => ['boolean'],

            'notify_organizer_of_new_orders' => ['boolean'],

            'price_display_mode' => [Rule::in(PriceDisplayMode::valuesArray())],

            'hide_getting_started_page' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        $colorMessage = __('Please enter a valid hex color code. In the format #000000 or #000.');

        return [
            'order_timeout_in_minutes.max' => __('The maximum timeout is 2 hours.'),
            'homepage_background_color' => $colorMessage,
            'homepage_text_color' => $colorMessage,
            'homepage_button_color' => $colorMessage,
            'homepage_link_color' => $colorMessage,
            'homepage_ticket_widget_background_color' => $colorMessage,
            'homepage_ticket_widget_text_color' => $colorMessage,
            'location_details.address_line_1.required_with' => __('The address line 1 field is required'),
            'location_details.city.required_with' => __('The city field is required'),
            'location_details.zip_or_postal_code.required_with' => __('The zip or postal code field is required'),
            'location_details.country.required_with' => __('The country field is required'),
            'location_details.country.max' => __('The country field should be a 2 character ISO 3166 code'),
            'price_display_mode.in' => 'The price display mode must be either inclusive or exclusive.',
        ];
    }
}
