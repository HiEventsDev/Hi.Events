<?php

declare(strict_types=1);

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
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

class MultiDateSeatedOrderTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private const ZONE = 'z2';

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
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, price: 30.00);
        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'payment_providers' => json_encode([PaymentProviders::OFFLINE->value]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $layout = $this->seatMapFixture('club');
        $layout['areas'][0]['elements'] = collect($layout['areas'][0]['elements'])
            ->filter(fn (array $element) => $element['id'] === self::ZONE)
            ->map(fn (array $element) => [...$element, 'capacity' => 3])
            ->values()
            ->all();
        $this->insertEventSeatMap($layout, ['b_premium' => [$this->productId]]);
    }

    public function test_one_order_can_take_seats_on_several_dates_beyond_a_single_dates_capacity(): void
    {
        $firstDate = $this->insertOccurrence();
        $secondDate = $this->insertOccurrence(daysAhead: 8);

        $order = $this->createOrder([$firstDate => 2, $secondDate => 2]);

        $this->assertSame(4, DB::table('seat_claims')->where('order_id', $order)->count());
    }

    /**
     * @param  array<int, int>  $quantityByDate
     */
    private function createOrder(array $quantityByDate): int
    {
        return app(CreateOrderHandler::class)->handle($this->eventId, new CreateOrderPublicDTO(
            products: collect($quantityByDate)->map(fn (int $quantity, int $occurrenceId) => new ProductOrderDetailsDTO(
                product_id: $this->productId,
                quantities: collect([new OrderProductPriceDTO(
                    quantity: $quantity,
                    price_id: $this->priceId,
                    seat_uids: array_fill(0, $quantity, self::ZONE),
                )]),
                event_occurrence_id: $occurrenceId,
            ))->values(),
            is_user_authenticated: true,
            session_identifier: sha1(uniqid('', true)),
            order_locale: 'en',
            promo_code: null,
        ))->getId();
    }
}
