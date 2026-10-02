<?php

declare(strict_types=1);

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderCompletionService;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\CompleteOrderHandler;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderOrderDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CompleteOrderProductDataDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\TransitionOrderToOfflinePaymentPublicDTO;
use HiEvents\Services\Application\Handlers\Order\TransitionOrderToOfflinePaymentHandler;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class SeatOrderItemBindingTest extends TestCase
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

        Queue::fake();
        Mail::fake();
        $this->app->instance(DomainEventDispatcherService::class, Mockery::mock(DomainEventDispatcherService::class)->shouldIgnoreMissing());

        $session = Mockery::mock(CheckoutSessionManagementService::class);
        $session->shouldReceive('verifySession')->andReturnTrue();
        $this->app->instance(CheckoutSessionManagementService::class, $session);

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, price: 30.00);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'payment_providers' => json_encode([PaymentProviders::OFFLINE->value]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertEventSeatMap($this->seatMapFixture('theatre'), [
            'b_premium' => [$this->productId],
            'b_standard' => [$this->productId],
        ]);
    }

    public function test_seats_in_two_bands_on_one_ticket_type_land_on_the_attendee_that_named_them(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT], [self::STANDARD_SEAT]]);

        $this->completeOrder($order, [self::STANDARD_SEAT, self::PREMIUM_SEAT]);

        $this->assertEqualsCanonicalizing(
            [self::PREMIUM_SEAT, self::STANDARD_SEAT],
            DB::table('attendees')->where('order_id', $order->getId())->pluck('seat_uid')->all(),
        );
        $this->assertClaimsSitOnTheItemOfTheirBand($order->getId());
        $this->assertEveryClaimIsPairedWithTheAttendeeOnItsSeat($order->getId());
    }

    public function test_seats_in_two_bands_are_shared_out_when_the_attendees_name_no_seat(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT], [self::STANDARD_SEAT]]);

        $this->completeOrder($order, [null, null]);

        $this->assertEqualsCanonicalizing(
            [self::PREMIUM_SEAT, self::STANDARD_SEAT],
            DB::table('attendees')->where('order_id', $order->getId())->pluck('seat_uid')->all(),
        );
        $this->assertEveryClaimIsPairedWithTheAttendeeOnItsSeat($order->getId());
    }

    public function test_a_line_split_by_an_order_level_promo_binds_each_seat_to_exactly_one_item(): void
    {
        DB::table('promo_codes')->insert([
            'code' => 'tenoff',
            'discount' => 10.00,
            'discount_type' => 'FIXED',
            'discount_applies_to' => 'ORDER',
            'event_id' => $this->eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seats = ['e2.0.0', 'e2.0.1', 'e2.0.2'];

        $order = $this->createOrder([$seats], promoCode: 'tenoff');

        $items = DB::table('order_items')->where('order_id', $order->getId())->get();
        $this->assertCount(2, $items, 'The discount cannot divide evenly, so the line is split');
        foreach ($items as $item) {
            $this->assertSame(
                (int) $item->quantity,
                DB::table('seat_claims')->where('order_item_id', $item->id)->count(),
            );
        }
        $this->assertSame(0, DB::table('seat_claims')->where('order_id', $order->getId())->whereNull('order_item_id')->count());

        $this->completeOrder($order, [null, null, null]);

        $this->assertEqualsCanonicalizing($seats, DB::table('attendees')->where('order_id', $order->getId())->pluck('seat_uid')->all());
    }

    public function test_completion_is_refused_when_a_claim_is_left_without_an_attendee(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT]]);
        $claim = (array) DB::table('seat_claims')->where('order_id', $order->getId())->first();
        unset($claim['id']);
        DB::table('seat_claims')->insert(array_merge($claim, ['seat_uid' => 'e2.0.5', 'seat_label' => 'A-6']));

        $this->expectException(ResourceConflictException::class);

        $this->completeOrder($order, [self::PREMIUM_SEAT]);
    }

    public function test_checkout_completion_is_refused_once_a_seat_is_no_longer_held(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT]]);
        DB::table('seat_claims')->where('order_id', $order->getId())->delete();

        try {
            $this->completeOrder($order, [null]);
            $this->fail('Completing an order whose seat is gone must be refused');
        } catch (ResourceConflictException $exception) {
            $this->assertSame('Your seats are no longer held. Please restart your order.', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('attendees')->where('order_id', $order->getId())->count());
    }

    public function test_checkout_completion_is_refused_once_a_seat_has_moved_to_another_band(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT]]);
        DB::table('seat_claims')->where('order_id', $order->getId())->update(['band_key' => 'b_standard']);

        $this->expectException(ResourceConflictException::class);

        $this->completeOrder($order, [null]);
    }

    public function test_switching_to_offline_payment_is_refused_once_a_seat_is_no_longer_held(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT]]);
        DB::table('seat_claims')->where('order_id', $order->getId())->delete();

        try {
            app(TransitionOrderToOfflinePaymentHandler::class)->handle(new TransitionOrderToOfflinePaymentPublicDTO(
                orderShortId: $order->getShortId(),
            ));
            $this->fail('Switching to offline payment without held seats must be refused');
        } catch (ResourceConflictException) {
        }

        $this->assertSame('RESERVED', DB::table('orders')->where('id', $order->getId())->value('status'));
    }

    public function test_switching_to_offline_payment_keeps_a_held_seat(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT]]);

        app(TransitionOrderToOfflinePaymentHandler::class)->handle(new TransitionOrderToOfflinePaymentPublicDTO(
            orderShortId: $order->getShortId(),
        ));

        $this->assertSame('AWAITING_OFFLINE_PAYMENT', DB::table('orders')->where('id', $order->getId())->value('status'));
    }

    public function test_a_door_sale_is_refused_once_a_seat_is_no_longer_held(): void
    {
        $order = $this->createOrder([[self::PREMIUM_SEAT]]);
        DB::table('seat_claims')->where('order_id', $order->getId())->delete();

        $event = app(EventRepositoryInterface::class)
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($this->eventId);

        try {
            app(BoxOfficeOrderCompletionService::class)->completeOffline($order, $event, BoxOfficeTender::CASH, 100.0, null, null);
            $this->fail('Completing a door sale without held seats must be refused');
        } catch (ResourceConflictException) {
        }

        $this->assertSame('RESERVED', DB::table('orders')->where('id', $order->getId())->value('status'));
    }

    /**
     * @param  array<int, string[]>  $lines
     */
    private function createOrder(array $lines, ?string $promoCode = null): OrderDomainObject
    {
        return app(CreateOrderHandler::class)->handle($this->eventId, new CreateOrderPublicDTO(
            products: collect([new ProductOrderDetailsDTO(
                product_id: $this->productId,
                quantities: collect(array_map(fn (array $seatUids) => new OrderProductPriceDTO(
                    quantity: count($seatUids),
                    price_id: $this->priceId,
                    seat_uids: $seatUids,
                ), $lines)),
                event_occurrence_id: $this->occurrenceId,
            )]),
            is_user_authenticated: true,
            session_identifier: sha1(uniqid('', true)),
            order_locale: 'en',
            promo_code: $promoCode,
        ));
    }

    /**
     * @param  array<int, ?string>  $seatUids
     */
    private function completeOrder(OrderDomainObject $order, array $seatUids): void
    {
        app(CompleteOrderHandler::class)->handle($order->getShortId(), new CompleteOrderDTO(
            order: new CompleteOrderOrderDTO(
                first_name: 'Buyer',
                last_name: 'Seated',
                email: 'buyer@example.test',
                questions: null,
            ),
            products: collect(array_map(fn (?string $seatUid) => new CompleteOrderProductDataDTO(
                product_price_id: $this->priceId,
                first_name: 'Guest',
                last_name: $seatUid ?? 'Unnamed',
                email: 'guest@example.test',
                seat_uid: $seatUid,
            ), $seatUids)),
            event_id: $this->eventId,
        ));
    }

    private function assertClaimsSitOnTheItemOfTheirBand(int $orderId): void
    {
        $claims = DB::table('seat_claims')
            ->join('order_items', 'order_items.id', '=', 'seat_claims.order_item_id')
            ->where('seat_claims.order_id', $orderId)
            ->get(['seat_claims.band_key AS claim_band', 'order_items.band_key AS item_band']);

        $this->assertCount(2, $claims);
        foreach ($claims as $claim) {
            $this->assertSame($claim->claim_band, $claim->item_band);
        }
    }

    private function assertEveryClaimIsPairedWithTheAttendeeOnItsSeat(int $orderId): void
    {
        $claims = DB::table('seat_claims')
            ->join('attendees', 'attendees.id', '=', 'seat_claims.attendee_id')
            ->where('seat_claims.order_id', $orderId)
            ->get(['seat_claims.seat_uid AS claim_seat', 'attendees.seat_uid AS attendee_seat']);

        $this->assertSame(DB::table('seat_claims')->where('order_id', $orderId)->count(), $claims->count());
        foreach ($claims as $claim) {
            $this->assertSame($claim->claim_seat, $claim->attendee_seat);
        }
    }
}
