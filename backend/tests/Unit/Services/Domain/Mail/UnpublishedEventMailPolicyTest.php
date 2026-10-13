<?php

namespace Tests\Unit\Services\Domain\Mail;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\UserRepositoryInterface;
use HiEvents\Services\Domain\Mail\UnpublishedEventMailPolicy;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnpublishedEventMailPolicyTest extends TestCase
{
    private EventRepositoryInterface|MockInterface $eventRepository;

    private UserRepositoryInterface|MockInterface $userRepository;

    private UnpublishedEventMailPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->policy = new UnpublishedEventMailPolicy($this->eventRepository, $this->userRepository);

        $this->userRepository->shouldReceive('findUsersByAccountId')->with(9)->andReturn(collect([
            (new UserDomainObject)->setEmail('Owner@Example.com')->setEmailVerifiedAt('2026-01-01 00:00:00'),
            (new UserDomainObject)->setEmail('unverified@example.com')->setEmailVerifiedAt(null),
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public static function publicStatuses(): array
    {
        return [[EventStatus::LIVE->name], [EventStatus::ARCHIVED->name]];
    }

    public static function withheldStatuses(): array
    {
        return [[EventStatus::DRAFT->name], [EventStatus::PENDING_MANUAL_REVIEW->name]];
    }

    #[DataProvider('publicStatuses')]
    public function test_never_withholds_content_for_public_events(string $status): void
    {
        $this->givenEventStatus($status);

        $this->assertFalse($this->policy->shouldWithholdEventContent($this->event(), ['victim@example.com']));
    }

    #[DataProvider('withheldStatuses')]
    public function test_withholds_content_for_unpublished_events_sent_to_outsiders(string $status): void
    {
        $this->givenEventStatus($status);

        $this->assertTrue($this->policy->shouldWithholdEventContent($this->event(), ['victim@example.com']));
    }

    public function test_sends_full_content_to_verified_members_of_the_account(): void
    {
        $this->givenEventStatus(EventStatus::DRAFT->name);

        $this->assertFalse($this->policy->shouldWithholdEventContent($this->event(), ['owner@example.com']));
    }

    public function test_withholds_content_from_unverified_members(): void
    {
        $this->givenEventStatus(EventStatus::DRAFT->name);

        $this->assertTrue($this->policy->shouldWithholdEventContent($this->event(), ['unverified@example.com']));
    }

    public function test_withholds_content_when_any_recipient_is_an_outsider(): void
    {
        $this->givenEventStatus(EventStatus::DRAFT->name);

        $this->assertTrue($this->policy->shouldWithholdEventContent($this->event(), ['owner@example.com', 'victim@example.com']));
    }

    public function test_uses_the_current_status_rather_than_the_status_when_the_mail_was_queued(): void
    {
        $this->givenEventStatus(EventStatus::PENDING_MANUAL_REVIEW->name);

        $event = $this->event()->setStatus(EventStatus::LIVE->name);

        $this->assertTrue($this->policy->shouldWithholdEventContent($event, ['victim@example.com']));
    }

    public function test_falls_back_to_the_queued_status_when_the_event_was_deleted(): void
    {
        $this->eventRepository->shouldReceive('findFirstWhere')->with(['id' => 5])->andReturnNull();

        $liveEvent = $this->event()->setStatus(EventStatus::LIVE->name);

        $this->assertFalse($this->policy->shouldWithholdEventContent($liveEvent, ['buyer@example.com']));
        $this->assertTrue($this->policy->shouldWithholdEventContent($this->event(), ['buyer@example.com']));
    }

    private function givenEventStatus(string $status): void
    {
        $this->eventRepository
            ->shouldReceive('findFirstWhere')
            ->with(['id' => 5])
            ->andReturn((new EventDomainObject)->setId(5)->setAccountId(9)->setStatus($status));
    }

    private function event(): EventDomainObject
    {
        return (new EventDomainObject)->setId(5)->setAccountId(9)->setStatus(EventStatus::DRAFT->name);
    }
}
