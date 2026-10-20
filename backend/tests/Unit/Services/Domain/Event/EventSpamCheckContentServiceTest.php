<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerSettingDomainObject;
use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\DomainObjects\QuestionDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Event\EventSpamCheckContentService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EventSpamCheckContentServiceTest extends TestCase
{
    private EventSpamCheckContentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EventSpamCheckContentService(Mockery::mock(EventRepositoryInterface::class));
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

    public function test_collects_lineup_and_schedule_content(): void
    {
        $event = (new EventDomainObject)
            ->setId(1)
            ->setAccountId(9)
            ->setTitle('Festival')
            ->setEventLineupEntries(new Collection([
                (new EventLineupEntryDomainObject)
                    ->setRoleLabel('Headliner')
                    ->setProfile((new ProfileDomainObject)
                        ->setName('Nova Lights')
                        ->setTagline('Berlin techno')
                        ->setBio('<p>Artist bio</p>')
                        ->setLinks([['type' => 'WEBSITE', 'url' => 'https://nova.example'], ['type' => 'SPOTIFY', 'url' => 'https://open.spotify.com/a']])),
            ]))
            ->setEventSpaces(new Collection([(new EventSpaceDomainObject)->setName('Main Stage')]))
            ->setEventScheduleItems(new Collection([
                (new EventScheduleItemDomainObject)->setTitle('Closing set')->setDescription('<p>Set notes</p>'),
            ]));

        $this->assertSame([
            'lineup 1 role' => 'Headliner',
            'lineup 1 name' => 'Nova Lights',
            'lineup 1 tagline' => 'Berlin techno',
            'lineup 1 bio' => '<p>Artist bio</p>',
            'lineup 1 links' => 'https://nova.example https://open.spotify.com/a',
            'space 1 name' => 'Main Stage',
            'schedule item 1 title' => 'Closing set',
            'schedule item 1 description' => '<p>Set notes</p>',
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
