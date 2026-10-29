<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class NestedMoneyRedactionTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const ORDER_ITEM_MONEY = ['price', 'price_before_discount', 'total_before_additions', 'taxes_and_fees_rollup'];

    private const BOX_OFFICE_ORDER_MONEY = ['box_office_amount_tendered', 'box_office_change_due'];

    private const OCCURRENCE_STATS_MONEY = ['total_gross_sales', 'total_tax', 'total_fee', 'total_refunded'];

    private const EVENT_STATS_MONEY = ['sales_total_gross', 'total_tax', 'sales_total_before_additions', 'total_fee', 'total_refunded'];

    private int $accountId;

    private int $eventId;

    private int $orderId;

    private int $boxOfficeOrderId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        $this->eventId = $this->createEvent($this->accountId, $this->createOrganizer($this->accountId));
        $this->seedMoneyRows();
    }

    public function test_members_without_financials_get_no_nested_or_secondary_money(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($organizer, $this->accountId, ['VIEW_FINANCIALS' => false]);
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);

        foreach ([$organizer, $viewer] as $member) {
            $responses = $this->fetchMoneySurfaces($member);

            $this->assertSame(2, $responses['order_item']['quantity']);
            $this->assertKeysAbsent(self::ORDER_ITEM_MONEY, $responses['order_item']);

            $this->assertSame('CASH', $responses['box_office_order']['box_office_tender']);
            $this->assertKeysAbsent(self::BOX_OFFICE_ORDER_MONEY, $responses['box_office_order']);

            $this->assertSame(3, $responses['affiliate']['total_sales']);
            $this->assertArrayNotHasKey('total_sales_gross', $responses['affiliate']);

            $this->assertSame(1, $responses['box_office']['sales_count']);
            $this->assertArrayNotHasKey('gross_sales', $responses['box_office']);

            $this->assertSame(4, $responses['occurrence_stats']['products_sold']);
            $this->assertKeysAbsent(self::OCCURRENCE_STATS_MONEY, $responses['occurrence_stats']);

            $this->assertSame(5, $responses['event_stats']['products_sold']);
            $this->assertKeysAbsent(self::EVENT_STATS_MONEY, $responses['event_stats']);
        }
    }

    public function test_members_with_financials_get_nested_and_secondary_money(): void
    {
        $organizer = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $this->setPermissionOverrides($viewer, $this->accountId, ['VIEW_FINANCIALS' => true]);

        foreach ([$organizer, $viewer] as $member) {
            $responses = $this->fetchMoneySurfaces($member);

            $this->assertEquals(25.5, $responses['order_item']['price']);
            $this->assertEquals(30, $responses['order_item']['price_before_discount']);
            $this->assertEquals(51, $responses['order_item']['total_before_additions']);
            $this->assertSame([['name' => 'VAT', 'rate' => 10, 'value' => 5.1]], $responses['order_item']['taxes_and_fees_rollup']['taxes']);

            $this->assertEquals(50, $responses['box_office_order']['box_office_amount_tendered']);
            $this->assertEquals(10, $responses['box_office_order']['box_office_change_due']);

            $this->assertEquals(300.5, $responses['affiliate']['total_sales_gross']);
            $this->assertEquals(40, $responses['box_office']['gross_sales']);

            $this->assertEquals(
                ['total_gross_sales' => 410.25, 'total_tax' => 41.5, 'total_fee' => 2.25, 'total_refunded' => 12],
                array_intersect_key($responses['occurrence_stats'], array_flip(self::OCCURRENCE_STATS_MONEY)),
            );
            $this->assertEquals(
                ['sales_total_gross' => 980.75, 'total_tax' => 98, 'sales_total_before_additions' => 882.75, 'total_fee' => 3.5, 'total_refunded' => 20],
                array_intersect_key($responses['event_stats'], array_flip(self::EVENT_STATS_MONEY)),
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchMoneySurfaces(User $member): array
    {
        $headers = $this->headersFor($member, $this->accountId);

        $boxOffices = $this->getJson("/events/{$this->eventId}/box-offices", $headers)->assertOk()->json('data');
        $events = $this->getJson('/events', $headers)->assertOk()->json('data');

        return [
            'order_item' => $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $headers)->assertOk()->json('data.order_items.0'),
            'box_office_order' => $this->getJson("/events/{$this->eventId}/orders/{$this->boxOfficeOrderId}", $headers)->assertOk()->json('data'),
            'affiliate' => $this->getJson("/events/{$this->eventId}/affiliates", $headers)->assertOk()->json('data.0'),
            'box_office' => collect($boxOffices)->firstWhere('name', 'Front Door'),
            'occurrence_stats' => $this->getJson("/events/{$this->eventId}/occurrences", $headers)->assertOk()->json('data.0.statistics'),
            'event_stats' => collect($events)->firstWhere('id', $this->eventId)['statistics'],
        ];
    }

    private function assertKeysAbsent(array $keys, array $payload): void
    {
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    private function seedMoneyRows(): void
    {
        $occurrenceId = DB::table('event_occurrences')->insertGetId([
            'short_id' => 'oc_'.uniqid(),
            'event_id' => $this->eventId,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeek()->addHours(2),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_occurrence_statistics')->insert([
            'event_id' => $this->eventId,
            'event_occurrence_id' => $occurrenceId,
            'products_sold' => 4,
            'sales_total_gross' => 410.25,
            'total_tax' => 41.5,
            'total_fee' => 2.25,
            'total_refunded' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'products_sold' => 5,
            'sales_total_gross' => 980.75,
            'total_tax' => 98,
            'sales_total_before_additions' => 882.75,
            'total_fee' => 3.5,
            'total_refunded' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('affiliates')->insert([
            'event_id' => $this->eventId,
            'account_id' => $this->accountId,
            'name' => 'Partner',
            'code' => 'PARTNER'.random_int(100, 999),
            'total_sales' => 3,
            'total_sales_gross' => 300.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'title' => 'General Admission',
            'event_id' => $this->eventId,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->orderId = $this->insertOrder(['total_gross' => 56.1, 'total_tax' => 5.1, 'total_before_additions' => 51]);
        DB::table('order_items')->insert([
            'order_id' => $this->orderId,
            'product_id' => $productId,
            'product_price_id' => $productPriceId,
            'event_occurrence_id' => $occurrenceId,
            'item_name' => 'General Admission',
            'quantity' => 2,
            'price' => 25.5,
            'price_before_discount' => 30,
            'total_before_additions' => 51,
            'total_tax' => 5.1,
            'total_gross' => 56.1,
            'taxes_and_fees_rollup' => json_encode(['fees' => [], 'taxes' => [['name' => 'VAT', 'rate' => 10, 'value' => 5.1]]]),
        ]);

        $boxOfficeId = DB::table('box_offices')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'bo_'.uniqid(),
            'name' => 'Front Door',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->boxOfficeOrderId = $this->insertOrder([
            'total_gross' => 40,
            'total_before_additions' => 40,
            'box_office_id' => $boxOfficeId,
            'box_office_operator_name' => 'Sam',
            'box_office_tender' => 'CASH',
            'box_office_amount_tendered' => 50,
            'box_office_change_due' => 10,
        ]);
    }

    private function insertOrder(array $attributes): int
    {
        return DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
            'created_at' => now(),
            'updated_at' => now(),
            ...$attributes,
        ]);
    }
}
