<?php

namespace Tests\Feature\SeatMap;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeBestAvailableSeatsDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeSeatRequestItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\GetBoxOfficeBestAvailableSeatsPublicHandler;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimService;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Services\Application\Handlers\Order\CreateOrderHandler;
use HiEvents\Services\Application\Handlers\Order\DTO\CreateOrderPublicDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\Feature\Support\InsertsSeatMapRows;
use Tests\TestCase;

class BestAvailableSeatRulesTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use InsertsSeatMapRows;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

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
    }

    public function test_no_seats_are_offered_when_every_choice_would_strand_a_seat(): void
    {
        $eventSeatMapId = $this->attachLayout($this->layoutWithRows([$this->fencedRow('e1', 100)]));

        $this->bestAvailable(quantity: 2)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.seat_uids', ['e1.0.1', 'e1.0.2']);

        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);

        $this->bestAvailable(quantity: 2)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.seat_uids', []);
    }

    public function test_a_suggestion_is_always_claimable_by_the_public_checkout(): void
    {
        $eventSeatMapId = $this->attachLayout($this->orphanTrapLayout());
        $this->updateEventSeatMap($eventSeatMapId, ['prevent_orphan_seats' => true]);
        DB::transaction(fn () => app(SeatClaimService::class)
            ->block($this->eventId, [$this->occurrenceId], ['e2.0.0', 'e3.0.0'], 'Held back'));

        $suggestion = $this->bestAvailable(quantity: 2)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data.seat_uids');

        $this->assertSame(['e2.0.1', 'e3.0.1'], $suggestion);

        $order = app(CreateOrderHandler::class)->handle($this->eventId, new CreateOrderPublicDTO(
            products: collect([new ProductOrderDetailsDTO(
                product_id: $this->productId,
                quantities: collect([new OrderProductPriceDTO(
                    quantity: 2,
                    price_id: $this->priceId,
                    seat_uids: $suggestion,
                )]),
                event_occurrence_id: $this->occurrenceId,
            )]),
            is_user_authenticated: false,
            session_identifier: sha1(uniqid('', true)),
            order_locale: 'en',
        ));

        $this->assertEqualsCanonicalizing(
            $suggestion,
            DB::table('seat_claims')->where('order_id', $order->getId())->pluck('seat_uid')->all(),
        );
    }

    public function test_a_quantity_above_the_per_order_limit_is_rejected(): void
    {
        $eventSeatMapId = $this->attachLayout($this->seatMapFixture('theatre'));
        $this->updateEventSeatMap($eventSeatMapId, ['max_seats_per_order' => 4]);

        $this->bestAvailable(quantity: 5)
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('quantity');

        $this->bestAvailable(quantity: 4)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(4, 'data.seat_uids');
    }

    public function test_accessible_requests_only_return_accessible_seats(): void
    {
        $layout = $this->seatMapFixture('theatre');
        $this->attachLayout($layout, 'b_standard');
        $accessibleUids = $this->accessibleSeatUids($layout, 'b_standard');

        $accessible = $this->bestAvailable(quantity: 2, accessible: true)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data.seat_uids');
        $standard = $this->bestAvailable(quantity: 2)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data.seat_uids');

        $this->assertCount(2, $accessible);
        $this->assertSame([], array_diff($accessible, $accessibleUids));
        $this->assertSame($standard, array_diff($standard, $accessibleUids));
    }

    public function test_a_zone_is_never_suggested_as_a_seat(): void
    {
        $layout = $this->seatMapFixture('club');
        $this->attachLayout($layout);

        $suggestion = $this->bestAvailable(quantity: 4)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data.seat_uids');

        $this->assertCount(4, $suggestion);
        $this->assertSame([], array_intersect($suggestion, ['z2', 'z3', 'z14']));
    }

    public function test_seats_held_back_by_the_organizer_are_never_suggested_to_either_channel(): void
    {
        $layout = $this->seatMapFixture('theatre');
        $this->attachLayout($layout);
        $heldBack = ['e2.0.9', 'e2.0.10', 'e2.0.11'];
        DB::transaction(fn () => app(SeatClaimService::class)
            ->block($this->eventId, [$this->occurrenceId], $heldBack, 'House seats'));

        $publicSuggestion = $this->bestAvailable(quantity: 3)
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data.seat_uids');

        $doorSuggestion = app(GetBoxOfficeBestAvailableSeatsPublicHandler::class)->handle(new BoxOfficeBestAvailableSeatsDTO(
            event_id: $this->eventId,
            event_occurrence_id: $this->occurrenceId,
            items: [new BoxOfficeSeatRequestItemDTO(product_id: $this->productId, quantity: 3)],
            excluded_seat_uids: [],
        ));

        $this->assertCount(3, $publicSuggestion);
        $this->assertSame([], array_intersect($publicSuggestion, $heldBack));
        $this->assertSame([], array_intersect($doorSuggestion[0], $heldBack));
        $this->assertCount(3, $doorSuggestion[0]);
    }

    private function bestAvailable(int $quantity, bool $accessible = false): TestResponse
    {
        return $this->getJson(
            "/public/events/{$this->eventId}/occurrences/{$this->occurrenceId}/best-available-seats"
            ."?product_id={$this->productId}&quantity=$quantity&accessible=".($accessible ? 1 : 0)
        );
    }

    private function attachLayout(array $layout, string $bandKey = 'b_premium'): int
    {
        return $this->insertEventSeatMap($layout, [$bandKey => [$this->productId]]);
    }

    /**
     * @return string[]
     */
    private function accessibleSeatUids(array $layout, string $bandKey): array
    {
        return collect($layout['areas'])
            ->flatMap(fn (array $area) => $area['elements'])
            ->flatMap(fn (array $element) => $element['seats'] ?? [])
            ->filter(fn (array $seat) => $seat['acc'] && $seat['band'] === $bandKey)
            ->pluck('uid')
            ->values()
            ->all();
    }

    private function orphanTrapLayout(): array
    {
        return $this->layoutWithRows([
            $this->fencedRow('e1', 100),
            $this->row('e2', [['b_premium', 90, 10], ['b_premium', 110, 10]]),
            $this->row('e3', [['b_premium', 90, 20], ['b_premium', 110, 20]]),
        ]);
    }

    private function fencedRow(string $elementId, int $y): array
    {
        return $this->row($elementId, [
            ['b_standard', 0, $y],
            ['b_premium', 20, $y],
            ['b_premium', 40, $y],
            ['b_standard', 60, $y],
        ]);
    }

    private function row(string $elementId, array $seats): array
    {
        return [
            'id' => $elementId,
            'type' => 'row',
            'x' => 0,
            'y' => 0,
            'rotation' => 0,
            'label' => strtoupper($elementId),
            'seats' => array_map(fn (array $seat, int $index) => [
                'uid' => "$elementId.0.$index",
                'label' => strtoupper($elementId).'-'.($index + 1),
                'band' => $seat[0],
                'x' => $seat[1],
                'y' => $seat[2],
                'acc' => false,
                'row' => 0,
                'gapAfter' => false,
            ], $seats, array_keys($seats)),
        ];
    }

    private function layoutWithRows(array $elements): array
    {
        $layout = $this->seatMapFixture('empty');
        $layout['areas'][0]['focal'] = ['x' => 100, 'y' => 0];
        $layout['areas'][0]['elements'] = $elements;

        return $layout;
    }
}
