<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Migrations;

use HiEvents\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises the inline backfill on the link_events_and_occurrences_to_event_locations
 * migration. The legacy address columns are intentionally retained, so the
 * migration's backfill() method can be re-invoked against pretend
 * pre-migration data without rerunning the schema changes.
 */
class LinkEventsToEventLocationsBackfillTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION_PATH = __DIR__.'/../../../../database/migrations/2026_05_22_000002_link_events_and_occurrences_to_event_locations.php';

    private int $accountId;

    private int $userId;

    private int $organizerId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;
        $this->organizerId = $this->insertOrganizer();
    }

    public function test_creates_in_person_event_location_from_event_settings_address(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEventSettings($eventId, locationDetails: [
            'venue_name' => 'Settings Hall',
            'address_line_1' => '1 Settings Way',
            'city' => 'Dublin',
            'country' => 'IE',
        ]);

        $count = $this->migration()->backfill();

        $this->assertSame(1, $count);

        $event = DB::table('events')->where('id', $eventId)->first();
        $this->assertNotNull($event->event_location_id);

        $eventLocation = DB::table('event_locations')->where('id', $event->event_location_id)->first();
        $this->assertSame('IN_PERSON', $eventLocation->type);
        $this->assertNotNull($eventLocation->location_id);
        $this->assertNull($eventLocation->online_event_connection_details);

        $location = DB::table('locations')->where('id', $eventLocation->location_id)->first();
        $this->assertSame($this->organizerId, (int) $location->organizer_id);
        $this->assertSame($this->accountId, (int) $location->account_id);
        $this->assertSame('Settings Hall', $location->name);
        $this->assertEquals([
            'venue_name' => 'Settings Hall',
            'address_line_1' => '1 Settings Way',
            'city' => 'Dublin',
            'country' => 'IE',
        ], json_decode($location->structured_address, true));
    }

    public function test_falls_back_to_events_location_details_when_event_settings_empty(): void
    {
        $eventId = $this->insertEvent(locationDetails: [
            'venue_name' => 'Event Hall',
            'city' => 'Cork',
        ]);
        $this->insertEventSettings($eventId, locationDetails: null);

        $this->migration()->backfill();

        $event = DB::table('events')->where('id', $eventId)->first();
        $eventLocation = DB::table('event_locations')->where('id', $event->event_location_id)->first();
        $location = DB::table('locations')->where('id', $eventLocation->location_id)->first();

        $this->assertSame('Event Hall', $location->name);
    }

    public function test_creates_online_event_location_when_is_online_event_true(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEventSettings(
            $eventId,
            isOnlineEvent: true,
            onlineDetails: '<p>Zoom: https://example.com/abc</p>',
        );

        $this->migration()->backfill();

        $event = DB::table('events')->where('id', $eventId)->first();
        $eventLocation = DB::table('event_locations')->where('id', $event->event_location_id)->first();

        $this->assertSame('ONLINE', $eventLocation->type);
        $this->assertNull($eventLocation->location_id);
        $this->assertSame('<p>Zoom: https://example.com/abc</p>', $eventLocation->online_event_connection_details);
    }

    public function test_online_event_legacy_html_is_re_purified(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEventSettings(
            $eventId,
            isOnlineEvent: true,
            onlineDetails: '<p>Zoom: https://example.com/abc</p><script>alert(1)</script>',
        );

        $this->migration()->backfill();

        $event = DB::table('events')->where('id', $eventId)->first();
        $eventLocation = DB::table('event_locations')->where('id', $event->event_location_id)->first();

        $this->assertStringNotContainsString('<script', $eventLocation->online_event_connection_details);
        $this->assertStringNotContainsString('alert(', $eventLocation->online_event_connection_details);
        $this->assertStringContainsString('Zoom: https://example.com/abc', $eventLocation->online_event_connection_details);
    }

    public function test_skips_events_with_no_legacy_data(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEventSettings($eventId);

        $count = $this->migration()->backfill();

        $this->assertSame(0, $count);
        $event = DB::table('events')->where('id', $eventId)->first();
        $this->assertNull($event->event_location_id);
    }

    public function test_is_idempotent_when_re_run(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEventSettings($eventId, locationDetails: ['venue_name' => 'Hall']);

        $first = $this->migration()->backfill();
        $second = $this->migration()->backfill();

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);

        $this->assertSame(1, DB::table('event_locations')->where('event_id', $eventId)->count());
    }

    public function test_skips_address_blobs_with_no_meaningful_fields(): void
    {
        $eventId = $this->insertEvent();
        $this->insertEventSettings($eventId, locationDetails: ['some_other_key' => 'noise']);

        $count = $this->migration()->backfill();

        $this->assertSame(0, $count);
    }

    private function migration(): Migration
    {
        // The migration is an anonymous-class instance — `require` the file
        // each time to get a fresh one and call backfill() directly.
        return require self::MIGRATION_PATH;
    }

    private function insertOrganizer(): int
    {
        $now = now()->toDateTimeString();

        return DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Backfill Organizer',
            'email' => 'org+'.uniqid().'@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEvent(?array $locationDetails = null): int
    {
        $now = now()->toDateTimeString();

        return DB::table('events')->insertGetId([
            'title' => 'Backfill Event '.uniqid(),
            'account_id' => $this->accountId,
            'user_id' => $this->userId,
            'organizer_id' => $this->organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'location_details' => $locationDetails !== null ? json_encode($locationDetails) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertEventSettings(
        int $eventId,
        ?array $locationDetails = null,
        bool $isOnlineEvent = false,
        ?string $onlineDetails = null,
    ): void {
        $now = now()->toDateTimeString();

        DB::table('event_settings')->insert([
            'event_id' => $eventId,
            'location_details' => $locationDetails !== null ? json_encode($locationDetails) : null,
            'is_online_event' => $isOnlineEvent,
            'online_event_connection_details' => $onlineDetails,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
