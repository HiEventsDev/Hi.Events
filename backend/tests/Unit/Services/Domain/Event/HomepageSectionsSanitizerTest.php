<?php

namespace Tests\Unit\Services\Domain\Event;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;
use HiEvents\Services\Domain\Event\HomepageSectionsSanitizer;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Support\Collection;
use Mockery as m;
use Tests\TestCase;

class HomepageSectionsSanitizerTest extends TestCase
{
    private HomepageSectionsSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $purifier = m::mock(HtmlPurifierService::class);
        $purifier->shouldReceive('purify')->andReturnUsing(fn (?string $html) => $html === null ? null : str_replace('<script>x</script>', '', $html));

        $images = m::mock(ImageRepositoryInterface::class);
        $images->shouldReceive('findWhere')->with(m::on(fn ($where) => $where['type'] === 'EVENT_GALLERY'))
            ->andReturn(new Collection([(new ImageDomainObject)->setId(10), (new ImageDomainObject)->setId(11)]));
        $images->shouldReceive('findWhere')->with(m::on(fn ($where) => $where['type'] === 'EVENT_SPONSOR_LOGO'))
            ->andReturn(new Collection([(new ImageDomainObject)->setId(20)]));
        $images->shouldReceive('findWhere')->with(m::on(fn ($where) => $where['type'] === 'ORGANIZER_GALLERY'
            && $where['entity_type'] === OrganizerDomainObject::class
            && $where['entity_id'] === 7))
            ->andReturn(new Collection([(new ImageDomainObject)->setId(30)]));
        $images->shouldReceive('findWhere')->with(m::on(fn ($where) => $where['type'] === 'ORGANIZER_SPONSOR_LOGO'))
            ->andReturn(new Collection([(new ImageDomainObject)->setId(40)]));

        $this->sanitizer = new HomepageSectionsSanitizer($purifier, $images);
    }

    public function test_cleans_content_and_drops_what_does_not_belong_to_the_event(): void
    {
        $result = $this->sanitizer->sanitizeForEvent([
            ['id' => 'about', 'type' => 'ABOUT', 'enabled' => false, 'heading' => ' <b>Info</b> '],
            ['id' => 'about-again', 'type' => 'ABOUT', 'enabled' => true],
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => false, 'heading' => 'Passes'],
            ['id' => 't1', 'type' => 'TEXT', 'body' => '<p>Hi<script>x</script></p>'],
            ['id' => 'v1', 'type' => 'VIDEO', 'video_url' => 'https://evil.example/watch'],
            ['id' => 'g1', 'type' => 'GALLERY', 'image_ids' => [11, 99, 10, 11]],
            ['id' => 's1', 'type' => 'SPONSORS', 'sponsors' => [
                ['image_id' => 20, 'name' => 'Acme', 'url' => 'http://insecure.example'],
                ['image_id' => 77, 'name' => 'Not ours'],
            ]],
            ['id' => 'f1', 'type' => 'FAQ', 'faqs' => [['question' => '  ', 'answer' => 'x'], ['question' => 'Parking?', 'answer' => '<p>Yes</p>']]],
        ], 5);

        $this->assertSame([
            ['id' => 'about', 'type' => 'ABOUT', 'enabled' => false, 'heading' => 'Info'],
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => true],
            ['id' => 't1', 'type' => 'TEXT', 'enabled' => true, 'body' => '<p>Hi</p>'],
            ['id' => 'v1', 'type' => 'VIDEO', 'enabled' => true],
            ['id' => 'g1', 'type' => 'GALLERY', 'enabled' => true, 'image_ids' => [11, 10]],
            ['id' => 's1', 'type' => 'SPONSORS', 'enabled' => true, 'sponsors' => [['image_id' => 20, 'name' => 'Acme']]],
            ['id' => 'f1', 'type' => 'FAQ', 'enabled' => true, 'faqs' => [['question' => 'Parking?', 'answer' => '<p>Yes</p>']]],
        ], $result);
    }

    public function test_adds_tickets_when_missing(): void
    {
        $result = $this->sanitizer->sanitizeForEvent([['id' => 'v1', 'type' => 'VIDEO', 'video_url' => 'https://youtu.be/abcdefgh']], 5);

        $this->assertSame('TICKETS', $result[0]['type']);
        $this->assertTrue($result[0]['enabled']);
        $this->assertSame('https://youtu.be/abcdefgh', $result[1]['video_url']);
    }

    public function test_drops_organizer_sections_from_event_pages(): void
    {
        $result = $this->sanitizer->sanitizeForEvent([
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => true],
            ['id' => 'events', 'type' => 'EVENTS', 'enabled' => true],
            ['id' => 'contact', 'type' => 'CONTACT', 'enabled' => true],
        ], 5);

        $this->assertSame(['TICKETS'], array_column($result, 'type'));
    }

    public function test_event_pages_keep_the_more_events_section_with_its_heading_and_visibility(): void
    {
        $result = $this->sanitizer->sanitizeForEvent([
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => true],
            ['id' => 'more_events', 'type' => 'MORE_EVENTS', 'enabled' => false, 'heading' => ' <i>Also on</i> '],
            ['id' => 'more-again', 'type' => 'MORE_EVENTS', 'enabled' => true],
        ], 5);

        $this->assertSame([
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => true],
            ['id' => 'more_events', 'type' => 'MORE_EVENTS', 'enabled' => false, 'heading' => 'Also on'],
        ], $result);
    }

    public function test_organizer_sections_keep_events_shown_and_only_use_organizer_images(): void
    {
        $result = $this->sanitizer->sanitizeForOrganizer([
            ['id' => 'about', 'type' => 'ABOUT', 'enabled' => true],
            ['id' => 'contact', 'type' => 'CONTACT', 'enabled' => false],
            ['id' => 'past_events', 'type' => 'PAST_EVENTS', 'enabled' => false],
            ['id' => 'past-again', 'type' => 'PAST_EVENTS', 'enabled' => true],
            ['id' => 'tickets', 'type' => 'TICKETS', 'enabled' => true],
            ['id' => 'lineup', 'type' => 'LINEUP', 'enabled' => true],
            ['id' => 'more_events', 'type' => 'MORE_EVENTS', 'enabled' => true],
            ['id' => 'g1', 'type' => 'GALLERY', 'image_ids' => [30, 10]],
            ['id' => 's1', 'type' => 'SPONSORS', 'sponsors' => [['image_id' => 40, 'url' => 'https://acme.example'], ['image_id' => 20]]],
        ], 7);

        $this->assertSame([
            ['id' => 'events', 'type' => 'EVENTS', 'enabled' => true],
            ['id' => 'about', 'type' => 'ABOUT', 'enabled' => true],
            ['id' => 'contact', 'type' => 'CONTACT', 'enabled' => false],
            ['id' => 'past_events', 'type' => 'PAST_EVENTS', 'enabled' => false],
            ['id' => 'g1', 'type' => 'GALLERY', 'enabled' => true, 'image_ids' => [30]],
            ['id' => 's1', 'type' => 'SPONSORS', 'enabled' => true, 'sponsors' => [['image_id' => 40, 'url' => 'https://acme.example']]],
        ], $result);
    }

    public function test_organizer_events_section_cannot_be_hidden(): void
    {
        $result = $this->sanitizer->sanitizeForOrganizer([['id' => 'events', 'type' => 'EVENTS', 'enabled' => false]], 7);

        $this->assertSame([['id' => 'events', 'type' => 'EVENTS', 'enabled' => true]], $result);
    }
}
