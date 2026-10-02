<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatsUnavailableException;
use HiEvents\Enterprise\Seating\Services\Domain\DTO\SeatSelectionDTO;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatClaimLayoutFreshnessTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const SEAT = 'e2.0.0';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $eventSeatMapId;

    private array $layout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);
        $this->layout = $this->seatMapFixture('theatre');
        $this->eventSeatMapId = $this->insertEventSeatMap($this->layout, ['b_premium' => [$this->productId]]);

        app(EventSeatMapLookupService::class)->indexFor($this->eventId);
    }

    public function test_a_seat_removed_after_validation_is_refused_at_claim_time(): void
    {
        $this->changeSeatBehindTheCache(fn (array $seats) => array_values(array_filter(
            $seats,
            fn (array $seat) => $seat['uid'] !== self::SEAT,
        )));

        try {
            $this->claim(self::SEAT);
            $this->fail('Expected the removed seat to be refused');
        } catch (SeatsUnavailableException $exception) {
            $this->assertSame([self::SEAT], $exception->getSeatUids());
        }

        $this->assertSame(0, DB::table('seat_claims')->where('event_id', $this->eventId)->count());
    }

    public function test_a_seat_renamed_after_validation_is_claimed_with_its_new_label(): void
    {
        $this->changeSeatBehindTheCache(fn (array $seats) => array_map(
            fn (array $seat) => $seat['uid'] === self::SEAT ? [...$seat, 'label' => 'Z-99'] : $seat,
            $seats,
        ));

        $this->claim(self::SEAT);

        $this->assertDatabaseHas('seat_claims', ['seat_uid' => self::SEAT, 'seat_label' => 'Stalls · Z-99']);
    }

    private function changeSeatBehindTheCache(callable $change): void
    {
        $layout = $this->layout;
        foreach ($layout['areas'][0]['elements'] as $position => $element) {
            if ($element['id'] === 'e2') {
                $layout['areas'][0]['elements'][$position]['seats'] = $change($element['seats']);
            }
        }

        DB::table('event_seat_maps')->where('id', $this->eventSeatMapId)->update(['layout' => json_encode($layout)]);
    }

    private function claim(string $seatUid): void
    {
        $order = (new OrderDomainObject)
            ->setId($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)))
            ->setEventId($this->eventId);

        DB::transaction(fn () => app(SeatClaimService::class)->claimForOrder($order, collect([new SeatSelectionDTO(
            seat_uid: $seatUid,
            event_occurrence_id: $this->occurrenceId,
            product_id: $this->productId,
            product_price_id: $this->priceId,
        )])));
    }
}
