<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\Models\User;
use HiEvents\Repository\Eloquent\LocationRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private LocationRepository $repository;

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(LocationRepository::class);

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;
        $this->organizerId = $this->insertOrganizer();
    }

    public function test_returns_true_when_location_is_referenced_by_active_event_location(): void
    {
        $locationId = $this->insertLocation();
        $eventId = $this->insertEvent();
        $this->insertEventLocation($eventId, $locationId);

        $this->assertTrue($this->repository->isReferenced($locationId));
    }

    public function test_returns_true_when_location_is_referenced_only_by_organizer(): void
    {
        $locationId = $this->insertLocation();
        DB::table('organizers')
            ->where('id', $this->organizerId)
            ->update(['location_id' => $locationId]);

        $this->assertTrue($this->repository->isReferenced($locationId));
    }

    public function test_returns_false_when_location_is_unreferenced(): void
    {
        $locationId = $this->insertLocation();

        $this->assertFalse($this->repository->isReferenced($locationId));
    }

    public function test_soft_deleted_event_location_does_not_count_as_reference(): void
    {
        $locationId = $this->insertLocation();
        $eventId = $this->insertEvent();
        $this->insertEventLocation($eventId, $locationId, deletedAt: now());

        $this->assertFalse($this->repository->isReferenced($locationId));
    }

    private function insertOrganizer(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Test Organizer',
            'email' => 'organizer+'.uniqid().'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertLocation(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('locations')->insertGetId([
            'short_id' => 'loc_'.uniqid(),
            'account_id' => $this->accountId,
            'organizer_id' => $this->organizerId,
            'name' => 'Test Venue',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEvent(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => 'Test Event',
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEventLocation(int $eventId, int $locationId, ?\DateTimeInterface $deletedAt = null): int
    {
        $now = now()->toDateTimeString();

        return DB::table('event_locations')->insertGetId([
            'short_id' => 'el_'.uniqid(),
            'event_id' => $eventId,
            'type' => 'IN_PERSON',
            'location_id' => $locationId,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => $deletedAt,
        ]);
    }
}
