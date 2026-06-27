<?php

namespace Tests\Unit\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Resources\Attendee\AttendeeWithCheckInPublicResource;
use Illuminate\Http\Request;
use Tests\TestCase;

class AttendeeWithCheckInPublicResourceTest extends TestCase
{
    public function test_public_check_in_attendee_resource_excludes_email(): void
    {
        $attendee = (new AttendeeDomainObject())
            ->setId(1)
            ->setOrderId(10)
            ->setProductId(20)
            ->setProductPriceId(30)
            ->setEmail('attendee@example.com')
            ->setFirstName('Jane')
            ->setLastName('Attendee')
            ->setPublicId('A-12345')
            ->setStatus('ACTIVE');

        $resource = (new AttendeeWithCheckInPublicResource($attendee))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('email', $resource);
        $this->assertSame('Jane', $resource['first_name']);
        $this->assertSame('Attendee', $resource['last_name']);
        $this->assertSame('A-12345', $resource['public_id']);
        $this->assertSame(10, $resource['order_id']);
    }
}
