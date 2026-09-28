<?php

namespace Tests\Unit\Services\Domain\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CashlessWalletResolveServiceTest extends TestCase
{
    private AttendeeRepositoryInterface|MockInterface $attendeeRepository;

    private CashlessWalletRepositoryInterface|MockInterface $walletRepository;

    private CashlessWalletResolveService $service;

    private EventRepositoryInterface|MockInterface $eventRepository;

    private CashlessSettingsService|MockInterface $settingsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->attendeeRepository = Mockery::mock(AttendeeRepositoryInterface::class);
        $this->walletRepository = Mockery::mock(CashlessWalletRepositoryInterface::class);

        $this->eventRepository = Mockery::mock(EventRepositoryInterface::class);
        $this->settingsService = Mockery::mock(CashlessSettingsService::class);

        $this->service = new CashlessWalletResolveService(
            $this->attendeeRepository,
            $this->walletRepository,
            $this->eventRepository,
            $this->settingsService,
        );
    }

    public function test_a_public_id_reference_is_matched_case_insensitively_on_public_id(): void
    {
        $this->expectAttendeeLookup(AttendeeDomainObjectAbstract::PUBLIC_ID, 'A-QKS3J8R', AttendeeStatus::ACTIVE);
        $this->walletRepository->shouldReceive('findFirstWhere')->andReturn(new CashlessWalletDomainObject);

        $wallet = $this->service->resolveByTicketReference(5, ' a-qks3j8r ');

        $this->assertNotNull($wallet->getAttendee());
    }

    public function test_a_short_id_reference_is_matched_on_short_id(): void
    {
        $this->expectAttendeeLookup(AttendeeDomainObjectAbstract::SHORT_ID, 'a_abc123', AttendeeStatus::ACTIVE);
        $this->walletRepository->shouldReceive('findFirstWhere')->andReturn(new CashlessWalletDomainObject);

        $this->assertNotNull($this->service->resolveByTicketReference(5, 'a_abc123')->getAttendee());
    }

    public function test_an_unknown_ticket_is_unavailable(): void
    {
        $this->attendeeRepository->shouldReceive('findFirstWhere')->andReturn(null);

        $this->expectException(CashlessWalletUnavailableException::class);

        $this->service->resolveByTicketReference(5, 'A-NOPE123');
    }

    public function test_a_cancelled_ticket_is_unavailable(): void
    {
        $this->expectAttendeeLookup(AttendeeDomainObjectAbstract::PUBLIC_ID, 'A-QKS3J8R', AttendeeStatus::CANCELLED);

        $this->expectException(CashlessWalletUnavailableException::class);

        $this->service->resolveByTicketReference(5, 'A-QKS3J8R');
    }

    public function test_a_new_wallet_is_active_while_cashless_is_open(): void
    {
        $this->expectNewWalletWithSettings(new EventSettingDomainObject);

        $this->assertSame(CashlessWalletStatus::ACTIVE->value, $this->createdWalletStatus());
    }

    public function test_a_new_wallet_is_closed_once_cashless_has_been_closed(): void
    {
        $this->expectNewWalletWithSettings((new EventSettingDomainObject)->setCashlessClosedAt('2026-09-28 10:00:00'));

        $this->assertSame(CashlessWalletStatus::CLOSED->value, $this->createdWalletStatus());
    }

    private array $createdWallet = [];

    private function createdWalletStatus(): string
    {
        $this->service->resolveByAttendeePublicId(5, 'A-QKS3J8R');

        return $this->createdWallet[CashlessWalletDomainObjectAbstract::STATUS];
    }

    private function expectNewWalletWithSettings(EventSettingDomainObject $settings): void
    {
        $this->expectAttendeeLookup(AttendeeDomainObjectAbstract::PUBLIC_ID, 'A-QKS3J8R', AttendeeStatus::ACTIVE);
        $this->walletRepository->shouldReceive('findFirstWhere')->andReturn(null);
        $this->eventRepository->shouldReceive('findById')->andReturn((new EventDomainObject)->setCurrency('EUR'));
        $this->settingsService->shouldReceive('getSettings')->with(5)->andReturn($settings);
        $this->walletRepository->shouldReceive('create')->andReturnUsing(function (array $attributes) {
            $this->createdWallet = $attributes;

            return new CashlessWalletDomainObject;
        });
    }

    private function expectAttendeeLookup(string $field, string $value, AttendeeStatus $status): void
    {
        $attendee = (new AttendeeDomainObject)->setId(9)->setEventId(5)->setStatus($status->name);

        $this->attendeeRepository
            ->shouldReceive('findFirstWhere')
            ->with([
                $field => $value,
                AttendeeDomainObjectAbstract::EVENT_ID => 5,
            ])
            ->once()
            ->andReturn($attendee);
    }
}
