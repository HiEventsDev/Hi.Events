<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventSpamCheckContentDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckContentService;
use HiEvents\Services\Domain\Event\EventSpamCheckService;
use HiEvents\Services\Domain\Lineup\LineupHighlightSelector;
use Illuminate\Config\Repository;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EventSpamCheckContentServiceTest extends TestCase
{
    private EventSpamCheckContentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EventSpamCheckContentService(Mockery::mock(EventRepositoryInterface::class), new LineupHighlightSelector);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_collects_content_from_every_public_surface(): void
    {
        $content = $this->service->buildForEvent($this->makeEvent());

        $this->assertSame('Event Title', $content->title);

        $this->assertSame([
            'organizer name' => 'Patches Maker UK',
            'organizer description' => '<p>Organizer bio</p>',
            'organizer website' => 'https://organizer.example',
            'organizer seo title' => 'Organizer SEO title',
            'product page message' => 'Product page message',
            'pre-checkout message' => 'Pre checkout',
            'post-checkout message' => 'Post checkout',
            'offline payment instructions' => 'Bank transfer',
            'email footer message' => 'Footer',
            'online event connection details' => 'Join at the link',
            'seo title' => 'SEO title',
            'product 1 title' => 'General Admission',
            'product 1 description' => '<p>Ticket blurb</p>',
            'category 1 name' => 'Tickets',
            'category 1 description' => '<p>Category blurb</p>',
            'question 1 title' => 'Dietary requirements',
            'question 1 description' => '<p>Question blurb</p>',
        ], $content->supplementaryContent);
    }

    public function test_events_without_a_lineup_keep_the_content_hash_they_had_before_lineups(): void
    {
        $event = $this->makeEvent()
            ->setOrganizer((new OrganizerDomainObject)
                ->setName('Patches Maker UK')
                ->setDescription('<p>Organizer bio</p>')
                ->setWebsite('https://organizer.example')
                ->setOrganizerSettings((new OrganizerSettingDomainObject)
                    ->setWebsiteUrl('https://settings.example')
                    ->setSeoTitle('Organizer SEO title')
                    ->setSeoDescription('Organizer SEO description')
                    ->setSeoKeywords('organizer, keywords')
                    ->setHomepageSections([['id' => 'a', 'type' => 'TEXT', 'heading' => 'Our story', 'body' => '<p>Since 2010</p>']])))
            ->setEventSettings((new EventSettingDomainObject)
                ->setProductPageMessage('Product page message')
                ->setPreCheckoutMessage('Pre checkout')
                ->setPostCheckoutMessage('Post checkout')
                ->setOfflinePaymentInstructions('Bank transfer')
                ->setEmailFooterMessage('Footer')
                ->setOnlineEventConnectionDetails('Join at the link')
                ->setMapsUrl('https://maps.example')
                ->setSeoTitle('SEO title')
                ->setSeoDescription('SEO description')
                ->setSeoKeywords('seo, keywords')
                ->setHomepageSections([['id' => 'f', 'type' => 'FAQ', 'faqs' => [['question' => 'Parking?', 'answer' => '<p>Yes</p>']]]]))
            ->setProducts(new Collection([
                (new ProductDomainObject)->setTitle('General Admission')->setDescription('<p>Ticket blurb</p>'),
                (new ProductDomainObject)->setTitle('VIP')->setDescription('<p>VIP blurb</p>'),
            ]))
            ->setProductCategories(new Collection([
                (new ProductCategoryDomainObject)->setName('Tickets')->setDescription('<p>Category blurb</p>'),
                (new ProductCategoryDomainObject)->setName('Extras'),
            ]));

        $contentBeforeLineups = new EventSpamCheckContentDTO(
            title: 'Event Title',
            description: '<p>Event description</p>',
            supplementaryContent: [
                'organizer name' => 'Patches Maker UK',
                'organizer description' => '<p>Organizer bio</p>',
                'organizer website' => 'https://organizer.example',
                'organizer settings website' => 'https://settings.example',
                'organizer seo title' => 'Organizer SEO title',
                'organizer seo description' => 'Organizer SEO description',
                'organizer seo keywords' => 'organizer, keywords',
                'organizer homepage section 1 heading' => 'Our story',
                'organizer homepage section 1 body' => '<p>Since 2010</p>',
                'product page message' => 'Product page message',
                'pre-checkout message' => 'Pre checkout',
                'post-checkout message' => 'Post checkout',
                'offline payment instructions' => 'Bank transfer',
                'email footer message' => 'Footer',
                'online event connection details' => 'Join at the link',
                'maps url' => 'https://maps.example',
                'seo title' => 'SEO title',
                'seo description' => 'SEO description',
                'seo keywords' => 'seo, keywords',
                'homepage section 1 question 1' => 'Parking?',
                'homepage section 1 answer 1' => '<p>Yes</p>',
                'product 1 title' => 'General Admission',
                'product 1 description' => '<p>Ticket blurb</p>',
                'product 2 title' => 'VIP',
                'product 2 description' => '<p>VIP blurb</p>',
                'category 1 name' => 'Tickets',
                'category 1 description' => '<p>Category blurb</p>',
                'category 2 name' => 'Extras',
                'question 1 title' => 'Dietary requirements',
                'question 1 description' => '<p>Question blurb</p>',
            ],
        );
        $hasher = new EventSpamCheckService(new Repository([]));

        $this->assertSame(
            $hasher->hashContent($contentBeforeLineups),
            $hasher->hashContent($this->service->buildForEvent($event)),
        );
    }

    public function test_collects_lineup_and_schedule_content(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Festival')
            ->setEventLineupEntries(new Collection([
                (new EventLineupEntryDomainObject)
                    ->setId(1)
                    ->setRoleLabel('Headliner')
                    ->setProfile((new ProfileDomainObject)
                        ->setName('Nova Lights')
                        ->setTagline('Berlin techno')
                        ->setBio('<p>Artist bio</p>')
                        ->setLinks([['type' => 'WEBSITE', 'url' => 'https://nova.example'], ['type' => 'SPOTIFY', 'url' => 'https://open.spotify.com/a']])),
            ]))
            ->setEventSpaces(new Collection([(new EventSpaceDomainObject)->setId(1)->setName('Main Stage')]))
            ->setEventScheduleItems(new Collection([
                (new EventScheduleItemDomainObject)->setId(1)->setTitle('Closing set')->setDescription('<p>Set notes</p>'),
            ]));

        $this->assertSame([
            'ticket email lineup 1 name' => 'Nova Lights',
            'ticket email schedule item 1 title' => 'Closing set',
            'space 1 name' => 'Main Stage',
            'lineup 1 role' => 'Headliner',
            'lineup 1 tagline' => 'Berlin techno',
            'lineup 1 bio' => '<p>Artist bio</p>',
            'lineup 1 links' => 'https://nova.example https://open.spotify.com/a',
            'schedule item 1 description' => '<p>Set notes</p>',
        ], $this->service->buildForEvent($event)->supplementaryContent);
    }

    public function test_orders_lineup_like_ticket_emails_with_hidden_entries_last_and_slots_by_start_time(): void
    {
        $entry = fn (int $id, string $tier, int $sortOrder, bool $hidden, string $name) => (new EventLineupEntryDomainObject)
            ->setId($id)
            ->setBillingTier($tier)
            ->setSortOrder($sortOrder)
            ->setIsHidden($hidden)
            ->setProfile((new ProfileDomainObject)->setName($name)->setBio("<p>$name bio</p>"));

        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Festival')
            ->setEventLineupEntries(new Collection([
                $entry(1, 'STANDARD', 0, false, 'Opener'),
                $entry(2, 'HEADLINER', 5, true, 'Secret Headliner'),
                $entry(3, 'FEATURED', 1, false, 'Featured Act'),
                $entry(4, 'HEADLINER', 3, false, 'Second Headliner'),
                $entry(5, 'HEADLINER', 2, false, 'First Headliner'),
            ]))
            ->setEventScheduleItems(new Collection([
                (new EventScheduleItemDomainObject)->setId(1)->setStartsAt('2030-06-14 22:00:00')->setTitle('Late set'),
                (new EventScheduleItemDomainObject)->setId(2)->setStartsAt('2030-06-14 19:00:00')->setTitle('Doors'),
            ]));

        $this->assertSame([
            'ticket email lineup 1 name' => 'First Headliner',
            'ticket email lineup 2 name' => 'Second Headliner',
            'ticket email schedule item 1 title' => 'Doors',
            'ticket email schedule item 2 title' => 'Late set',
            'lineup 3 name' => 'Featured Act',
            'lineup 4 name' => 'Opener',
            'lineup 1 bio' => '<p>First Headliner bio</p>',
            'lineup 2 bio' => '<p>Second Headliner bio</p>',
            'lineup 3 bio' => '<p>Featured Act bio</p>',
            'lineup 4 bio' => '<p>Opener bio</p>',
            'lineup 5 name' => 'Secret Headliner',
            'lineup 5 bio' => '<p>Secret Headliner bio</p>',
        ], $this->service->buildForEvent($event)->supplementaryContent);
    }

    public function test_labels_the_lineup_ticket_emails_show_and_lists_it_first(): void
    {
        $entry = fn (int $id, string $tier, int $sortOrder, string $name) => (new EventLineupEntryDomainObject)
            ->setId($id)
            ->setBillingTier($tier)
            ->setSortOrder($sortOrder)
            ->setProfile((new ProfileDomainObject)->setName($name));
        $performers = fn (int ...$entryIds) => new Collection(array_map(
            fn (int $entryId) => (new EventScheduleItemPerformerDomainObject)->setEventLineupEntryId($entryId),
            $entryIds,
        ));

        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Festival')
            ->setEventSettings((new EventSettingDomainObject)->setEmailFooterMessage('Footer'))
            ->setProducts(new Collection([(new ProductDomainObject)->setTitle('Day ticket')]))
            ->setEventSpaces(new Collection([
                (new EventSpaceDomainObject)->setId(10)->setName('Main Stage'),
                (new EventSpaceDomainObject)->setId(11)->setName('Tent'),
            ]))
            ->setEventLineupEntries(new Collection([
                $entry(1, 'HEADLINER', 0, 'Nova'),
                $entry(2, 'HEADLINER', 1, 'Orbit'),
                $entry(3, 'STANDARD', 2, 'Opener'),
            ]))
            ->setEventScheduleItems(new Collection([
                (new EventScheduleItemDomainObject)->setId(1)->setStartsAt('2030-06-14 18:00:00')->setEventSpaceId(11)->setTitle('Doors'),
                (new EventScheduleItemDomainObject)->setId(2)->setStartsAt('2030-06-14 21:00:00')->setEventSpaceId(10)->setPerformers($performers(1)),
                (new EventScheduleItemDomainObject)->setId(3)->setStartsAt('2030-06-14 22:00:00')->setTitle('Encore')->setPerformers($performers(2, 3)),
            ]));

        $this->assertSame([
            'ticket email lineup 2 name' => 'Orbit',
            'ticket email lineup 1 name' => 'Nova',
            'ticket email space 1 name' => 'Main Stage',
            'ticket email schedule item 3 title' => 'Encore',
            'lineup 3 name' => 'Opener',
            'schedule item 1 title' => 'Doors',
            'space 2 name' => 'Tent',
            'email footer message' => 'Footer',
            'product 1 title' => 'Day ticket',
        ], $this->service->buildForEvent($event)->supplementaryContent);
    }

    public function test_collects_homepage_section_content(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Festival')
            ->setEventSettings((new EventSettingDomainObject)->setHomepageSections([
                ['id' => 'a', 'type' => 'TEXT', 'heading' => 'Travel', 'body' => '<p>Bus</p>'],
                ['id' => 'b', 'type' => 'FAQ', 'faqs' => [['question' => 'Parking?', 'answer' => '<p>Yes</p>']]],
                ['id' => 'c', 'type' => 'SPONSORS', 'sponsors' => [['image_id' => 1, 'name' => 'Acme', 'url' => 'https://acme.example']]],
            ]));

        $content = $this->service->buildForEvent($event)->supplementaryContent;

        $this->assertSame('Travel', $content['homepage section 1 heading']);
        $this->assertSame('<p>Bus</p>', $content['homepage section 1 body']);
        $this->assertSame('Parking?', $content['homepage section 2 question 1']);
        $this->assertSame('Acme https://acme.example', $content['homepage section 3 sponsor 1']);
    }

    public function test_collects_organizer_homepage_section_content(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Festival')
            ->setOrganizer((new OrganizerDomainObject)
                ->setName('Promoter')
                ->setOrganizerSettings((new OrganizerSettingDomainObject)->setHomepageSections([
                    ['id' => 'events', 'type' => 'EVENTS', 'enabled' => true],
                    ['id' => 'a', 'type' => 'TEXT', 'heading' => 'Our story', 'body' => '<p>Since 2010</p>'],
                    ['id' => 'v', 'type' => 'VIDEO', 'video_url' => 'https://youtu.be/abcdefgh'],
                ])));

        $content = $this->service->buildForEvent($event)->supplementaryContent;

        $this->assertSame('Our story', $content['organizer homepage section 2 heading']);
        $this->assertSame('<p>Since 2010</p>', $content['organizer homepage section 2 body']);
        $this->assertSame('https://youtu.be/abcdefgh', $content['organizer homepage section 3 video']);
    }

    public function test_all_html_covers_description_and_supplementary_content(): void
    {
        $this->assertContains('<p>Ticket blurb</p>', $this->service->buildForEvent($this->makeEvent())->allHtml());
        $this->assertContains('<p>Event description</p>', $this->service->buildForEvent($this->makeEvent())->allHtml());
    }

    public function test_omits_missing_relations_and_blank_values(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Bare Event')
            ->setDescription(null);

        $content = $this->service->buildForEvent($event);

        $this->assertSame([], $content->supplementaryContent);
        $this->assertSame([], $content->allHtml());
    }

    private function makeEvent(): EventDomainObject
    {
        return (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Event Title')
            ->setDescription('<p>Event description</p>')
            ->setOrganizer(
                (new OrganizerDomainObject)
                    ->setName('Patches Maker UK')
                    ->setDescription('<p>Organizer bio</p>')
                    ->setWebsite('https://organizer.example')
                    ->setOrganizerSettings((new OrganizerSettingDomainObject)->setSeoTitle('Organizer SEO title'))
            )
            ->setEventSettings(
                (new EventSettingDomainObject)
                    ->setProductPageMessage('Product page message')
                    ->setPreCheckoutMessage('Pre checkout')
                    ->setPostCheckoutMessage('Post checkout')
                    ->setOfflinePaymentInstructions('Bank transfer')
                    ->setEmailFooterMessage('Footer')
                    ->setOnlineEventConnectionDetails('Join at the link')
                    ->setSeoTitle('SEO title')
            )
            ->setProducts(new Collection([
                (new ProductDomainObject)->setTitle('General Admission')->setDescription('<p>Ticket blurb</p>'),
            ]))
            ->setProductCategories(new Collection([
                (new ProductCategoryDomainObject)->setName('Tickets')->setDescription('<p>Category blurb</p>'),
            ]))
            ->setQuestions(new Collection([
                (new QuestionDomainObject)->setTitle('Dietary requirements')->setDescription('<p>Question blurb</p>'),
            ]));
    }
}
