<?php

namespace Tests\Feature\Http\Actions\Lineup;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Domain\Event\DuplicateEventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LineupCrudTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_owner_builds_a_lineup_and_schedule_that_the_public_page_can_read(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        DB::table('events')->where('id', $eventId)->update([
            'timezone' => 'Europe/Dublin',
            'status' => EventStatus::LIVE->name,
        ]);
        $headers = $this->headersFor($owner, $accountId);

        $headlinerId = $this->postJson("/organizers/$organizerId/profiles", [
            'name' => 'Nova Lights',
            'tagline' => 'Berlin techno',
            'bio' => '<p>Hi<script>alert(1)</script></p>',
            'links' => ['https://open.spotify.com/artist/abc', 'https://nova.example.com'],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.bio', '<p>Hi</p>')
            ->assertJsonPath('data.links.0.type', 'SPOTIFY')
            ->assertJsonPath('data.links.1.type', 'WEBSITE')
            ->json('data.id');

        $secretGuestId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Secret Guest'], $headers)
            ->assertCreated()
            ->json('data.id');

        $headlinerEntryId = $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $headlinerId,
            'billing_tier' => 'HEADLINER',
        ], $headers)->assertCreated()->json('data.id');

        $secretEntryId = $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $secretGuestId,
            'billing_tier' => 'STANDARD',
            'is_hidden' => true,
        ], $headers)->assertCreated()->json('data.id');

        $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $headlinerId,
            'billing_tier' => 'STANDARD',
        ], $headers)->assertStatus(409);

        $spaceId = $this->postJson("/events/$eventId/lineup/spaces", ['name' => 'Main Stage'], $headers)
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/events/$eventId/lineup/schedule-items", [
            'starts_at' => '2026-07-10 22:00',
            'ends_at' => '2026-07-11 00:30',
            'event_space_id' => $spaceId,
            'lineup_entry_ids' => [$headlinerEntryId],
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-07-10 21:00:00')
            ->assertJsonPath('data.lineup_entry_ids', [$headlinerEntryId]);

        $this->postJson("/events/$eventId/lineup/schedule-items", [
            'starts_at' => '2026-07-10 20:00',
            'ends_at' => '2026-07-10 21:00',
            'lineup_entry_ids' => [$secretEntryId],
        ], $headers)->assertCreated();

        $this->postJson("/events/$eventId/lineup/entries/sort", [
            'sorted_ids' => [$secretEntryId, $headlinerEntryId],
        ], $headers)->assertNoContent();

        $this->getJson("/events/$eventId/lineup", $headers)
            ->assertOk()
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.0.id', $secretEntryId)
            ->assertJsonCount(2, 'data.schedule_items');

        $this->getJson("/public/events/$eventId/lineup")
            ->assertOk()
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonPath('data.entries.0.profile.name', 'Nova Lights')
            ->assertJsonPath('data.spaces.0.name', 'Main Stage')
            ->assertJsonCount(1, 'data.schedule_items')
            ->assertJsonMissingPath('data.entries.0.is_hidden');
    }

    public function test_public_lineup_is_not_served_for_draft_events(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        DB::table('events')->where('id', $eventId)->update(['status' => EventStatus::DRAFT->name]);

        $this->getJson("/public/events/$eventId/lineup")->assertNotFound();
    }

    public function test_profiles_from_another_account_cannot_be_added_to_a_lineup(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));

        [$otherOwner, $otherAccountId] = $this->createAccountOwner();
        $otherOrganizerId = $this->createOrganizer($otherAccountId);
        $foreignProfileId = $this->postJson("/organizers/$otherOrganizerId/profiles", ['name' => 'Not yours'], $this->headersFor($otherOwner, $otherAccountId))
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $foreignProfileId,
            'billing_tier' => 'STANDARD',
        ], $this->headersFor($owner, $accountId))->assertNotFound();

        $this->assertDatabaseMissing('event_lineup_entries', ['event_id' => $eventId]);
    }

    public function test_schedule_items_reject_lineup_entries_from_another_event(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        $otherEventId = $this->createEvent($accountId, $organizerId, 'Other event');
        $headers = $this->headersFor($owner, $accountId);

        $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Speaker'], $headers)->json('data.id');
        $otherEntryId = $this->postJson("/events/$otherEventId/lineup/entries", [
            'profile_id' => $profileId,
            'billing_tier' => 'STANDARD',
        ], $headers)->assertCreated()->json('data.id');

        $this->postJson("/events/$eventId/lineup/schedule-items", [
            'starts_at' => '2026-07-10 10:00',
            'ends_at' => '2026-07-10 11:00',
            'lineup_entry_ids' => [$otherEntryId],
        ], $headers)->assertNotFound();

        $this->assertDatabaseMissing('event_schedule_items', ['event_id' => $eventId]);
    }

    public function test_event_restricted_member_can_schedule_but_not_create_organizer_profiles(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Speaker'], $this->headersFor($owner, $accountId))
            ->json('data.id');

        $member = $this->addRestrictedAccountUser($accountId, eventIds: [$eventId]);
        $headers = $this->headersFor($member, $accountId);

        $this->getJson("/organizers/$organizerId/profiles", $headers)->assertOk();
        $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Nope'], $headers)->assertForbidden();
        $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $profileId,
            'billing_tier' => 'FEATURED',
        ], $headers)->assertCreated();
    }

    public function test_viewer_can_read_but_not_change_the_lineup(): void
    {
        [, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEvent($accountId, $this->createOrganizer($accountId));
        $viewer = $this->addAccountUser($accountId, Role::VIEWER);
        $headers = $this->headersFor($viewer, $accountId);

        $this->getJson("/events/$eventId/lineup", $headers)->assertOk();
        $this->postJson("/events/$eventId/lineup/spaces", ['name' => 'Main Stage'], $headers)->assertForbidden();
    }

    public function test_profile_photos_can_be_reordered(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $headers = $this->headersFor($owner, $accountId);
        $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Nova'], $headers)->json('data.id');
        $otherProfileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Other'], $headers)->json('data.id');

        $first = $this->insertProfileImage($accountId, $profileId);
        $second = $this->insertProfileImage($accountId, $profileId);
        $third = $this->insertProfileImage($accountId, $profileId);
        $foreign = $this->insertProfileImage($accountId, $otherProfileId);

        $this->postJson("/organizers/$organizerId/profiles/$profileId/images/sort", [
            'sorted_ids' => [$third, $first, $second],
        ], $headers)->assertNoContent();

        $nova = collect($this->getJson("/organizers/$organizerId/profiles", $headers)->json('data'))->firstWhere('id', $profileId);
        $this->assertSame([$third, $first, $second], array_column($nova['images'], 'id'));

        $this->postJson("/organizers/$organizerId/profiles/$profileId/images/sort", [
            'sorted_ids' => [$foreign, $first],
        ], $headers)->assertNotFound();
    }

    public function test_duplicating_an_event_copies_its_lineup_and_moves_times_with_the_new_date(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);
        $eventId = $this->createEvent($accountId, $organizerId);
        DB::table('events')->where('id', $eventId)->update(['type' => 'SINGLE']);
        DB::table('event_occurrences')->insert([
            'short_id' => 'occ_'.uniqid(),
            'event_id' => $eventId,
            'start_date' => '2030-06-14 19:00:00',
            'status' => 'ACTIVE',
            'used_capacity' => 0,
            'is_overridden' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $headers = $this->headersFor($owner, $accountId);

        $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Nova'], $headers)->json('data.id');
        $entryId = $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $profileId,
            'billing_tier' => 'HEADLINER',
            'role_label' => 'Closing set',
        ], $headers)->json('data.id');
        $spaceId = $this->postJson("/events/$eventId/lineup/spaces", ['name' => 'Main Stage'], $headers)->json('data.id');
        $this->postJson("/events/$eventId/lineup/schedule-items", [
            'starts_at' => '2030-06-14 21:00',
            'ends_at' => '2030-06-14 23:00',
            'event_space_id' => $spaceId,
            'lineup_entry_ids' => [$entryId],
        ], $headers)->assertCreated();

        $newEvent = $this->app->make(DuplicateEventService::class)->duplicateEvent(
            eventId: (string) $eventId,
            accountId: (string) $accountId,
            title: 'Next year',
            startDate: '2030-06-21 19:00:00',
            duplicateProducts: false,
            duplicateQuestions: false,
            duplicateSettings: false,
            duplicatePromoCodes: false,
            duplicateCapacityAssignments: false,
            duplicateCheckInLists: false,
            duplicateEventCoverImage: false,
            duplicateTicketLogo: false,
            duplicateWebhooks: false,
            duplicateAffiliates: false,
        );

        $lineup = $this->getJson("/events/{$newEvent->getId()}/lineup", $headers)->assertOk();
        $lineup->assertJsonPath('data.entries.0.profile.id', $profileId)
            ->assertJsonPath('data.entries.0.role_label', 'Closing set')
            ->assertJsonPath('data.spaces.0.name', 'Main Stage')
            ->assertJsonPath('data.schedule_items.0.starts_at', '2030-06-21 21:00:00')
            ->assertJsonPath('data.schedule_items.0.event_space_id', $lineup->json('data.spaces.0.id'))
            ->assertJsonPath('data.schedule_items.0.lineup_entry_ids', [$lineup->json('data.entries.0.id')]);

        $this->assertNotSame($entryId, $lineup->json('data.entries.0.id'));
        $this->getJson("/events/$eventId/lineup", $headers)->assertJsonPath('data.schedule_items.0.starts_at', '2030-06-14 21:00:00');
    }

    public function test_removing_people_clears_the_untitled_slots_they_leave_empty(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        $otherEventId = $this->createEvent($accountId, $organizerId, 'Other');
        $headers = $this->headersFor($owner, $accountId);

        $add = function (int $event, string $name) use ($organizerId, $headers) {
            $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => $name], $headers)->json('data.id');

            return [$profileId, $this->postJson("/events/$event/lineup/entries", ['profile_id' => $profileId, 'billing_tier' => 'STANDARD'], $headers)->json('data.id')];
        };
        $slot = fn (int $event, array $entryIds, ?string $title = null) => $this->postJson("/events/$event/lineup/schedule-items", [
            'title' => $title,
            'starts_at' => '2030-06-14 20:00',
            'ends_at' => '2030-06-14 21:00',
            'lineup_entry_ids' => $entryIds,
        ], $headers)->json('data.id');

        [, $soloEntry] = $add($eventId, 'Solo');
        [, $duoA] = $add($eventId, 'Duo A');
        [, $duoB] = $add($eventId, 'Duo B');
        $soloSlot = $slot($eventId, [$soloEntry]);
        $titledSlot = $slot($eventId, [$soloEntry], 'Panel');
        $duoSlot = $slot($eventId, [$duoA, $duoB]);

        $this->deleteJson("/events/$eventId/lineup/entries/$soloEntry", [], $headers)->assertSuccessful();
        $this->assertDatabaseMissing('event_schedule_items', ['id' => $soloSlot]);
        $this->assertDatabaseHas('event_schedule_items', ['id' => $titledSlot]);
        $this->assertDatabaseHas('event_schedule_items', ['id' => $duoSlot]);

        [$sharedProfile, $sharedEntry] = $add($otherEventId, 'Shared');
        $otherSlot = $slot($otherEventId, [$sharedEntry]);
        $this->deleteJson("/organizers/$organizerId/profiles/$sharedProfile", [], $headers)->assertSuccessful();
        $this->assertDatabaseMissing('event_schedule_items', ['id' => $otherSlot]);
    }

    public function test_updating_a_slot_replaces_its_people_and_converts_times_from_the_event_timezone(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        DB::table('events')->where('id', $eventId)->update(['timezone' => 'America/New_York']);
        $headers = $this->headersFor($owner, $accountId);
        $entry = fn (string $name) => $this->postJson("/events/$eventId/lineup/entries", [
            'profile_id' => $this->postJson("/organizers/$organizerId/profiles", ['name' => $name], $headers)->json('data.id'),
            'billing_tier' => 'STANDARD',
        ], $headers)->json('data.id');
        $first = $entry('First');
        $second = $entry('Second');

        $slotId = $this->postJson("/events/$eventId/lineup/schedule-items", [
            'starts_at' => '2030-01-10 20:00',
            'ends_at' => '2030-01-10 21:00',
            'lineup_entry_ids' => [$first],
        ], $headers)->json('data.id');

        $this->putJson("/events/$eventId/lineup/schedule-items/$slotId", [
            'starts_at' => '2030-07-10 20:00',
            'ends_at' => '2030-07-10 21:30',
            'lineup_entry_ids' => [$second],
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.starts_at', '2030-07-11 00:00:00')
            ->assertJsonPath('data.ends_at', '2030-07-11 01:30:00')
            ->assertJsonPath('data.lineup_entry_ids', [$second]);

        $this->assertDatabaseMissing('event_schedule_item_performers', ['event_schedule_item_id' => $slotId, 'event_lineup_entry_id' => $first]);
    }

    public function test_stages_only_accept_venues_from_their_own_event(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $eventId = $this->createEvent($accountId, $organizerId);
        $otherEventId = $this->createEvent($accountId, $organizerId, 'Other');
        $headers = $this->headersFor($owner, $accountId);
        $locationId = DB::table('locations')->insertGetId([
            'short_id' => 'loc_'.uniqid(),
            'account_id' => $accountId,
            'organizer_id' => $organizerId,
            'name' => 'Riverside Park',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $venue = fn (int $event) => DB::table('event_locations')->insertGetId([
            'short_id' => 'evl_'.uniqid(),
            'event_id' => $event,
            'location_id' => $locationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ownVenue = $venue($eventId);
        $otherVenue = $venue($otherEventId);

        $this->postJson("/events/$eventId/lineup/spaces", ['name' => 'Tent', 'event_location_id' => $otherVenue], $headers)->assertNotFound();
        $spaceId = $this->postJson("/events/$eventId/lineup/spaces", ['name' => 'Tent', 'event_location_id' => $ownVenue], $headers)
            ->assertCreated()
            ->assertJsonPath('data.venue.name', 'Riverside Park')
            ->json('data.id');

        DB::table('event_locations')->where('id', $ownVenue)->update(['deleted_at' => now()]);
        $this->getJson("/events/$eventId/lineup", $headers)
            ->assertJsonPath('data.spaces.0.event_location_id', null)
            ->assertJsonPath('data.spaces.0.venue', null);
        $this->putJson("/events/$eventId/lineup/spaces/$spaceId", ['name' => 'Big Tent', 'event_location_id' => null], $headers)->assertOk();
    }

    public function test_a_profile_can_have_up_to_eight_photos(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $headers = $this->headersFor($owner, $accountId);
        $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Photogenic'], $headers)->json('data.id');
        $upload = fn () => $this->post('/images', [
            'image' => UploadedFile::fake()->image('photo.png', 400, 400),
            'image_type' => 'PROFILE_IMAGE',
            'entity_id' => $profileId,
        ], [...$headers, 'Accept' => 'application/json']);

        foreach (range(1, 8) as $ignored) {
            $upload()->assertSuccessful();
        }

        $upload()->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_people_library_only_lists_events_the_member_can_access(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createOrganizer($accountId);
        $visibleEventId = $this->createEvent($accountId, $organizerId, 'Visible event');
        $otherEventId = $this->createEvent($accountId, $organizerId, 'Other event');
        $ownerHeaders = $this->headersFor($owner, $accountId);

        $profileId = $this->postJson("/organizers/$organizerId/profiles", ['name' => 'Nova'], $ownerHeaders)->json('data.id');
        foreach ([$visibleEventId, $otherEventId] as $eventId) {
            $this->postJson("/events/$eventId/lineup/entries", ['profile_id' => $profileId, 'billing_tier' => 'STANDARD'], $ownerHeaders)
                ->assertCreated();
        }

        $this->getJson("/organizers/$organizerId/profiles", $ownerHeaders)->assertJsonCount(2, 'data.0.appearances');

        $member = $this->addRestrictedAccountUser($accountId, eventIds: [$visibleEventId]);
        $this->getJson("/organizers/$organizerId/profiles", $this->headersFor($member, $accountId))
            ->assertOk()
            ->assertJsonCount(1, 'data.0.appearances')
            ->assertJsonPath('data.0.appearances.0.event_title', 'Visible event');
    }

    private function insertProfileImage(int $accountId, int $profileId): int
    {
        return DB::table('images')->insertGetId([
            'account_id' => $accountId,
            'entity_id' => $profileId,
            'entity_type' => ProfileDomainObject::class,
            'type' => 'PROFILE_IMAGE',
            'filename' => 'photo.png',
            'disk' => 'public',
            'path' => 'profile_image/photo.png',
            'size' => 100,
            'mime_type' => 'image/png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
