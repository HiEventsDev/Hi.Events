<?php

namespace Tests\Unit\Resources\Event;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Resources\Event\EventSettingsResourcePublic;
use Illuminate\Http\Request;
use Tests\TestCase;

class EventSettingsResourcePublicTest extends TestCase
{
    public function test_public_resource_exposes_allow_copy_details_when_enabled(): void
    {
        $settings = (new EventSettingDomainObject())
            ->setAllowCopyDetailsToAllAttendees(true);

        $resource = (new EventSettingsResourcePublic($settings))->toArray(Request::create('/'));

        // Load-bearing: the checkout can only hide the control if this flag is on the
        // public payload, so it must always be present (outside the post-checkout block).
        $this->assertArrayHasKey('allow_copy_details_to_all_attendees', $resource);
        $this->assertTrue($resource['allow_copy_details_to_all_attendees']);
    }

    public function test_public_resource_exposes_allow_copy_details_when_disabled(): void
    {
        $settings = (new EventSettingDomainObject())
            ->setAllowCopyDetailsToAllAttendees(false);

        $resource = (new EventSettingsResourcePublic($settings))->toArray(Request::create('/'));

        $this->assertFalse($resource['allow_copy_details_to_all_attendees']);
    }
}
