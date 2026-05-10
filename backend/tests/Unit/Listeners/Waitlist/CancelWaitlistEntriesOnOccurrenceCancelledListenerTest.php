<?php

namespace Tests\Unit\Listeners\Waitlist;

use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Events\OccurrenceCancelledEvent;
use HiEvents\Listeners\Waitlist\CancelWaitlistEntriesOnOccurrenceCancelledListener;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CancelWaitlistEntriesOnOccurrenceCancelledListenerTest extends TestCase
{
    private WaitlistEntryRepositoryInterface|MockInterface $waitlistEntryRepository;

    private CancelWaitlistEntriesOnOccurrenceCancelledListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlistEntryRepository = Mockery::mock(WaitlistEntryRepositoryInterface::class);
        $this->listener = new CancelWaitlistEntriesOnOccurrenceCancelledListener(
            $this->waitlistEntryRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cancels_waiting_and_offered_entries_for_occurrence(): void
    {
        // The listener bulk-updates by event + occurrence + status. Without
        // this, capacity released by attendee cancellation would let those
        // entries pass the per-entry capacity check and fire offer emails for
        // an occurrence that no longer exists.
        $eventId = 1;
        $occurrenceId = 10;

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->with(
                Mockery::on(static function (array $attrs): bool {
                    // Must mark CANCELLED *and* stamp cancelled_at. Without the
                    // timestamp, bulk-cancelled entries are indistinguishable
                    // from the historical-data cohort and break audit queries.
                    return ($attrs['status'] ?? null) === WaitlistEntryStatus::CANCELLED->name
                        && array_key_exists('cancelled_at', $attrs);
                }),
                Mockery::on(static function (array $where) use ($eventId, $occurrenceId): bool {
                    // event_id, event_occurrence_id, status-in-clause must all be present.
                    if (($where['event_id'] ?? null) !== $eventId) {
                        return false;
                    }
                    if (($where['event_occurrence_id'] ?? null) !== $occurrenceId) {
                        return false;
                    }
                    foreach ($where as $clause) {
                        if (is_array($clause) && $clause[0] === 'status' && $clause[1] === 'in') {
                            return $clause[2] === [
                                WaitlistEntryStatus::WAITING->name,
                                WaitlistEntryStatus::OFFERED->name,
                            ];
                        }
                    }

                    return false;
                }),
            );

        $this->listener->handle(new OccurrenceCancelledEvent(
            eventId: $eventId,
            occurrenceId: $occurrenceId,
            refundOrders: false,
        ));

        $this->assertTrue(true);
    }

    public function test_only_targets_waiting_and_offered_status(): void
    {
        // PURCHASED, OFFER_EXPIRED, and already-CANCELLED entries should not
        // be touched — flipping a PURCHASED entry to CANCELLED would lose
        // sale-attribution data.
        $captured = null;

        $this->waitlistEntryRepository
            ->shouldReceive('updateWhere')
            ->once()
            ->andReturnUsing(function (array $attrs, array $where) use (&$captured): int {
                $captured = $where;

                return 0;
            });

        $this->listener->handle(new OccurrenceCancelledEvent(
            eventId: 1,
            occurrenceId: 10,
            refundOrders: true,
        ));

        $statusClause = null;
        foreach ($captured as $clause) {
            if (is_array($clause) && $clause[0] === 'status') {
                $statusClause = $clause;
                break;
            }
        }

        $this->assertNotNull($statusClause);
        $this->assertSame('in', $statusClause[1]);
        $this->assertEqualsCanonicalizing(
            [WaitlistEntryStatus::WAITING->name, WaitlistEntryStatus::OFFERED->name],
            $statusClause[2],
        );
    }
}
