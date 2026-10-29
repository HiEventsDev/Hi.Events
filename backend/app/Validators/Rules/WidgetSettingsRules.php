<?php

declare(strict_types=1);

namespace HiEvents\Validators\Rules;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\Enums\HomepageFontFamily;
use HiEvents\DomainObjects\Enums\WidgetType;
use HiEvents\Locale;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WidgetSettingsRules
{
    public const SECTIONS = ['tickets', 'header', 'about', 'location', 'organizer', 'lineup', 'schedule', 'custom'];

    public const MAX_IDS = 100;

    private const HEX_COLOR = 'regex:/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    private const SINGLE_LINE = 'not_regex:/[\x00-\x1F\x7F]/';

    private const SHARED_KEYS = [
        'background_color',
        'primary_color',
        'primary_text_color',
        'secondary_color',
        'secondary_text_color',
        'padding',
        'radius',
        'continue_button_text',
        'button_text',
        'checkout',
        'autoresize',
        'show_lineup',
        'hide_promo_code',
        'hide_category_titles',
        'theme',
        'font',
        'locale',
        'sections',
        'promo_code',
    ];

    private const SINGLE_EVENT_KEYS = ['products', 'categories', 'occurrence_id'];

    private const EVENT_LISTING_KEYS = ['layout', 'limit', 'event_category', 'show_past', 'open_in'];

    public static function rules(?WidgetType $type): array
    {
        $allowedKeys = [
            ...self::SHARED_KEYS,
            ...($type === WidgetType::EVENTS ? self::EVENT_LISTING_KEYS : self::SINGLE_EVENT_KEYS),
        ];

        return [
            'settings' => ['nullable', 'array:'.implode(',', $allowedKeys)],
            'settings.background_color' => ['nullable', 'string', self::HEX_COLOR],
            'settings.primary_color' => ['nullable', 'string', self::HEX_COLOR],
            'settings.primary_text_color' => ['nullable', 'string', self::HEX_COLOR],
            'settings.secondary_color' => ['nullable', 'string', self::HEX_COLOR],
            'settings.secondary_text_color' => ['nullable', 'string', self::HEX_COLOR],
            'settings.padding' => ['nullable', 'integer', 'min:0', 'max:500'],
            'settings.radius' => ['nullable', 'integer', 'min:0', 'max:32'],
            'settings.continue_button_text' => ['nullable', 'string', 'max:100', self::SINGLE_LINE],
            'settings.button_text' => ['nullable', 'string', 'max:100', self::SINGLE_LINE],
            'settings.checkout' => ['nullable', Rule::in(['modal', 'new-tab'])],
            'settings.autoresize' => ['nullable', 'boolean'],
            'settings.show_lineup' => ['nullable', 'boolean'],
            'settings.hide_promo_code' => ['nullable', 'boolean'],
            'settings.hide_category_titles' => ['nullable', 'boolean'],
            'settings.theme' => ['nullable', Rule::in(['custom', 'event'])],
            'settings.font' => ['nullable', Rule::in(HomepageFontFamily::valuesArray())],
            'settings.locale' => ['nullable', Rule::in(Locale::getSupportedLocales())],
            'settings.sections' => ['nullable', 'array', 'list', 'max:'.count(self::SECTIONS)],
            'settings.sections.*' => ['string', Rule::in(self::SECTIONS)],
            'settings.promo_code' => ['nullable', 'string', 'max:50', self::SINGLE_LINE],
            'settings.products' => ['nullable', 'array', 'list', 'max:'.self::MAX_IDS],
            'settings.products.*' => ['integer', 'min:1'],
            'settings.categories' => ['nullable', 'array', 'list', 'max:'.self::MAX_IDS],
            'settings.categories.*' => ['integer', 'min:1'],
            'settings.occurrence_id' => ['nullable', 'integer', 'min:1'],
            'settings.layout' => ['nullable', Rule::in(['list', 'grid', 'compact'])],
            'settings.limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'settings.event_category' => ['nullable', Rule::in(EventCategory::valuesArray())],
            'settings.show_past' => ['nullable', 'boolean'],
            'settings.open_in' => ['nullable', Rule::in(['popup', 'page'])],
        ];
    }

    public static function messages(): array
    {
        return [
            'settings.array' => __('Settings must be an object containing only supported widget settings.'),
        ];
    }

    public static function rejectDuplicateSections(Validator $validator, mixed $sections): void
    {
        if (! is_array($sections)) {
            return;
        }

        $seen = [];
        foreach ($sections as $index => $section) {
            if (! is_string($section)) {
                continue;
            }

            if (isset($seen[$section])) {
                $validator->errors()->add("settings.sections.$index", __('Each section can only be listed once.'));
            }

            $seen[$section] = true;
        }
    }
}
