<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimLiveness;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class SeatClaimLivenessTotalityTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $occurrenceId;

    private SeatClaimRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->repository = app(SeatClaimRepositoryInterface::class);
    }

    #[DataProvider('orderShapes')]
    public function test_every_order_shape_leaves_its_claim_either_live_or_dead(
        string $status,
        ?string $reservedUntil,
        bool $softDeleted,
        bool $heldBack,
    ): void {
        $seatUid = 'e1.0.0';
        $orderId = $this->insertOrder($status, $reservedUntil === null ? null : now()->modify($reservedUntil));

        if ($softDeleted) {
            DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);
        }

        $this->insertSeatClaim($orderId, $seatUid, $heldBack);

        $isLive = $this->repository->findLiveForOccurrence($this->occurrenceId)->contains('seat_uid', $seatUid);

        $this->repository->deleteDeadClaimsForSeats([$this->occurrenceId], [$seatUid]);
        $isDead = DB::table('seat_claims')->where('seat_uid', $seatUid)->doesntExist();

        $this->assertNotSame(
            $isLive,
            $isDead,
            sprintf(
                'A %s order (reserved_until: %s, soft deleted: %s, held back: %s) left its seat claim %s.',
                $status,
                $reservedUntil ?? 'null',
                $softDeleted ? 'yes' : 'no',
                $heldBack ? 'yes' : 'no',
                $isLive ? 'both live and dead' : 'neither live nor dead',
            ),
        );
    }

    #[DataProvider('orderShapes')]
    public function test_a_non_utc_session_time_zone_does_not_change_which_claims_are_live(
        string $status,
        ?string $reservedUntil,
        bool $softDeleted,
        bool $heldBack,
    ): void {
        DB::statement("SET LOCAL TIME ZONE 'Europe/Berlin'");

        $seatUid = 'e1.0.0';
        $orderId = $this->insertOrder($status, $reservedUntil === null ? null : now()->modify($reservedUntil));

        if ($softDeleted) {
            DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);
        }

        $this->insertSeatClaim($orderId, $seatUid, $heldBack);

        $orderIsLive = $this->orderIsLive($status, $reservedUntil, $softDeleted);

        $this->assertSame($orderIsLive || $heldBack, $this->repository->findLiveForOccurrence($this->occurrenceId)->contains('seat_uid', $seatUid));

        $this->repository->deleteDeadClaimsForSeats([$this->occurrenceId], [$seatUid]);

        $this->assertSame($orderIsLive || $heldBack, DB::table('seat_claims')->where('seat_uid', $seatUid)->exists());
    }

    #[DataProvider('orderShapes')]
    public function test_a_held_back_seat_is_blocked_whenever_its_sale_is_not_live(
        string $status,
        ?string $reservedUntil,
        bool $softDeleted,
        bool $heldBack,
    ): void {
        $seatUid = 'e1.0.0';
        $orderId = $this->insertOrder($status, $reservedUntil === null ? null : now()->modify($reservedUntil));

        if ($softDeleted) {
            DB::table('orders')->where('id', $orderId)->update(['deleted_at' => now()]);
        }

        $this->insertSeatClaim($orderId, $seatUid, $heldBack);

        $orderIsLive = $this->orderIsLive($status, $reservedUntil, $softDeleted);
        $expectedStatus = match (true) {
            $orderIsLive && $status === OrderStatus::RESERVED->name => SeatClaimStatus::HELD->name,
            $orderIsLive => SeatClaimStatus::SOLD->name,
            $heldBack => SeatClaimStatus::BLOCKED->name,
            default => null,
        };

        $this->assertSame($expectedStatus, $this->repository->findLiveWithHoldersForOccurrence($this->occurrenceId)->firstWhere('seat_uid', $seatUid)?->status);
        $this->assertSame($expectedStatus, DB::selectOne(
            'SELECT status FROM ('.$this->repository->liveClaimsWithStatusSql().') AS claims WHERE event_occurrence_id = ? AND seat_uid = ?',
            [$this->occurrenceId, $seatUid],
        )?->status);

        $this->assertSame($heldBack && ! $orderIsLive, $this->repository->deleteBlocks([$this->occurrenceId], [$seatUid])->isNotEmpty());
    }

    public static function orderShapes(): iterable
    {
        foreach (OrderStatus::cases() as $status) {
            foreach (['+30 minutes', '-30 minutes', null] as $reservedUntil) {
                foreach ([false, true] as $softDeleted) {
                    foreach ([false, true] as $heldBack) {
                        $label = sprintf(
                            '%s / %s / %s / %s',
                            $status->name,
                            $reservedUntil ?? 'null',
                            $softDeleted ? 'deleted' : 'present',
                            $heldBack ? 'held back' : 'not held back',
                        );

                        yield $label => [$status->name, $reservedUntil, $softDeleted, $heldBack];
                    }
                }
            }
        }
    }

    private function orderIsLive(string $status, ?string $reservedUntil, bool $softDeleted): bool
    {
        $orderStatus = OrderStatus::fromName($status);

        return ! $softDeleted && (
            in_array($orderStatus, SeatClaimLiveness::ORDER_STATUSES_ALWAYS_LIVE, true)
            || (in_array($orderStatus, SeatClaimLiveness::ORDER_STATUSES_LIVE_UNTIL_EXPIRY, true) && $reservedUntil === '+30 minutes')
        );
    }

    public function test_a_block_is_live_until_it_is_explicitly_released(): void
    {
        $seatUid = 'e1.0.1';
        $this->insertSeatClaim(null, $seatUid, heldBack: true);

        $this->repository->deleteDeadClaimsForSeats([$this->occurrenceId], [$seatUid]);

        $this->assertTrue($this->repository->findLiveForOccurrence($this->occurrenceId)->contains('seat_uid', $seatUid));

        $this->repository->deleteBlocks([$this->occurrenceId], [$seatUid]);

        $this->assertFalse($this->repository->findLiveForOccurrence($this->occurrenceId)->contains('seat_uid', $seatUid));
    }

    private function insertSeatClaim(?int $orderId, string $seatUid, bool $heldBack = false): void
    {
        DB::table('seat_claims')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => $seatUid,
            'is_zone' => false,
            'band_key' => 'b_premium',
            'seat_label' => 'A-1',
            'order_id' => $orderId,
            'held_back' => $heldBack,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
