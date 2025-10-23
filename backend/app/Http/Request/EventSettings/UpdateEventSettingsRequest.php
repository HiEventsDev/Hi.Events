<?php

namespace HiEvents\Http\Request\EventSettings;

use HiEvents\DomainObjects\Enums\HomepageBackgroundType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
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

            // Payment settings
            'payment_providers' => ['array'],
            'payment_providers.*' => ['string', Rule::in(PaymentProviders::valuesArray())],
            'offline_payment_instructions' => ['string', 'nullable', Rule::requiredIf(fn() => in_array(PaymentProviders::OFFLINE->name, $this->input('payment_providers', []), true))],
            'allow_orders_awaiting_offline_payment_to_check_in' => ['boolean'],

            // Invoice settings
            'enable_invoicing' => ['boolean'],
            'invoice_label' => ['nullable', 'string', 'max:50'],
            'invoice_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-]*$/'],
            'invoice_start_number' => ['nullable', 'integer', 'min:1'],
            'require_billing_address' => ['boolean'],
            'organization_name' => ['required_if:enable_invoicing,true', 'string', 'max:255', 'nullable'],
            'organization_address' => ['required_if:enable_invoicing,true', 'string', 'max:255', 'nullable'],
            'invoice_tax_details' => ['nullable', 'string'],
            'invoice_notes' => ['nullable', 'string'],
            'invoice_payment_terms_days' => ['nullable', 'integer', 'gte:0', 'lte:1000'],

            // Ticket design settings
            'ticket_design_settings' => ['nullable', 'array'],
            'ticket_design_settings.accent_color' => ['nullable', 'string', ...RulesHelper::HEX_COLOR],
            'ticket_design_settings.logo_image_id' => ['nullable', 'integer'],
            'ticket_design_settings.footer_text' => ['nullable', 'string', 'max:500'],
            'ticket_design_settings.layout_type' => ['nullable', 'string', Rule::in(['default', 'modern'])],
            'ticket_design_settings.enabled' => ['boolean'],
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
            'homepage_product_widget_background_color' => $colorMessage,
            'homepage_product_widget_text_color' => $colorMessage,
            'location_details.address_line_1.required_with' => __('The address line 1 field is required'),
            'location_details.city.required_with' => __('The city field is required'),
            'location_details.zip_or_postal_code.required_with' => __('The zip or postal code field is required'),
            'location_details.country.required_with' => __('The country field is required'),
            'location_details.country.max' => __('The country field should be a 2 character ISO 3166 code'),
            'price_display_mode.in' => 'The price display mode must be either inclusive or exclusive.',

            // Payment messages
            'payment_providers.*.in' => __('Invalid payment provider selected.'),
            'offline_payment_instructions.required' => __('Payment instructions are required when offline payments are enabled.'),

            // Invoice messages
            'invoice_prefix.regex' => __('The invoice prefix may only contain letters, numbers, and hyphens.'),
            'organization_name.required_if' => __('The organization name is required when invoicing is enabled.'),
            'organization_address.required_if' => __('The organization address is required when invoicing is enabled.'),
            'invoice_start_number.min' => __('The invoice start number must be at least 1.'),

            // Ticket design messages
            'ticket_design_settings.accent_color' => $colorMessage,
            'ticket_design_settings.footer_text.max' => __('The footer text may not be greater than 500 characters.'),
            'ticket_design_settings.layout_type.in' => __('The layout type must be default or modern.'),
        ];
    }
}
