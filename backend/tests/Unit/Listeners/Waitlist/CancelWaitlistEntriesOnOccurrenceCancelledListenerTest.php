<?php

namespace Tests\Unit\Listeners\Waitlist;

use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Listeners\Waitlist\CancelWaitlistEntriesOnOccurrenceCancelledListener;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Waitlist\CancelWaitlistEntryService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelWaitlistEntriesOnOccurrenceCancelledListenerTest extends TestCase
{
    private WaitlistEntryRepositoryInterface|MockInterface $waitlistEntryRepository;

    private CancelWaitlistEntryService|MockInterface $cancelWaitlistEntryService;

    private CancelWaitlistEntriesOnOccurrenceCancelledListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->cancelWaitlistEntryService = Mockery::mock(CancelWaitlistEntryService::class);
        $this->listener = new CancelWaitlistEntriesOnOccurrenceCancelledListener(
            $this->waitlistEntryRepository,
            $this->cancelWaitlistEntryService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_bulk_cancels_waiting_entries_only(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(static function (array $attrs): bool {
                    return ($attrs['status'] ?? null) === WaitlistEntryStatus::CANCELLED->name
                        && array_key_exists('cancelled_at', $attrs);
                }),
                [
                    'event_id' => $eventId,
                    'event_occurrence_id' => $occurrenceId,
                    'status' => WaitlistEntryStatus::WAITING->name,
                ],
            );

        $this->waitlistEntryRepository
            ->shouldReceive('findWhere')
            ->once()
            ->with([
                'event_id' => $eventId,
                'event_occurrence_id' => $occurrenceId,
                'status' => WaitlistEntryStatus::OFFERED->name,
            ])
            ->andReturn(collect());

        $this->cancelWaitlistEntryService->shouldNotReceive('cancelEntry');

        $this->listener->handle(new OccurrenceCancelledEvent(
            eventId: $eventId,
            occurrenceId: $occurrenceId,
            refundOrders: false,
        ));

        $this->assertTrue(true);
    }

    public function test_offered_entries_are_cancelled_through_the_entry_service(): void
    {
        $eventId = 1;
        $occurrenceId = 10;

        $offeredEntry1 = Mockery::mock(WaitlistEntryDomainObject::class);
        $offeredEntry2 = Mockery::mock(WaitlistEntryDomainObject::class);

        $this->waitlistEntryRepository->shouldReceive('updateWhere')->once();

        $this->waitlistEntryRepository
            ->shouldReceive('findWhere')
            ->once()
            ->andReturn(collect([$offeredEntry1, $offeredEntry2]));

        $this->cancelWaitlistEntryService
            ->shouldReceive('cancelEntry')
            ->once()
            ->with($offeredEntry1)
            ->andReturn($offeredEntry1);
        $this->cancelWaitlistEntryService
            ->shouldReceive('cancelEntry')
            ->once()
            ->with($offeredEntry2)
            ->andReturn($offeredEntry2);

        $this->listener->handle(new OccurrenceCancelledEvent(
            eventId: $eventId,
            occurrenceId: $occurrenceId,
            refundOrders: true,
        ));

        $this->assertTrue(true);
    }
}
