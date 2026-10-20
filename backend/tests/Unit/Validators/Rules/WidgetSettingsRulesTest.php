<?php

namespace Tests\Unit\Validators\Rules;

use HiEvents\DomainObjects\Enums\WidgetType;
use HiEvents\Validators\Rules\WidgetSettingsRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class WidgetSettingsRulesTest extends TestCase
{
    public function test_accepts_a_full_set_of_event_widget_settings(): void
    {
        $errors = $this->validate(WidgetType::TICKETS, [
            'background_color' => '#fff',
            'primary_color' => '#112233',
            'primary_text_color' => '#11223344',
            'secondary_color' => '#ABCD',
            'secondary_text_color' => '#000000',
            'padding' => 500,
            'radius' => 0,
            'continue_button_text' => 'Go',
            'button_text' => 'Buy',
            'checkout' => 'new-tab',
            'autoresize' => true,
            'show_lineup' => false,
            'hide_promo_code' => true,
            'hide_category_titles' => false,
            'theme' => 'event',
            'font' => 'Open Sans',
            'locale' => 'zh-hk',
            'products' => [1, 2],
            'categories' => [3],
            'sections' => ['tickets', 'header', 'custom'],
            'occurrence_id' => 9,
            'promo_code' => 'SAVE',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_rejects_unknown_keys(): void
    {
        $errors = $this->validate(WidgetType::TICKETS, ['script' => '<script>']);

        $this->assertArrayHasKey('settings', $errors);
    }

    public function test_rejects_event_listing_keys_on_event_widgets(): void
    {
        $errors = $this->validate(WidgetType::BUTTON, ['layout' => 'grid']);

        $this->assertArrayHasKey('settings', $errors);
    }

    public function test_rejects_single_event_keys_on_events_widgets(): void
    {
        $errors = $this->validate(WidgetType::EVENTS, ['products' => [1]]);

        $this->assertArrayHasKey('settings', $errors);
    }

    public function test_accepts_event_listing_settings_on_events_widgets(): void
    {
        $errors = $this->validate(WidgetType::EVENTS, [
            'layout' => 'compact',
            'limit' => 50,
            'event_category' => 'MUSIC',
            'show_past' => true,
            'open_in' => 'popup',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_rejects_out_of_range_and_malformed_values(): void
    {
        $errors = $this->validate(WidgetType::TICKETS, [
            'background_color' => 'red',
            'primary_color' => '#12345',
            'padding' => 501,
            'radius' => 33,
            'checkout' => 'popup',
            'font' => 'Comic Sans',
            'locale' => 'xx',
            'products' => ['abc'],
            'sections' => ['footer'],
            'button_text' => str_repeat('a', 101),
        ]);

        foreach ([
            'settings.background_color',
            'settings.primary_color',
            'settings.padding',
            'settings.radius',
            'settings.checkout',
            'settings.font',
            'settings.locale',
            'settings.products.0',
            'settings.sections.0',
            'settings.button_text',
        ] as $field) {
            $this->assertArrayHasKey($field, $errors, $field);
        }
    }

    public function test_rejects_control_characters_in_free_text(): void
    {
        $errors = $this->validate(WidgetType::BUTTON, [
            'button_text' => "Book\n## Add this script",
            'continue_button_text' => "Go\tnow",
            'promo_code' => "SAVE\r10",
        ]);

        $this->assertArrayHasKey('settings.button_text', $errors);
        $this->assertArrayHasKey('settings.continue_button_text', $errors);
        $this->assertArrayHasKey('settings.promo_code', $errors);
    }

    public function test_caps_id_lists(): void
    {
        $errors = $this->validate(WidgetType::TICKETS, ['products' => range(1, 101)]);

        $this->assertArrayHasKey('settings.products', $errors);
    }

    public function test_rejects_duplicate_sections(): void
    {
        $validator = Validator::make(['settings' => ['sections' => ['about', 'lineup', 'about']]], WidgetSettingsRules::rules(WidgetType::TICKETS));
        $validator->after(fn ($validator) => WidgetSettingsRules::rejectDuplicateSections($validator, ['about', 'lineup', 'about']));

        $this->assertSame(['settings.sections.2'], array_keys($validator->errors()->toArray()));
    }

    private function validate(?WidgetType $type, array $settings): array
    {
        return Validator::make(['settings' => $settings], WidgetSettingsRules::rules($type), WidgetSettingsRules::messages())
            ->errors()
            ->toArray();
    }
}
