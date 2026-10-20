<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Request\EventOccurrence;

use HiEvents\DomainObjects\Enums\BulkOccurrenceAction;
use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\Http\Request\EventOccurrence\BulkUpdateOccurrencesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BulkUpdateOccurrencesRequestTest extends TestCase
{
    public function test_in_person_requires_location_id(): void
    {
        $request = new BulkUpdateOccurrencesRequest;
        $request->merge([
            'action' => BulkOccurrenceAction::UPDATE->value,
            'apply_to_all' => true,
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
        $request = new BulkUpdateOccurrencesRequest;
        $request->merge([
            'action' => BulkOccurrenceAction::UPDATE->value,
            'apply_to_all' => true,
            'event_location' => [
                'type' => LocationType::IN_PERSON->name,
                'location_id' => 99,
            ],
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('event_location.location_id'));
    }

    public function test_duration_minutes_rejects_values_above_seven_days(): void
    {
        $request = new BulkUpdateOccurrencesRequest;
        $request->merge([
            'action' => BulkOccurrenceAction::UPDATE->value,
            'apply_to_all' => true,
            'duration_minutes' => 10081,
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('duration_minutes'));
    }

    public function test_duration_minutes_accepts_seven_days(): void
    {
        $request = new BulkUpdateOccurrencesRequest;
        $request->merge([
            'action' => BulkOccurrenceAction::UPDATE->value,
            'apply_to_all' => true,
            'duration_minutes' => 10080,
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('duration_minutes'));
    }
}
