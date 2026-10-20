<?php

namespace Tests\Feature\Http\Actions\Lineup;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LineupMalformedInputTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $organizerId;

    private int $eventId;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($accountId);
        $this->eventId = $this->createEvent($accountId, $this->organizerId);
        $this->headers = $this->headersFor($owner, $accountId);
    }

    public function test_sorting_lineup_entries_with_nested_ids_is_a_validation_error(): void
    {
        $this->postJson("/events/$this->eventId/lineup/entries/sort", [
            'sorted_ids' => [[1], [2]],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sorted_ids.0', 'sorted_ids.1']);
    }

    public function test_sorting_profile_images_with_nested_ids_is_a_validation_error(): void
    {
        $profileId = $this->postJson("/organizers/$this->organizerId/profiles", ['name' => 'Nova'], $this->headers)->json('data.id');

        $this->postJson("/organizers/$this->organizerId/profiles/$profileId/images/sort", [
            'sorted_ids' => [[1], [2]],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sorted_ids.0', 'sorted_ids.1']);
    }

    public function test_creating_a_schedule_item_with_nested_values_is_a_validation_error(): void
    {
        $this->postJson("/events/$this->eventId/lineup/schedule-items", [
            'title' => ['Doors'],
            'starts_at' => '2030-06-14 19:00',
            'ends_at' => '2030-06-14 20:00',
            'lineup_entry_ids' => [[1], [1]],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'lineup_entry_ids.0', 'lineup_entry_ids.1']);

        $this->assertDatabaseMissing('event_schedule_items', ['event_id' => $this->eventId]);
    }

    public function test_updating_a_schedule_item_with_nested_values_is_a_validation_error(): void
    {
        $slotId = $this->postJson("/events/$this->eventId/lineup/schedule-items", [
            'title' => 'Doors',
            'starts_at' => '2030-06-14 19:00',
            'ends_at' => '2030-06-14 20:00',
        ], $this->headers)->assertCreated()->json('data.id');

        $this->putJson("/events/$this->eventId/lineup/schedule-items/$slotId", [
            'title' => ['Doors'],
            'starts_at' => '2030-06-14 19:00',
            'ends_at' => '2030-06-14 20:00',
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseHas('event_schedule_items', ['id' => $slotId, 'title' => 'Doors']);
    }

    public function test_a_schedule_item_without_a_title_or_people_still_says_so_when_other_fields_are_invalid(): void
    {
        $this->postJson("/events/$this->eventId/lineup/schedule-items", [
            'starts_at' => 'not a date',
            'ends_at' => '2030-06-14 20:00',
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at'])
            ->assertJsonPath('errors.title.0', 'Add a title or at least one person from the lineup');
    }

    public function test_duplicate_ids_are_still_rejected(): void
    {
        $this->postJson("/events/$this->eventId/lineup/entries/sort", [
            'sorted_ids' => [5, 5],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.sorted_ids.0', 'Each lineup entry may only appear once');

        $this->postJson("/events/$this->eventId/lineup/schedule-items", [
            'starts_at' => '2030-06-14 19:00',
            'ends_at' => '2030-06-14 20:00',
            'lineup_entry_ids' => [7, 7],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.lineup_entry_ids.0', 'Each person may only be added once');
    }
}
