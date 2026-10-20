<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\EventOccurrence;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Request\EventOccurrence\UpsertEventOccurrenceRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpsertEventOccurrenceRequestTest extends TestCase
{
    public function test_in_person_requires_location_id(): void
    {
        $request = new UpsertEventOccurrenceRequest;
        $request->merge([
            'start_date' => now()->addDay()->toDateTimeString(),
            'event_location' => [
                'type' => LocationType::IN_PERSON->name,
                'location_id' => null,
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('event_location.location_id'));
    }

    public function test_in_person_passes_when_location_id_set(): void
    {
        $request = new UpsertEventOccurrenceRequest;
        $request->merge([
            'start_date' => now()->addDay()->toDateTimeString(),
            'event_location' => [
                'type' => LocationType::IN_PERSON->name,
                'location_id' => 7,
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('event_location.location_id'));
    }
}
