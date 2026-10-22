<?php

namespace Tests\Feature\Http\Actions\Events;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OrganizerEventsPublicUpcomingTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_guest_upcoming_list_returns_only_the_organizers_live_upcoming_events(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createLiveOrganizer($accountId, 'Organizer A');
        $otherOrganizerId = $this->createLiveOrganizer($accountId, 'Organizer B');

        $liveUpcoming = $this->createEventWithOccurrence($accountId, $organizerId, EventStatus::LIVE, now()->addDays(7));
        $this->createEventWithOccurrence($accountId, $organizerId, EventStatus::DRAFT, now()->addDays(7));
        $this->createEventWithOccurrence($accountId, $organizerId, EventStatus::LIVE, now()->subDays(3));
        $this->createEventWithOccurrence($accountId, $otherOrganizerId, EventStatus::LIVE, now()->addDays(7));

        $response = $this->getJson(
            "/public/organizers/$organizerId/events?page=1&per_page=4&query=&sort_by=start_date&sort_direction=asc&eventsStatus=upcoming"
        );

        $response->assertOk();
        $this->assertSame([$liveUpcoming], $response->json('data.*.id'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    private function createLiveOrganizer(int $accountId, string $name): int
    {
        $organizerId = $this->createOrganizer($accountId, $name);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);

        return $organizerId;
    }

    private function createEventWithOccurrence(
        int $accountId,
        int $organizerId,
        EventStatus $status,
        CarbonInterface $startDate,
    ): int {
        $eventId = $this->createEvent($accountId, $organizerId);
        DB::table('events')->where('id', $eventId)->update(['status' => $status->name]);

        DB::table('event_occurrences')->insert([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => $startDate->toDateTimeString(),
            'end_date' => $startDate->copy()->addHours(2)->toDateTimeString(),
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $eventId;
    }
}
