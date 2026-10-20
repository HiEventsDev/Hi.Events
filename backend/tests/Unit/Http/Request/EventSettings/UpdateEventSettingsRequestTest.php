<?php

namespace Tests\Unit\Http\Request\EventSettings;

use HiEvents\DomainObjects\Enums\TicketDateDisplayMode;
use HiEvents\Http\Request\EventSettings\UpdateEventSettingsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateEventSettingsRequestTest extends TestCase
{
    public function test_valid_date_display_modes_are_accepted(): void
    {
        foreach (TicketDateDisplayMode::valuesArray() as $mode) {
            $validator = Validator::make(
                ['ticket_design_settings' => ['date_display_mode' => $mode]],
                (new UpdateEventSettingsRequest)->rules()
            );

            $this->assertFalse(
                $validator->errors()->has('ticket_design_settings.date_display_mode'),
                "Expected '{$mode}' to be a valid date display mode"
            );
        }
    }

    public function test_invalid_date_display_mode_is_rejected(): void
    {
        $validator = Validator::make(
            ['ticket_design_settings' => ['date_display_mode' => 'NOT_A_MODE']],
            (new UpdateEventSettingsRequest)->rules()
        );

        $this->assertTrue($validator->errors()->has('ticket_design_settings.date_display_mode'));
    }

    public function test_date_display_mode_is_optional(): void
    {
        $validator = Validator::make(
            ['ticket_design_settings' => ['accent_color' => '#333333']],
            (new UpdateEventSettingsRequest)->rules()
        );

        $this->assertFalse($validator->errors()->has('ticket_design_settings.date_display_mode'));
    }

    public function test_allow_copy_details_to_all_attendees_accepts_boolean(): void
    {
        $validator = Validator::make(
            ['allow_copy_details_to_all_attendees' => false],
            (new UpdateEventSettingsRequest)->rules()
        );

        $this->assertFalse($validator->errors()->has('allow_copy_details_to_all_attendees'));
    }

    public function test_allow_copy_details_to_all_attendees_rejects_non_boolean(): void
    {
        $validator = Validator::make(
            ['allow_copy_details_to_all_attendees' => 'not-a-boolean'],
            (new UpdateEventSettingsRequest)->rules()
        );

        $this->assertTrue($validator->errors()->has('allow_copy_details_to_all_attendees'));
    }

    public function test_allow_copy_details_to_all_attendees_is_optional(): void
    {
        $validator = Validator::make(
            ['ticket_design_settings' => ['accent_color' => '#333333']],
            (new UpdateEventSettingsRequest)->rules()
        );

        $this->assertFalse($validator->errors()->has('allow_copy_details_to_all_attendees'));
    }
}
