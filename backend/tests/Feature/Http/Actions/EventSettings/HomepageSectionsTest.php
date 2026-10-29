<?php

namespace Tests\Feature\Http\Actions\EventSettings;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Services\Domain\Event\DuplicateEventService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class HomepageSectionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_sections_are_saved_cleaned_and_served_on_the_public_event(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);
        DB::table('events')->where('id', $eventId)->update(['status' => EventStatus::LIVE->name]);
        $galleryImageId = DB::table('images')->insertGetId([
            'account_id' => $accountId,
            'entity_id' => $eventId,
            'entity_type' => EventDomainObject::class,
            'type' => 'EVENT_GALLERY',
            'filename' => 'photo.png',
            'disk' => 'public',
            'path' => 'event_gallery/photo.png',
            'size' => 100,
            'mime_type' => 'image/png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $headers = $this->headersFor($owner, $accountId);

        $this->patchJson("/events/$eventId/settings", [
            'homepage_sections' => [
                ['id' => 'about', 'type' => 'ABOUT', 'enabled' => true],
                ['id' => 'lineup', 'type' => 'LINEUP', 'enabled' => false, 'heading' => 'Artists'],
                ['id' => 'travel', 'type' => 'TEXT', 'enabled' => true, 'heading' => 'Travel', 'body' => '<p>Bus<script>alert(1)</script></p>'],
                ['id' => 'photos', 'type' => 'GALLERY', 'enabled' => true, 'image_ids' => [$galleryImageId, 999999]],
            ],
        ], $headers)->assertOk();

        $sections = $this->getJson("/public/events/$eventId")->assertOk()->json('data.settings.homepage_sections');

        $this->assertSame(['tickets', 'about', 'lineup', 'travel', 'photos'], array_column($sections, 'id'));
        $this->assertFalse($sections[2]['enabled']);
        $this->assertSame('Artists', $sections[2]['heading']);
        $this->assertStringNotContainsString('script', $sections[3]['body']);
        $this->assertSame([$galleryImageId], $sections[4]['image_ids']);
    }

    public function test_the_more_events_section_keeps_its_heading_and_can_be_hidden(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);
        DB::table('events')->where('id', $eventId)->update(['status' => EventStatus::LIVE->name]);

        $this->patchJson("/events/$eventId/settings", [
            'homepage_sections' => [
                ['id' => 'more_events', 'type' => 'MORE_EVENTS', 'enabled' => false, 'heading' => 'Also on'],
            ],
        ], $this->headersFor($owner, $accountId))->assertOk();

        $sections = $this->getJson("/public/events/$eventId")->assertOk()->json('data.settings.homepage_sections');

        $this->assertSame(['tickets', 'more_events'], array_column($sections, 'id'));
        $this->assertFalse($sections[1]['enabled']);
        $this->assertSame('Also on', $sections[1]['heading']);
    }

    public function test_every_built_in_plus_thirty_four_custom_sections_can_be_saved(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);

        $builtIns = array_map(
            fn (string $type) => ['id' => strtolower($type), 'type' => $type],
            ['TICKETS', 'ABOUT', 'LINEUP', 'SCHEDULE', 'LOCATION', 'ORGANIZER', 'MORE_EVENTS'],
        );
        $custom = array_map(fn (int $i) => ['id' => "text_$i", 'type' => 'TEXT', 'body' => "<p>$i</p>"], range(1, 34));

        $this->patchJson("/events/$eventId/settings", [
            'homepage_sections' => [...$builtIns, ...$custom],
        ], $this->headersFor($owner, $accountId))->assertOk();

        $this->patchJson("/events/$eventId/settings", [
            'homepage_sections' => [...$builtIns, ...$custom, ['id' => 'text_35', 'type' => 'TEXT', 'body' => '<p>35</p>']],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('homepage_sections');
    }

    public function test_video_links_must_be_https(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);

        $this->patchJson("/events/$eventId/settings", [
            'homepage_sections' => [['id' => 'v', 'type' => 'VIDEO', 'video_url' => 'http://youtube.com/watch?v=abc']],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('homepage_sections.0.video_url');
    }

    public function test_duplicating_an_event_gives_the_copy_its_own_gallery_and_sponsor_images(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $eventId = $this->createEventWithSettings($owner, $accountId);
        $image = fn (string $type) => DB::table('images')->insertGetId([
            'account_id' => $accountId,
            'entity_id' => $eventId,
            'entity_type' => EventDomainObject::class,
            'type' => $type,
            'filename' => 'photo.png',
            'disk' => 'public',
            'path' => strtolower($type).'/photo.png',
            'size' => 100,
            'mime_type' => 'image/png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $galleryImageId = $image('EVENT_GALLERY');
        $sponsorImageId = $image('EVENT_SPONSOR_LOGO');

        $this->patchJson("/events/$eventId/settings", [
            'homepage_sections' => [
                ['id' => 'photos', 'type' => 'GALLERY', 'enabled' => true, 'image_ids' => [$galleryImageId]],
                ['id' => 'thanks', 'type' => 'SPONSORS', 'enabled' => true, 'sponsors' => [['image_id' => $sponsorImageId, 'name' => 'Acme']]],
            ],
        ], $this->headersFor($owner, $accountId))->assertOk();

        $copy = $this->app->make(DuplicateEventService::class)->duplicateEvent(
            eventId: (string) $eventId,
            accountId: (string) $accountId,
            title: 'Copy',
            startDate: '2031-06-14 19:00:00',
            duplicateProducts: false,
            duplicateQuestions: false,
            duplicatePromoCodes: false,
            duplicateCapacityAssignments: false,
            duplicateCheckInLists: false,
            duplicateEventCoverImage: false,
            duplicateTicketLogo: false,
            duplicateWebhooks: false,
            duplicateAffiliates: false,
            duplicateLineup: false,
        );

        $sections = collect(json_decode(DB::table('event_settings')->where('event_id', $copy->getId())->value('homepage_sections'), true))->keyBy('id');
        $galleryCopy = $sections['photos']['image_ids'][0];
        $sponsorCopy = $sections['thanks']['sponsors'][0]['image_id'];

        $this->assertNotSame($galleryImageId, $galleryCopy);
        $this->assertDatabaseHas('images', ['id' => $galleryCopy, 'entity_id' => $copy->getId(), 'type' => 'EVENT_GALLERY', 'path' => 'event_gallery/photo.png']);
        $this->assertDatabaseHas('images', ['id' => $sponsorCopy, 'entity_id' => $copy->getId(), 'type' => 'EVENT_SPONSOR_LOGO']);
        $this->assertSame('Acme', $sections['thanks']['sponsors'][0]['name']);
    }

    private function createEventWithSettings($owner, int $accountId): int
    {
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);

        return $this->postJson('/events', [
            'title' => 'Sections test',
            'type' => 'SINGLE',
            'organizer_id' => $organizerId,
            'start_date' => '2030-06-14 19:00',
            'currency' => 'EUR',
            'timezone' => 'UTC',
        ], $this->headersFor($owner, $accountId))->assertSuccessful()->json('data.id');
    }
}
