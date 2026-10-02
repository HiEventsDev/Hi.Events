<?php

namespace Tests\Unit\Services\Domain\BoxOffice;

use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\BoxOffice\Exceptions\CannotSellException;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeActivityValidator;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use Illuminate\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BoxOfficeActivityValidatorTest extends TestCase
{
    private MockInterface|AccountRepositoryInterface $accountRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountRepository = Mockery::mock(AccountRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function validator(bool $saas): BoxOfficeActivityValidator
    {
        return new BoxOfficeActivityValidator(
            accountRepository: $this->accountRepository,
            config: new Repository(['app' => ['saas_mode_enabled' => $saas]]),
        );
    }

    private function event(string $status): EventDomainObject
    {
        return (new EventDomainObject)->setId(1)->setAccountId(9)->setStatus($status);
    }

    public function test_live_event_within_window_passes(): void
    {
        $this->validator(true)->assertActive(new BoxOfficeDomainObject, $this->event(EventStatus::LIVE->name));

        $this->assertTrue(true);
    }

    public function test_expired_box_office_is_rejected(): void
    {
        $this->expectException(CannotSellException::class);

        $this->validator(false)->assertActive(
            (new BoxOfficeDomainObject)->setExpiresAt(now()->subMinute()->toDateTimeString()),
            $this->event(EventStatus::LIVE->name),
        );
    }

    public function test_not_yet_active_box_office_is_rejected(): void
    {
        $this->expectException(CannotSellException::class);

        $this->validator(false)->assertActive(
            (new BoxOfficeDomainObject)->setActivatesAt(now()->addHour()->toDateTimeString()),
            $this->event(EventStatus::LIVE->name),
        );
    }

    public function test_archived_and_pending_review_events_are_rejected(): void
    {
        foreach ([EventStatus::ARCHIVED->name, EventStatus::PENDING_MANUAL_REVIEW->name] as $status) {
            try {
                $this->validator(false)->assertActive(new BoxOfficeDomainObject, $this->event($status));
                $this->fail("Expected rejection for $status");
            } catch (CannotSellException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_draft_event_requires_verified_account_in_saas_mode(): void
    {
        $this->accountRepository->shouldReceive('findById')->with(9)->andReturn(
            (new AccountDomainObject)->setAccountVerifiedAt(null),
        );

        $this->expectException(CannotSellException::class);

        $this->validator(true)->assertActive(new BoxOfficeDomainObject, $this->event(EventStatus::DRAFT->name));
    }

    public function test_draft_event_passes_for_verified_account_and_outside_saas(): void
    {
        $this->accountRepository->shouldReceive('findById')->with(9)->andReturn(
            (new AccountDomainObject)->setAccountVerifiedAt(now()->toDateTimeString()),
        );

        $this->validator(true)->assertActive(new BoxOfficeDomainObject, $this->event(EventStatus::DRAFT->name));
        $this->validator(false)->assertActive(new BoxOfficeDomainObject, $this->event(EventStatus::DRAFT->name));

        $this->assertTrue(true);
    }
}
