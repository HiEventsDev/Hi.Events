<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductLinkDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductsDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\UpdateEventSeatMapBandProductsHandler;
use HiEvents\Enterprise\Seating\Services\Domain\SeatSelectionValidationService;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Order\OrderItemProcessingService;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class BandPricingTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const PREMIUM_SEAT = 'e2.0.0';

    private const STANDARD_SEAT = 'e3.0.0';

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, price: 30.00);
    }

    public function test_seats_in_two_bands_become_two_order_items_priced_by_band(): void
    {
        $this->linkBands(['b_premium' => 1500, 'b_standard' => 0]);

        $this->placeOrder([
            ['band_key' => 'b_premium', 'seat_uids' => [self::PREMIUM_SEAT]],
            ['band_key' => 'b_standard', 'seat_uids' => [self::STANDARD_SEAT]],
        ]);

        $items = DB::table('order_items')->orderBy('band_key')->get();

        $this->assertCount(2, $items, 'One row per band, both under the same product price');
        $this->assertSame(['b_premium', 'b_standard'], $items->pluck('band_key')->all());
        $this->assertSame([45.00, 30.00], $items->pluck('price')->map(fn ($p) => (float) $p)->all());
        $this->assertSame([$this->priceId, $this->priceId], $items->pluck('product_price_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_the_band_is_derived_from_the_seats_so_a_client_cannot_dodge_the_adjustment(): void
    {
        $this->linkBands(['b_premium' => 1500]);

        $this->placeOrder([['band_key' => null, 'seat_uids' => [self::PREMIUM_SEAT]]]);

        $item = DB::table('order_items')->first();

        $this->assertSame(45.00, (float) $item->price, 'The omitted band must not skip the adjustment');
        $this->assertSame('b_premium', $item->band_key);
    }

    public function test_a_client_naming_the_wrong_band_is_still_charged_for_the_seats_it_sent(): void
    {
        $this->linkBands(['b_premium' => 1500, 'b_standard' => 0]);

        $this->placeOrder([['band_key' => 'b_standard', 'seat_uids' => [self::PREMIUM_SEAT]]]);

        $this->assertSame(45.00, (float) DB::table('order_items')->value('price'));
    }

    public function test_a_negative_adjustment_never_charges_below_zero(): void
    {
        $this->linkBands(['b_premium' => -9000]);

        $this->placeOrder([['band_key' => 'b_premium', 'seat_uids' => [self::PREMIUM_SEAT]]]);

        $this->assertSame(0.0, (float) DB::table('order_items')->value('price'));
    }

    public function test_an_unbanded_product_is_priced_exactly_as_before(): void
    {
        $this->placeOrder([['band_key' => null, 'seat_uids' => []]]);

        $item = DB::table('order_items')->first();

        $this->assertSame(30.00, (float) $item->price);
        $this->assertNull($item->band_key);
    }

    public function test_a_line_whose_seats_span_two_bands_is_rejected(): void
    {
        $this->linkBands(['b_premium' => 1500, 'b_standard' => 0]);

        $this->expectException(SeatSelectionInvalidException::class);
        $this->expectExceptionMessage('Seats from different price bands cannot share one ticket line');

        $this->validateSelection([['band_key' => null, 'seat_uids' => [self::PREMIUM_SEAT, self::STANDARD_SEAT]]]);
    }

    public function test_a_line_claiming_the_wrong_band_is_rejected(): void
    {
        $this->linkBands(['b_premium' => 1500, 'b_standard' => 0]);

        $this->expectException(SeatSelectionInvalidException::class);
        $this->expectExceptionMessage('The selected seats do not belong to that price band');

        $this->validateSelection([['band_key' => 'b_standard', 'seat_uids' => [self::PREMIUM_SEAT]]]);
    }

    public function test_a_free_ticket_cannot_be_given_a_band_adjustment(): void
    {
        $freeProductId = $this->insertProduct(priceType: ProductPriceType::FREE->name);
        $this->insertPrice($freeProductId, null, price: 0.00);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'));

        $this->expectException(SeatMapChangeConflictException::class);
        $this->expectExceptionMessage('Free tickets cannot have a price band adjustment');

        app(UpdateEventSeatMapBandProductsHandler::class)->handle($this->eventId, collect([
            new BandProductsDTO(
                band_key: 'b_premium',
                products: collect([new BandProductLinkDTO(product_id: $freeProductId, price_adjustment: 1500)]),
            ),
        ]));
    }

    /**
     * @param  array<string, int>  $adjustmentsByBand
     */
    private function linkBands(array $adjustmentsByBand): void
    {
        $this->insertEventSeatMap($this->seatMapFixture('theatre'));

        app(UpdateEventSeatMapBandProductsHandler::class)->handle($this->eventId, collect(
            array_map(fn (string $bandKey, int $adjustment) => new BandProductsDTO(
                band_key: $bandKey,
                products: collect([new BandProductLinkDTO(product_id: $this->productId, price_adjustment: $adjustment)]),
            ), array_keys($adjustmentsByBand), $adjustmentsByBand)
        ));
    }

    /**
     * @param  array<int, array{band_key: ?string, seat_uids: string[]}>  $lines
     */
    private function placeOrder(array $lines): void
    {
        $order = (new OrderDomainObject)
            ->setId($this->insertOrder(OrderStatus::RESERVED->name, now()->addMinutes(15)))
            ->setEventId($this->eventId);

        app(OrderItemProcessingService::class)->process(
            order: $order,
            productsOrderDetails: collect([new ProductOrderDetailsDTO(
                product_id: $this->productId,
                quantities: collect(array_map(fn (array $line) => new OrderProductPriceDTO(
                    quantity: max(1, count($line['seat_uids'])),
                    price_id: $this->priceId,
                    seat_uids: $line['seat_uids'],
                    band_key: $line['band_key'],
                ), $lines)),
                event_occurrence_id: $this->occurrenceId,
            )]),
            event: (new EventDomainObject)->setId($this->eventId)->setCurrency('USD'),
            promoCode: null,
        );
    }

    /**
     * @param  array<int, array{band_key: ?string, seat_uids: string[]}>  $lines
     *
     * @throws SeatSelectionInvalidException
     */
    private function validateSelection(array $lines): void
    {
        app(SeatSelectionValidationService::class)->validate($this->eventId, [[
            'product_id' => $this->productId,
            'event_occurrence_id' => $this->occurrenceId,
            'quantities' => array_map(fn (array $line) => [
                'quantity' => count($line['seat_uids']),
                'seat_uids' => $line['seat_uids'],
                'band_key' => $line['band_key'],
            ], $lines),
        ]]);
    }
}
