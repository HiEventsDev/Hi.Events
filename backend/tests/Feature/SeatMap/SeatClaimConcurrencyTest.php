<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatClaimConcurrencyTest extends TestCase
{
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const LOCK_NOT_AVAILABLE = '55P03';

    private const SEAT = 'e2.0.0';

    private const ZONE = 'z2';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private Connection $other;

    protected function setUp(): void
    {
        parent::setUp();

        AccountConfiguration::firstOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'Default',
            'is_system_default' => true,
            'application_fees' => ['percentage' => 1.5, 'fixed' => 0],
        ]);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct(priceType: 'FREE');
        $this->priceId = $this->insertPrice($this->productId, null);

        config(['database.connections.pgsql_other' => config('database.connections.'.config('database.default'))]);
        $this->other = DB::connection('pgsql_other');
        $this->other->statement("SET lock_timeout = '300ms'");
    }

    protected function tearDown(): void
    {
        if ($this->other->transactionLevel() > 0) {
            $this->other->rollBack();
        }
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::purge('pgsql_other');

        DB::table('seat_claims')->where('event_id', $this->eventId)->delete();
        DB::table('attendees')->where('event_id', $this->eventId)->delete();
        DB::table('order_items')->whereIn('order_id', DB::table('orders')->where('event_id', $this->eventId)->select('id'))->delete();
        DB::table('orders')->where('event_id', $this->eventId)->delete();
        DB::table('event_seat_map_band_products')->whereIn(
            'event_seat_map_id',
            DB::table('event_seat_maps')->where('event_id', $this->eventId)->select('id'),
        )->delete();
        $this->deleteEventSeatMapOf($this->eventId);
        DB::table('product_prices')->where('product_id', $this->productId)->delete();
        DB::table('products')->where('event_id', $this->eventId)->delete();
        DB::table('event_settings')->where('event_id', $this->eventId)->delete();
        DB::table('event_occurrences')->where('event_id', $this->eventId)->delete();
        DB::table('events')->where('id', $this->eventId)->delete();
        DB::table('organizers')->where('id', $this->organizerId)->delete();
        DB::table('account_users')->where('account_id', $this->accountId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        DB::table('accounts')->where('id', $this->accountId)->delete();

        parent::tearDown();
    }

    public function test_the_event_advisory_lock_serialises_concurrent_claimers(): void
    {
        DB::beginTransaction();
        DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, $this->eventId]);

        $this->other->beginTransaction();
        try {
            $this->other->statement('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, $this->eventId]);
            $this->fail('The second connection must wait for the event lock');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }
        $this->other->rollBack();

        DB::commit();

        $this->other->beginTransaction();
        $this->other->statement('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, $this->eventId]);
        $this->other->commit();
    }

    public function test_the_unique_index_blocks_a_concurrent_insert_and_reports_the_lost_seat(): void
    {
        $winner = $this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15));
        $loser = $this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15));

        DB::beginTransaction();
        $this->assertSame([self::SEAT], app(SeatClaimRepositoryInterface::class)->insertIgnoringConflicts([$this->row($winner)]));

        $this->other->beginTransaction();
        try {
            $this->insertOnOtherConnection($loser);
            $this->fail('The uncommitted claim must block a second insert for the same seat');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }
        $this->other->rollBack();

        DB::commit();

        $this->other->beginTransaction();
        $this->assertSame([], $this->insertOnOtherConnection($loser));
        $this->other->commit();

        $this->assertSame([$winner], DB::table('seat_claims')->where('seat_uid', self::SEAT)->pluck('order_id')->all());
    }

    public function test_a_box_office_sale_and_the_public_checkout_cannot_both_get_the_same_seat(): void
    {
        $this->attachSeatMap($this->seatMapFixture('theatre'));
        $publicLoser = $this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15));

        DB::beginTransaction();
        $boxOfficeOrderId = $this->claimForBoxOffice([self::SEAT]);

        $this->other->beginTransaction();
        try {
            $this->insertOnOtherConnection($publicLoser);
            $this->fail('An uncommitted box office claim must block a second insert for the same seat');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }
        $this->other->rollBack();

        DB::commit();

        try {
            $this->claimForPublicCheckout([self::SEAT]);
            $this->fail('The public checkout must lose the seat to the box office');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::SEAT], $exception->getSeatUids());
        }

        $this->assertSame(
            [$boxOfficeOrderId],
            DB::table('seat_claims')->where('seat_uid', self::SEAT)->pluck('order_id')->all(),
        );
    }

    public function test_zone_capacity_is_protected_by_the_event_lock_alone(): void
    {
        $layout = $this->seatMapFixture('club');
        $layout['areas'][0]['elements'][1]['capacity'] = 2;
        $this->attachSeatMap($layout);

        DB::beginTransaction();
        $this->claimForBoxOffice([self::ZONE, self::ZONE]);

        $this->other->beginTransaction();
        try {
            $this->other->statement('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, $this->eventId]);
            $this->fail('A second zone claimer must wait for the event lock before counting');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }
        $this->other->rollBack();

        DB::commit();

        try {
            $this->claimForBoxOffice([self::ZONE]);
            $this->fail('Expected the zone to be full');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::ZONE], $exception->getSeatUids());
        }

        $this->assertSame(2, app(SeatClaimRepositoryInterface::class)->countLiveForZone($this->occurrenceId, self::ZONE));
    }

    public function test_a_seat_cannot_end_up_both_sold_and_blocked(): void
    {
        $this->attachSeatMap($this->seatMapFixture('theatre'));

        DB::beginTransaction();
        $buyerOrderId = $this->claimForBoxOffice([self::SEAT]);

        $this->other->beginTransaction();
        try {
            $this->blockOnOtherConnection();
            $this->fail('An uncommitted claim must block a concurrent organiser block');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }
        $this->other->rollBack();

        DB::commit();

        $result = DB::transaction(fn () => app(SeatClaimService::class)
            ->block($this->eventId, [$this->occurrenceId], [self::SEAT], 'Sound desk'));
        $this->assertSame(0, $result->blocked);
        $this->assertSame([self::SEAT], $result->skipped[0]->seat_uids);

        $claim = DB::table('seat_claims')->where('seat_uid', self::SEAT)->sole();
        $this->assertSame($buyerOrderId, $claim->order_id);
        $this->assertNull($claim->block_reason);
    }

    public function test_changing_an_attendees_ticket_waits_for_the_event_lock(): void
    {
        $this->attachSeatMap($this->seatMapFixture('theatre'));

        $this->other->beginTransaction();
        $this->other->statement('SELECT pg_advisory_xact_lock(?, ?)', [TransactionLockService::EVENT_LOCK_KEYSPACE, $this->eventId]);

        DB::beginTransaction();
        DB::statement("SET LOCAL lock_timeout = '300ms'");

        try {
            app(SeatClaimService::class)->changeProductForAttendee(
                (new AttendeeDomainObject)->setId(1)->setEventId($this->eventId),
                $this->productId,
                $this->priceId,
            );
            $this->fail('Changing a seated ticket must wait for the event lock');
        } catch (QueryException $exception) {
            $this->assertSame(self::LOCK_NOT_AVAILABLE, $exception->getCode());
        }

        DB::rollBack();
        $this->other->rollBack();
    }

    private function attachSeatMap(array $layout): void
    {
        $this->insertEventSeatMap($layout, ['b_premium' => [$this->productId]]);
    }

    private function claimForBoxOffice(array $seatUids): int
    {
        $orderId = $this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15));

        app(SeatClaimService::class)->claimForOrder(
            (new OrderDomainObject)->setId($orderId)->setEventId($this->eventId),
            collect($seatUids)->map(fn (string $seatUid) => new SeatSelectionDTO(
                seat_uid: $seatUid,
                event_occurrence_id: $this->occurrenceId,
                product_id: $this->productId,
                product_price_id: $this->priceId,
            )),
            allowBlocked: true,
        );

        return $orderId;
    }

    private function claimForPublicCheckout(array $seatUids): void
    {
        app(CreateOrderHandler::class)->handle($this->eventId, new CreateOrderPublicDTO(
            products: collect([new ProductOrderDetailsDTO(
                product_id: $this->productId,
                quantities: collect([new OrderProductPriceDTO(
                    quantity: count($seatUids),
                    price_id: $this->priceId,
                    seat_uids: $seatUids,
                )]),
                event_occurrence_id: $this->occurrenceId,
            )]),
            is_user_authenticated: false,
            session_identifier: sha1(uniqid('', true)),
            order_locale: 'en',
        ));
    }

    private function blockOnOtherConnection(): array
    {
        $row = [
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => self::SEAT,
            'is_zone' => 'false',
            'band_key' => 'b_premium',
            'seat_label' => 'Stalls · A-1',
            'block_reason' => 'Sound desk',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        return $this->other->select(
            'INSERT INTO seat_claims ('.implode(', ', array_keys($row)).') VALUES ('.implode(', ', array_fill(0, count($row), '?')).')'
            .' ON CONFLICT DO NOTHING RETURNING seat_uid',
            array_values($row),
        );
    }

    private function insertOnOtherConnection(int $orderId): array
    {
        $row = $this->row($orderId);

        return $this->other->select(
            'INSERT INTO seat_claims ('.implode(', ', array_keys($row)).') VALUES ('.implode(', ', array_fill(0, count($row), '?')).')'
            .' ON CONFLICT DO NOTHING RETURNING seat_uid',
            array_values($row),
        );
    }

    private function row(int $orderId): array
    {
        return [
            'event_id' => $this->eventId,
            'event_occurrence_id' => $this->occurrenceId,
            'seat_uid' => self::SEAT,
            'is_zone' => 'false',
            'band_key' => 'b_premium',
            'seat_label' => 'Stalls · A-1',
            'order_id' => $orderId,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
