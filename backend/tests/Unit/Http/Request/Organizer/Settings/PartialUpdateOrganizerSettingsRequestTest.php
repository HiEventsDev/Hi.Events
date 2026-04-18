<?php

namespace Tests\Unit\Http\Request\Organizer\Settings;

use HiEvents\Http\Request\Organizer\Settings\PartialUpdateOrganizerSettingsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PartialUpdateOrganizerSettingsRequestTest extends TestCase
{
    public function testConsentMustBeAcknowledgedWhenPixelsAreEnabled(): void
    {
        $request = new PartialUpdateOrganizerSettingsRequest();
        $request->merge([
            'tracking_pixels' => [
                ['provider' => 'facebook_pixel', 'pixel_id' => '1234567890', 'enabled' => true],
            ],
            'tracking_consent_acknowledged' => false,
        ]);

        $validator = Validator::make(
            $request->all(),
            PartialUpdateOrganizerSettingsRequest::rules()
        );

        foreach ($request->after() as $callback) {
            $callback($validator);
        }

        $this->assertTrue($validator->errors()->has('tracking_consent_acknowledged'));
    }

    public function testConsentNotRequiredWhenPixelsAreDisabled(): void
    {
        $request = new PartialUpdateOrganizerSettingsRequest();
        $request->merge([
            'tracking_pixels' => [
                ['provider' => 'facebook_pixel', 'pixel_id' => '1234567890', 'enabled' => false],
            ],
            'tracking_consent_acknowledged' => false,
        ]);

        $validator = Validator::make(
            $request->all(),
            PartialUpdateOrganizerSettingsRequest::rules()
        );

        foreach ($request->after() as $callback) {
            $callback($validator);
        }

        $this->assertFalse($validator->errors()->has('tracking_consent_acknowledged'));
    }

    public function testConsentNotRequiredWhenNoPixels(): void
    {
        $request = new PartialUpdateOrganizerSettingsRequest();
        $request->merge([
            'tracking_pixels' => [],
            'tracking_consent_acknowledged' => false,
        ]);

        $validator = Validator::make(
            $request->all(),
            PartialUpdateOrganizerSettingsRequest::rules()
        );

        foreach ($request->after() as $callback) {
            $callback($validator);
        }

        $this->assertFalse($validator->errors()->has('tracking_consent_acknowledged'));
    }

    public function testInvalidPixelIdIsRejected(): void
    {
        $request = new PartialUpdateOrganizerSettingsRequest();
        $request->merge([
            'tracking_pixels' => [
                ['provider' => 'facebook_pixel', 'pixel_id' => 'not-a-valid-id', 'enabled' => true],
            ],
            'tracking_consent_acknowledged' => true,
        ]);

        $validator = Validator::make(
            $request->all(),
            PartialUpdateOrganizerSettingsRequest::rules()
        );

        foreach ($request->after() as $callback) {
            $callback($validator);
        }

        $this->assertTrue($validator->errors()->has('tracking_pixels.0.pixel_id'));
    }

    public function testGtmBlockedInSaasMode(): void
    {
        config(['app.saas_mode_enabled' => true]);

        $request = new PartialUpdateOrganizerSettingsRequest();
        $request->merge([
            'tracking_pixels' => [
                ['provider' => 'google_tag_manager', 'pixel_id' => 'GTM-ABCDEF', 'enabled' => true],
            ],
            'tracking_consent_acknowledged' => true,
        ]);

        $validator = Validator::make(
            $request->all(),
            PartialUpdateOrganizerSettingsRequest::rules()
        );

        foreach ($request->after() as $callback) {
            $callback($validator);
        }

        $this->assertTrue($validator->errors()->has('tracking_pixels.0.provider'));
    }

    public function testGtmAllowedInSelfHostedMode(): void
    {
        config(['app.saas_mode_enabled' => false]);

        $request = new PartialUpdateOrganizerSettingsRequest();
        $request->merge([
            'tracking_pixels' => [
                ['provider' => 'google_tag_manager', 'pixel_id' => 'GTM-ABCDEF', 'enabled' => true],
            ],
            'tracking_consent_acknowledged' => true,
        ]);

        $validator = Validator::make(
            $request->all(),
            PartialUpdateOrganizerSettingsRequest::rules()
        );

        foreach ($request->after() as $callback) {
            $callback($validator);
        }

        $this->assertFalse($validator->errors()->has('tracking_pixels.0.provider'));
    }
}
