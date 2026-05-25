<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\Event;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Request\Event\UpdateEventLocationRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateEventLocationRequestTest extends TestCase
{
    public function test_in_person_requires_location_id(): void
    {
        $request = new UpdateEventLocationRequest;
        $request->merge([
            'event_location' => [
                'type' => LocationType::IN_PERSON->name,
                'location_id' => null,
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('event_location.location_id'));
    }

    public function test_in_person_passes_when_location_id_set(): void
    {
        $request = new UpdateEventLocationRequest;
        $request->merge([
            'event_location' => [
                'type' => LocationType::IN_PERSON->name,
                'location_id' => 42,
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('event_location.location_id'));
    }

    public function test_online_does_not_require_location_id(): void
    {
        $request = new UpdateEventLocationRequest;
        $request->merge([
            'event_location' => [
                'type' => LocationType::ONLINE->name,
                'online_event_connection_details' => 'Zoom: https://example.com/abc',
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('event_location.location_id'));
    }

    public function test_online_requires_connection_details(): void
    {
        $request = new UpdateEventLocationRequest;
        $request->merge([
            'event_location' => [
                'type' => LocationType::ONLINE->name,
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('event_location.online_event_connection_details'));
    }

    public function test_null_event_location_is_allowed(): void
    {
        $request = new UpdateEventLocationRequest;
        $request->merge([
            'event_location' => null,
            'clear_event_location' => true,
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
    }
}
