<?php

namespace Tests\Feature\BoxOffice;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeSessionService;
use HiEvents\Exceptions\CannotDeleteEntityException;
use HiEvents\Services\Domain\Product\DeleteProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response as ResponseCodes;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class BoxOfficeDoorSaleTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private int $occurrenceId;

    private int $boxOfficeId;

    private string $boxOfficeShortId;

    private string $sessionToken;

    private int $assignedProductId;

    private int $assignedPriceId;

    private int $otherProductId;

    private int $otherPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['status' => EventStatus::LIVE->name]);
        DB::table('event_settings')->insert(['event_id' => $this->eventId, 'created_at' => now(), 'updated_at' => now()]);
        $this->occurrenceId = $this->insertOccurrence();

        $categoryId = DB::table('product_categories')->insertGetId([
            'event_id' => $this->eventId,
            'name' => 'Tickets',
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assignedProductId = $this->insertProduct();
        $this->otherProductId = $this->insertProduct();
        DB::table('products')->whereIn('id', [$this->assignedProductId, $this->otherProductId])->update(['product_category_id' => $categoryId]);
        $this->assignedPriceId = $this->insertPrice($this->assignedProductId, null);
        $this->otherPriceId = $this->insertPrice($this->otherProductId, null);

        $this->boxOfficeShortId = 'bo_'.uniqid();
        $this->boxOfficeId = DB::table('box_offices')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => $this->boxOfficeShortId,
            'name' => 'Front door',
            'pin_hash' => 'hash',
            'allow_discounts' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_box_offices')->insert(['product_id' => $this->assignedProductId, 'box_office_id' => $this->boxOfficeId]);

        $this->sessionToken = app(BoxOfficeSessionService::class)->create(
            boxOffice: (new BoxOfficeDomainObject)->setId($this->boxOfficeId)->setEventId($this->eventId)->setPinHash('hash'),
            operatorName: 'Sam',
            eventOccurrenceId: $this->occurrenceId,
            stripeTerminalReaderId: null,
        )->token;
    }

    public function test_a_product_not_assigned_to_the_box_office_cannot_be_sold(): void
    {
        $this->createOrder([$this->item($this->otherProductId, $this->otherPriceId)])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['items.0.product_id']);

        $this->assertSame(0, DB::table('orders')->where('box_office_id', $this->boxOfficeId)->count());
    }

    public function test_the_only_product_a_box_office_sells_cannot_be_deleted(): void
    {
        try {
            app(DeleteProductService::class)->deleteProduct($this->assignedProductId, $this->eventId);
            $this->fail('The product was deleted');
        } catch (CannotDeleteEntityException $exception) {
            $this->assertStringContainsString('Front door', $exception->getMessage());
        }

        $this->assertNull(DB::table('products')->where('id', $this->assignedProductId)->value('deleted_at'));

        $this->createOrder([$this->item($this->otherProductId, $this->otherPriceId)])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_deleting_one_of_several_products_keeps_the_box_office_restricted_to_the_rest(): void
    {
        $extraProductId = $this->insertProduct();
        DB::table('product_box_offices')->insert(['product_id' => $extraProductId, 'box_office_id' => $this->boxOfficeId]);

        app(DeleteProductService::class)->deleteProduct($extraProductId, $this->eventId);

        $this->assertSame(
            [$this->assignedProductId],
            DB::table('product_box_offices')->where('box_office_id', $this->boxOfficeId)->pluck('product_id')->all(),
        );

        $this->createOrder([$this->item($this->otherProductId, $this->otherPriceId)])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_a_product_assigned_to_the_box_office_can_be_sold(): void
    {
        $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId)])
            ->assertStatus(ResponseCodes::HTTP_CREATED);
    }

    public function test_replaying_a_sale_in_progress_returns_the_same_order(): void
    {
        $key = (string) Str::uuid();
        $items = [$this->item($this->assignedProductId, $this->assignedPriceId)];

        $first = $this->createOrder($items, $key)->assertStatus(ResponseCodes::HTTP_CREATED)->json('data.short_id');
        $replay = $this->createOrder($items, $key)->assertStatus(ResponseCodes::HTTP_CREATED)->json('data.short_id');

        $this->assertSame($first, $replay);
    }

    public function test_replaying_an_abandoned_sale_starts_a_new_order(): void
    {
        $key = (string) Str::uuid();
        $items = [$this->item($this->assignedProductId, $this->assignedPriceId)];

        $abandoned = $this->createOrder($items, $key)->json('data.short_id');
        DB::table('orders')->where('short_id', $abandoned)->update(['status' => OrderStatus::ABANDONED->name]);

        $fresh = $this->createOrder($items, $key)->assertStatus(ResponseCodes::HTTP_CREATED)->json('data.short_id');
        $replay = $this->createOrder($items, $key)->json('data.short_id');

        $this->assertNotSame($abandoned, $fresh);
        $this->assertSame($fresh, $replay);
    }

    public function test_a_cart_is_limited_to_fifty_lines(): void
    {
        $this->createOrder(array_fill(0, 51, $this->item($this->assignedProductId, $this->assignedPriceId)))
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_a_cash_tender_records_the_change_due(): void
    {
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId, quantity: 2)])->json('data.short_id');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::CASH->value, 'amount_tendered' => 50])
            ->assertStatus(ResponseCodes::HTTP_OK);

        $stored = DB::table('orders')->where('short_id', $orderShortId)->first();
        $this->assertSame(OrderStatus::COMPLETED->name, $stored->status);
        $this->assertSame(BoxOfficeTender::CASH->value, $stored->box_office_tender);
        $this->assertEquals(50, $stored->box_office_amount_tendered);
        $this->assertEquals(30, $stored->box_office_change_due);
        $this->assertNotNull($stored->box_office_completed_at);
    }

    public function test_the_door_order_list_says_which_tickets_are_already_checked_in(): void
    {
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId, 2)])->json('data.short_id');
        $this->tender($orderShortId, ['tender' => BoxOfficeTender::CASH->value, 'amount_tendered' => 20])
            ->assertStatus(ResponseCodes::HTTP_OK);

        $attendeeId = DB::table('attendees')
            ->where('order_id', DB::table('orders')->where('short_id', $orderShortId)->value('id'))
            ->orderBy('id')
            ->value('id');
        $checkInListId = DB::table('check_in_lists')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'cil_'.uniqid(),
            'name' => 'Door',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attendee_check_ins')->insert([
            'short_id' => 'ci_'.uniqid(),
            'check_in_list_id' => $checkInListId,
            'product_id' => $this->assignedProductId,
            'attendee_id' => $attendeeId,
            'event_id' => $this->eventId,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $attendees = collect($this->withHeader('X-Box-Office-Session', $this->sessionToken)
            ->getJson("/public/box-offices/{$this->boxOfficeShortId}/orders")
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json('data.0.attendees'));

        $this->assertTrue($attendees->firstWhere('id', $attendeeId)['is_checked_in']);
        $this->assertSame(1, $attendees->where('is_checked_in', false)->count());
    }

    public function test_door_details_are_shown_to_the_door_but_not_on_the_buyers_order_page(): void
    {
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId)])->json('data.short_id');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::CASH->value, 'amount_tendered' => 20])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.box_office_operator_name', 'Sam')
            ->assertJsonPath('data.box_office_tender', BoxOfficeTender::CASH->value)
            ->assertJsonPath('data.created_at', fn ($createdAt) => is_string($createdAt) && $createdAt !== '');

        $this->flushHeaders();

        $this->getJson("/public/events/{$this->eventId}/order/{$orderShortId}")
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonMissingPath('data.box_office_operator_name')
            ->assertJsonMissingPath('data.box_office_tender')
            ->assertJsonMissingPath('data.box_office_reference');
    }

    public function test_a_sale_paid_elsewhere_needs_a_reference(): void
    {
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId)])->json('data.short_id');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::OTHER->value])
            ->assertStatus(ResponseCodes::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors('reference');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::OTHER->value, 'reference' => 'SumUp 4471'])
            ->assertStatus(ResponseCodes::HTTP_OK);

        $this->assertSame('SumUp 4471', DB::table('orders')->where('short_id', $orderShortId)->value('box_office_reference'));
    }

    public function test_a_comp_completes_the_sale_at_no_charge(): void
    {
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId)])->json('data.short_id');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::COMP->value])
            ->assertStatus(ResponseCodes::HTTP_OK);

        $stored = DB::table('orders')->where('short_id', $orderShortId)->first();
        $this->assertSame(OrderStatus::COMPLETED->name, $stored->status);
        $this->assertSame(BoxOfficeTender::COMP->value, $stored->box_office_tender);
        $this->assertEquals(0, $stored->total_gross);
    }

    public function test_a_comp_is_refused_when_the_box_office_does_not_allow_discounts(): void
    {
        DB::table('box_offices')->where('id', $this->boxOfficeId)->update(['allow_discounts' => false]);
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId)])->json('data.short_id');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::COMP->value])
            ->assertStatus(ResponseCodes::HTTP_CONFLICT);

        $this->assertSame(OrderStatus::RESERVED->name, DB::table('orders')->where('short_id', $orderShortId)->value('status'));
    }

    public function test_a_free_sale_completes_when_the_box_office_does_not_allow_discounts(): void
    {
        DB::table('box_offices')->where('id', $this->boxOfficeId)->update(['allow_discounts' => false]);
        $freeProductId = $this->insertProduct(priceType: 'FREE');
        DB::table('products')->where('id', $freeProductId)->update(['product_category_id' => DB::table('products')->where('id', $this->assignedProductId)->value('product_category_id')]);
        $freePriceId = $this->insertPrice($freeProductId, null, price: 0);
        DB::table('product_box_offices')->insert(['product_id' => $freeProductId, 'box_office_id' => $this->boxOfficeId]);
        $orderShortId = $this->createOrder([$this->item($freeProductId, $freePriceId)])->json('data.short_id');

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::COMP->value])
            ->assertStatus(ResponseCodes::HTTP_OK);

        $stored = DB::table('orders')->where('short_id', $orderShortId)->first();
        $this->assertSame(OrderStatus::COMPLETED->name, $stored->status);
        $this->assertSame(BoxOfficeTender::FREE->value, $stored->box_office_tender);
    }

    public function test_tendering_an_expired_sale_reports_the_sale_expired_code(): void
    {
        $orderShortId = $this->createOrder([$this->item($this->assignedProductId, $this->assignedPriceId)])->json('data.short_id');
        DB::table('orders')->where('short_id', $orderShortId)->update(['reserved_until' => now()->subMinute()]);

        $this->tender($orderShortId, ['tender' => BoxOfficeTender::CASH->value, 'amount_tendered' => 10])
            ->assertStatus(ResponseCodes::HTTP_CONFLICT)
            ->assertJsonPath('error_code', 'SALE_EXPIRED');
    }

    private function item(int $productId, int $priceId, int $quantity = 1): array
    {
        return ['product_id' => $productId, 'product_price_id' => $priceId, 'quantity' => $quantity];
    }

    private function createOrder(array $items, ?string $idempotencyKey = null): TestResponse
    {
        return $this->withHeader('X-Box-Office-Session', $this->sessionToken)
            ->postJson("/public/box-offices/{$this->boxOfficeShortId}/orders", [
                'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
                'items' => $items,
            ]);
    }

    private function tender(string $orderShortId, array $body): TestResponse
    {
        return $this->withHeader('X-Box-Office-Session', $this->sessionToken)
            ->postJson("/public/box-offices/{$this->boxOfficeShortId}/orders/{$orderShortId}/tender", $body);
    }
}
