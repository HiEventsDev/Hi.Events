<?php

namespace Tests\Feature\Http\Actions\Organizers;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrganizerStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class OrganizerHomepageSectionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    public function test_sections_are_saved_cleaned_and_served_on_the_public_organizer(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createLiveOrganizerWithSettings($accountId);
        $galleryImageId = $this->createImage($accountId, $organizerId, OrganizerDomainObject::class, 'ORGANIZER_GALLERY');
        $eventGalleryImageId = $this->createImage($accountId, $organizerId, EventDomainObject::class, 'EVENT_GALLERY');

        $this->patchJson("/organizers/$organizerId/settings", [
            'homepage_sections' => [
                ['id' => 'about', 'type' => 'ABOUT', 'enabled' => true],
                ['id' => 'contact', 'type' => 'CONTACT', 'enabled' => false, 'heading' => 'Say hello'],
                ['id' => 'story', 'type' => 'TEXT', 'enabled' => true, 'heading' => 'Our story', 'body' => '<p>Since 2010<script>alert(1)</script></p>'],
                ['id' => 'photos', 'type' => 'GALLERY', 'enabled' => true, 'image_ids' => [$galleryImageId, $eventGalleryImageId]],
                ['id' => 'past_events', 'type' => 'PAST_EVENTS', 'enabled' => true],
            ],
        ], $this->headersFor($owner, $accountId))->assertOk();

        $sections = $this->getJson("/public/organizers/$organizerId")->assertOk()->json('data.settings.homepage_sections');

        $this->assertSame(['events', 'about', 'contact', 'story', 'photos', 'past_events'], array_column($sections, 'id'));
        $this->assertFalse($sections[2]['enabled']);
        $this->assertSame('Say hello', $sections[2]['heading']);
        $this->assertStringNotContainsString('script', $sections[3]['body']);
        $this->assertSame([$galleryImageId], $sections[4]['image_ids']);
    }

    public function test_event_only_sections_are_rejected(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createLiveOrganizerWithSettings($accountId);

        $this->patchJson("/organizers/$organizerId/settings", [
            'homepage_sections' => [['id' => 'more_events', 'type' => 'MORE_EVENTS', 'enabled' => true]],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('homepage_sections.0.type');
    }

    public function test_other_settings_updates_keep_saved_sections(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createLiveOrganizerWithSettings($accountId);
        $headers = $this->headersFor($owner, $accountId);

        $this->patchJson("/organizers/$organizerId/settings", [
            'homepage_sections' => [['id' => 'faq', 'type' => 'FAQ', 'faqs' => [['question' => 'Refunds?', 'answer' => '<p>Yes</p>']]]],
        ], $headers)->assertOk();

        $sections = $this->patchJson("/organizers/$organizerId/settings", ['seo_title' => 'Promoter'], $headers)
            ->assertOk()
            ->json('data.homepage_sections');

        $this->assertSame(['events', 'faq'], array_column($sections, 'id'));
    }

    public function test_event_only_section_types_are_rejected(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createLiveOrganizerWithSettings($accountId);

        $this->patchJson("/organizers/$organizerId/settings", [
            'homepage_sections' => [['id' => 'tickets', 'type' => 'TICKETS']],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('homepage_sections.0.type');
    }

    public function test_sponsor_links_must_be_https(): void
    {
        [$owner, $accountId] = $this->createAccountOwner();
        $organizerId = $this->createLiveOrganizerWithSettings($accountId);

        $this->patchJson("/organizers/$organizerId/settings", [
            'homepage_sections' => [['id' => 's', 'type' => 'SPONSORS', 'sponsors' => [['image_id' => 1, 'url' => 'http://acme.example']]]],
        ], $this->headersFor($owner, $accountId))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('homepage_sections.0.sponsors.0.url');
    }

    private function createLiveOrganizerWithSettings(int $accountId): int
    {
        $organizerId = $this->createOrganizer($accountId);
        DB::table('organizers')->where('id', $organizerId)->update(['status' => OrganizerStatus::LIVE->name]);
        DB::table('organizer_settings')->insert(['organizer_id' => $organizerId, 'created_at' => now(), 'updated_at' => now()]);

        return $organizerId;
    }

    private function createImage(int $accountId, int $entityId, string $entityType, string $type): int
    {
        return DB::table('images')->insertGetId([
            'account_id' => $accountId,
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'type' => $type,
            'filename' => 'photo.png',
            'disk' => 'public',
            'path' => 'gallery/photo.png',
            'size' => 100,
            'mime_type' => 'image/png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
