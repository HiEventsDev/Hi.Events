<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LineupAccessTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private User $owner;

    private int $accountId;

    private int $organizerA;

    private int $organizerB;

    private int $eventA;

    private int $eventB;

    private int $profileA;

    private int $entryB;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerA = $this->createOrganizer($this->accountId, 'A');
        $this->organizerB = $this->createOrganizer($this->accountId, 'B');
        $this->eventA = $this->createEvent($this->accountId, $this->organizerA, 'Event A');
        $this->eventB = $this->createEvent($this->accountId, $this->organizerB, 'Event B');

        $headers = $this->headersFor($this->owner, $this->accountId);
        $this->profileA = $this->postJson("/organizers/$this->organizerA/profiles", ['name' => 'Speaker A'], $headers)->json('data.id');
        $profileB = $this->postJson("/organizers/$this->organizerB/profiles", ['name' => 'Speaker B'], $headers)->json('data.id');
        $this->entryB = $this->postJson("/events/$this->eventB/lineup/entries", ['profile_id' => $profileB, 'billing_tier' => 'STANDARD'], $headers)
            ->json('data.id');
    }

    public function test_viewers_can_read_the_lineup_and_people_but_change_nothing(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $headers = $this->headersFor($viewer, $this->accountId);

        $this->getJson("/events/$this->eventA/lineup", $headers)->assertOk();
        $this->getJson("/organizers/$this->organizerA/profiles", $headers)->assertOk();

        $this->postJson("/organizers/$this->organizerA/profiles", ['name' => 'Nope'], $headers)->assertForbidden();
        $this->putJson("/organizers/$this->organizerA/profiles/$this->profileA", ['name' => 'Renamed'], $headers)->assertForbidden();
        $this->postJson("/organizers/$this->organizerA/profiles/$this->profileA/images/sort", ['sorted_ids' => []], $headers)->assertForbidden();
        $this->postJson("/events/$this->eventA/lineup/entries", ['profile_id' => $this->profileA, 'billing_tier' => 'STANDARD'], $headers)->assertForbidden();
        $this->postJson("/events/$this->eventA/lineup/spaces", ['name' => 'Main Stage'], $headers)->assertForbidden();
        $this->postJson("/events/$this->eventA/lineup/schedule-items", [
            'title' => 'Doors', 'starts_at' => '2030-06-14 19:00', 'ends_at' => '2030-06-14 20:00',
        ], $headers)->assertForbidden();
        $this->patchJson("/events/$this->eventA/settings", ['homepage_sections' => []], $headers)->assertForbidden();
        $this->post('/images', [
            'image' => UploadedFile::fake()->image('photo.png', 400, 400),
            'image_type' => 'PROFILE_IMAGE',
            'entity_id' => $this->profileA,
        ], $headers)->assertForbidden();

        $this->assertDatabaseMissing('event_spaces', ['event_id' => $this->eventA]);
        $this->assertDatabaseHas('profiles', ['id' => $this->profileA, 'name' => 'Speaker A']);
    }

    public function test_an_event_restricted_member_cannot_touch_another_events_lineup(): void
    {
        $member = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $headers = $this->headersFor($member, $this->accountId);

        $this->getJson("/events/$this->eventA/lineup", $headers)->assertOk();

        $this->getJson("/events/$this->eventB/lineup", $headers)->assertForbidden();
        $this->deleteJson("/events/$this->eventB/lineup/entries/$this->entryB", [], $headers)->assertForbidden();
        $this->deleteJson("/events/$this->eventA/lineup/entries/$this->entryB", [], $headers)->assertNotFound();
        $this->postJson("/events/$this->eventA/lineup/schedule-items", [
            'starts_at' => '2030-06-14 19:00', 'ends_at' => '2030-06-14 20:00', 'lineup_entry_ids' => [$this->entryB],
        ], $headers)->assertNotFound();
        $this->getJson("/organizers/$this->organizerB/profiles", $headers)->assertForbidden();

        $this->assertDatabaseHas('event_lineup_entries', ['id' => $this->entryB]);
        $this->assertDatabaseMissing('event_schedule_items', ['event_id' => $this->eventA]);
    }

    public function test_photos_need_write_access_to_the_profiles_organizer(): void
    {
        $eventOnlyMember = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventA]);
        $organizerMember = $this->addRestrictedAccountUser($this->accountId, organizerIds: [$this->organizerA]);

        $upload = fn (User $user, int $profileId) => $this->post('/images', [
            'image' => UploadedFile::fake()->image('photo.png', 400, 400),
            'image_type' => 'PROFILE_IMAGE',
            'entity_id' => $profileId,
        ], $this->headersFor($user, $this->accountId));

        $upload($eventOnlyMember, $this->profileA)->assertForbidden();
        $upload($organizerMember, $this->profileA)->assertSuccessful();

        $foreignImage = DB::table('images')->insertGetId([
            'account_id' => $this->accountId,
            'entity_id' => $this->eventB,
            'entity_type' => EventDomainObject::class,
            'type' => 'EVENT_GALLERY',
            'filename' => 'x.png',
            'disk' => 'public',
            'path' => 'event_gallery/x.png',
            'size' => 1,
            'mime_type' => 'image/png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->deleteJson("/images/$foreignImage", [], $this->headersFor($organizerMember, $this->accountId))->assertForbidden();

        $this->assertDatabaseHas('images', ['entity_id' => $this->profileA, 'entity_type' => ProfileDomainObject::class]);
    }
}
