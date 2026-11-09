<?php

namespace Tests\Unit\Services\Application\Handlers\CheckInList\Public;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Exceptions\CannotCheckInException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\CheckInListRepositoryInterface;
use HiEvents\Services\Application\Handlers\CheckInList\Public\DTO\LookupKioskTicketsPublicDTO;
use HiEvents\Services\Application\Handlers\CheckInList\Public\LookupKioskTicketsPublicHandler;
use HiEvents\Services\Domain\CheckInList\CheckInListActivityValidator;
use Mockery as m;
use Tests\TestCase;

class LookupKioskTicketsPublicHandlerTest extends TestCase
{
    private CheckInListRepositoryInterface $checkInListRepository;

    private AttendeeRepositoryInterface $attendeeRepository;

    private LookupKioskTicketsPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checkInListRepository = m::mock(CheckInListRepositoryInterface::class);
        $this->checkInListRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->attendeeRepository = m::mock(AttendeeRepositoryInterface::class);

        $this->handler = new LookupKioskTicketsPublicHandler(
            $this->checkInListRepository,
            $this->attendeeRepository,
            new CheckInListActivityValidator,
        );
    }

    public function test_matches_are_scoped_to_the_lists_tickets_and_date(): void
    {
        $list = (new CheckInListDomainObject)
            ->setEventId(7)
            ->setEventOccurrenceId(42)
            ->setKioskMode(true)
            ->setKioskLookupEnabled(true)
            ->setProducts(collect([(new ProductDomainObject)->setId(3), (new ProductDomainObject)->setId(5)]));
        $this->checkInListRepository->shouldReceive('findFirstWhere')->once()->andReturn($list);

        $this->attendeeRepository
            ->shouldReceive('findKioskTicketMatches')
            ->once()
            ->with(7, 'ada@example.test', 'Lovelace', [3, 5], 42)
            ->andReturn(collect());

        $this->assertCount(0, $this->handler->handle($this->dto()));
    }

    public function test_staffed_lists_do_not_offer_a_lookup(): void
    {
        $this->checkInListRepository->shouldReceive('findFirstWhere')->once()->andReturn(
            (new CheckInListDomainObject)->setEventId(7)->setKioskMode(false)->setKioskLookupEnabled(true),
        );
        $this->attendeeRepository->shouldNotReceive('findKioskTicketMatches');

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle($this->dto());
    }

    public function test_kiosks_with_the_lookup_switched_off_do_not_offer_it(): void
    {
        $this->checkInListRepository->shouldReceive('findFirstWhere')->once()->andReturn(
            (new CheckInListDomainObject)->setEventId(7)->setKioskMode(true)->setKioskLookupEnabled(false),
        );
        $this->attendeeRepository->shouldNotReceive('findKioskTicketMatches');

        $this->expectException(ResourceNotFoundException::class);
        $this->handler->handle($this->dto());
    }

    public function test_expired_kiosks_refuse_lookups(): void
    {
        $this->checkInListRepository->shouldReceive('findFirstWhere')->once()->andReturn(
            (new CheckInListDomainObject)
                ->setEventId(7)
                ->setKioskMode(true)
                ->setKioskLookupEnabled(true)
                ->setExpiresAt(now()->subDay()->toDateTimeString()),
        );
        $this->attendeeRepository->shouldNotReceive('findKioskTicketMatches');

        $this->expectException(CannotCheckInException::class);
        $this->handler->handle($this->dto());
    }

    private function dto(): LookupKioskTicketsPublicDTO
    {
        return new LookupKioskTicketsPublicDTO(
            checkInListShortId: 'cil_kiosk',
            email: 'ada@example.test',
            lastName: 'Lovelace',
        );
    }
}
