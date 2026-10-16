<?php

namespace Tests\Feature\Http\Actions\Lineup;

use HiEvents\DomainObjects\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class LineupImportExportTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        [$owner, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
        DB::table('events')->where('id', $this->eventId)->update(['timezone' => 'Europe/Dublin']);
        $this->headers = $this->headersFor($owner, $this->accountId);
    }

    public function test_import_creates_people_stages_and_slots_and_skips_them_when_reimported(): void
    {
        $existingProfileId = $this->postJson("/organizers/$this->organizerId/profiles", [
            'name' => 'Nova Lights',
            'bio' => '<p><strong>Already</strong> here</p>',
            'links' => ['https://nova.example.com'],
        ], $this->headers)->json('data.id');

        $payload = [
            'people' => [
                [
                    'name' => 'nova lights',
                    'tagline' => 'Berlin techno',
                    'bio' => '<p>Already here</p>',
                    'links' => ['https://open.spotify.com/artist/abc'],
                    'billing_tier' => 'HEADLINER',
                    'role_label' => 'Friday closer',
                    'is_hidden' => false,
                ],
                ['name' => 'Ada Byron', 'billing_tier' => null],
            ],
            'slots' => [
                [
                    'starts_at' => '2030-06-14 23:00:00',
                    'ends_at' => '2030-06-15 00:30:00',
                    'stage' => 'Main Stage',
                    'people' => ['Nova Lights'],
                ],
                [
                    'starts_at' => '2030-06-14 19:00:00',
                    'ends_at' => '2030-06-14 19:30:00',
                    'stage' => 'main stage ',
                    'title' => 'Doors',
                    'people' => [],
                ],
                [
                    'starts_at' => '2030-06-14 20:00:00',
                    'ends_at' => '2030-06-14 20:45:00',
                    'stage' => 'Tent',
                    'people' => ['Surprise Guest'],
                ],
            ],
        ];

        $this->postJson("/events/$this->eventId/lineup/import", $payload, $this->headers)
            ->assertOk()
            ->assertJson([
                'people_created' => 2,
                'people_updated' => 1,
                'people_added' => 3,
                'stages_created' => 2,
                'slots_created' => 3,
                'slots_skipped' => 0,
            ]);

        $this->assertDatabaseCount('profiles', 3);
        $profile = DB::table('profiles')->where('id', $existingProfileId)->first();
        $this->assertSame('Nova Lights', $profile->name);
        $this->assertSame('Berlin techno', $profile->tagline);
        $this->assertSame('<p><strong>Already</strong> here</p>', $profile->bio);
        $this->assertCount(2, json_decode($profile->links, true));
        $this->assertDatabaseHas('event_lineup_entries', [
            'event_id' => $this->eventId,
            'profile_id' => $existingProfileId,
            'billing_tier' => 'HEADLINER',
            'role_label' => 'Friday closer',
        ]);
        $this->assertDatabaseHas('event_schedule_items', [
            'event_id' => $this->eventId,
            'starts_at' => '2030-06-14 22:00:00',
            'ends_at' => '2030-06-14 23:30:00',
        ]);

        $this->postJson("/events/$this->eventId/lineup/import", $payload, $this->headers)
            ->assertOk()
            ->assertJson([
                'people_created' => 0,
                'people_updated' => 0,
                'people_added' => 0,
                'stages_created' => 0,
                'slots_created' => 0,
                'slots_skipped' => 3,
            ]);
    }

    public function test_import_matches_the_profile_already_on_the_lineup_and_keeps_details_left_blank(): void
    {
        $older = $this->postJson("/organizers/$this->organizerId/profiles", ['name' => 'DJ Sam'], $this->headers)->json('data.id');
        $onLineup = $this->postJson("/organizers/$this->organizerId/profiles", [
            'name' => 'dj  sam',
            'tagline' => 'House all night',
            'bio' => '<p><em>Original</em> bio</p>',
            'links' => ['https://sam.example.com'],
        ], $this->headers)->json('data.id');
        $this->postJson("/events/$this->eventId/lineup/entries", [
            'profile_id' => $onLineup,
            'billing_tier' => 'FEATURED',
            'role_label' => 'Late set',
        ], $this->headers)->assertCreated();

        $otherOrganizer = $this->createOrganizer($this->accountId, 'Other');
        $this->postJson("/organizers/$otherOrganizer/profiles", ['name' => 'Ada Byron'], $this->headers)->assertCreated();

        $this->postJson("/events/$this->eventId/lineup/import", [
            'people' => [
                ['name' => 'DJ Sam', 'tagline' => null, 'bio' => null, 'links' => [], 'billing_tier' => null, 'role_label' => null, 'is_hidden' => null],
                ['name' => 'Ada Byron'],
            ],
            'slots' => [],
        ], $this->headers)
            ->assertOk()
            ->assertJson(['people_created' => 1, 'people_updated' => 0, 'people_added' => 1]);

        $this->assertDatabaseMissing('event_lineup_entries', ['event_id' => $this->eventId, 'profile_id' => $older]);
        $this->assertDatabaseHas('event_lineup_entries', [
            'event_id' => $this->eventId,
            'profile_id' => $onLineup,
            'billing_tier' => 'FEATURED',
            'role_label' => 'Late set',
        ]);
        $profile = DB::table('profiles')->where('id', $onLineup)->first();
        $this->assertSame('House all night', $profile->tagline);
        $this->assertSame('<p><em>Original</em> bio</p>', $profile->bio);
        $this->assertSame(1, DB::table('profiles')->where('organizer_id', $this->organizerId)->where('name', 'Ada Byron')->count());

        $this->postJson("/events/$this->eventId/lineup/import", [
            'people' => [['name' => 'DJ SAM', 'billing_tier' => 'HEADLINER', 'is_hidden' => true]],
            'slots' => [],
        ], $this->headers)
            ->assertOk()
            ->assertJson(['people_created' => 0, 'people_updated' => 1, 'people_added' => 0]);
        $this->assertDatabaseHas('event_lineup_entries', ['profile_id' => $onLineup, 'billing_tier' => 'HEADLINER', 'is_hidden' => true]);
    }

    public function test_import_rejects_slots_that_end_before_they_start_or_have_nothing_in_them(): void
    {
        $this->postJson("/events/$this->eventId/lineup/import", [
            'people' => [],
            'slots' => [
                ['starts_at' => '2030-06-14 20:00:00', 'ends_at' => '2030-06-14 19:00:00', 'title' => 'Backwards'],
                ['starts_at' => '2030-06-14 20:00:00', 'ends_at' => '2030-06-14 21:00:00'],
            ],
        ], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slots.0.ends_at', 'slots.1.title']);

        $this->assertDatabaseMissing('event_schedule_items', ['event_id' => $this->eventId]);
    }

    public function test_import_caps_the_number_of_people_it_can_create(): void
    {
        $slots = array_map(fn (int $index) => [
            'starts_at' => '2030-06-14 20:00:00',
            'ends_at' => '2030-06-14 21:00:00',
            'people' => array_map(fn (int $person) => "Person $index-$person", range(1, 50)),
        ], range(1, 11));

        $this->postJson("/events/$this->eventId/lineup/import", ['people' => [], 'slots' => $slots], $this->headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['people']);

        $this->assertDatabaseMissing('profiles', ['organizer_id' => $this->organizerId]);
    }

    public function test_updating_an_entry_without_is_hidden_keeps_it_hidden(): void
    {
        $profileId = $this->postJson("/organizers/$this->organizerId/profiles", ['name' => 'Secret Guest'], $this->headers)->json('data.id');
        $entryId = $this->postJson("/events/$this->eventId/lineup/entries", [
            'profile_id' => $profileId,
            'billing_tier' => 'STANDARD',
            'is_hidden' => true,
        ], $this->headers)->json('data.id');

        $this->putJson("/events/$this->eventId/lineup/entries/$entryId", ['billing_tier' => 'HEADLINER'], $this->headers)->assertOk();

        $this->assertDatabaseHas('event_lineup_entries', ['id' => $entryId, 'billing_tier' => 'HEADLINER', 'is_hidden' => true]);
    }

    public function test_disabled_sections_are_not_published(): void
    {
        DB::table('events')->where('id', $this->eventId)->update(['status' => 'LIVE']);
        $this->postJson("/events/$this->eventId/lineup/import", [
            'people' => [['name' => 'Unannounced Act'], ['name' => 'Scheduled Act']],
            'slots' => [['starts_at' => '2030-06-14 20:00:00', 'ends_at' => '2030-06-14 21:00:00', 'people' => ['Scheduled Act']]],
        ], $this->headers)->assertOk();
        DB::table('event_settings')->updateOrInsert(['event_id' => $this->eventId], ['homepage_sections' => json_encode([
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => true],
            ['id' => 'lineup', 'type' => 'LINEUP', 'enabled' => false],
            ['id' => 'faq', 'type' => 'FAQ', 'enabled' => false, 'faqs' => [['question' => 'Draft question', 'answer' => '<p>Draft</p>']]],
            ['id' => 'about', 'type' => 'ABOUT', 'enabled' => false],
        ]), 'created_at' => now(), 'updated_at' => now()]);

        $lineup = $this->getJson("/public/events/$this->eventId/lineup")->assertOk();
        $this->assertSame(['Scheduled Act'], collect($lineup->json('data.entries'))->pluck('profile.name')->all());
        $this->assertCount(1, $lineup->json('data.schedule_items'));

        $sections = collect($this->getJson("/public/events/$this->eventId")->assertOk()->json('data.settings.homepage_sections'));
        $this->assertSame(['TICKETS', 'LINEUP', 'ABOUT'], $sections->pluck('type')->all());
        $this->assertStringNotContainsString('Draft question', $sections->toJson());
    }

    public function test_only_members_who_can_edit_the_organizer_can_import(): void
    {
        $payload = ['people' => [['name' => 'Nope']], 'slots' => []];

        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $this->postJson("/events/$this->eventId/lineup/import", $payload, $this->headersFor($viewer, $this->accountId))
            ->assertForbidden();

        $eventOnly = $this->addRestrictedAccountUser($this->accountId, eventIds: [$this->eventId]);
        $this->postJson("/events/$this->eventId/lineup/import", $payload, $this->headersFor($eventOnly, $this->accountId))
            ->assertForbidden();

        $this->assertDatabaseMissing('profiles', ['name' => 'Nope']);
    }

    public function test_export_downloads_a_spreadsheet_for_read_only_members(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        $this->get("/events/$this->eventId/lineup/export", $this->headersFor($viewer, $this->accountId))
            ->assertOk()
            ->assertDownload();
    }
}
