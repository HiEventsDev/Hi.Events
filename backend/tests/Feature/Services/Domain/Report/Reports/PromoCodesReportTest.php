<?php

namespace Tests\Feature\Services\Domain\Report\Reports;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class PromoCodesReportTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private string $authToken;

    private int $occurrenceId;

    private int $productId;

    private int $priceId;

    private int $promoCodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->occurrenceId = $this->insertOccurrence();
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null, price: 100.00);

        $this->promoCodeId = DB::table('promo_codes')->insertGetId([
            'event_id' => $this->eventId,
            'code' => 'SAVE20',
            'discount' => 20,
            'discount_type' => PromoCodeDiscountTypeEnum::PERCENTAGE->name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_percentage_discount_reports_the_list_price_and_the_amount_taken_off(): void
    {
        $this->insertPromoOrder([['price' => 80.00, 'price_before_discount' => 100.00, 'quantity' => 1]]);

        $row = $this->promoRow();

        $this->assertSame(1, $row['times_used']);
        $this->assertEqualsWithDelta(100.00, (float) $row['total_before_discounts'], 0.001);
        $this->assertEqualsWithDelta(20.00, (float) $row['total_discount_amount'], 0.001);
        $this->assertEqualsWithDelta(80.00, (float) $row['total_gross_sales'], 0.001);
    }

    public function test_an_order_mixing_discounted_and_full_price_lines_counts_only_the_discounted_line_as_discount(): void
    {
        $this->insertPromoOrder([
            ['price' => 80.00, 'price_before_discount' => 100.00, 'quantity' => 2],
            ['price' => 50.00, 'price_before_discount' => null, 'quantity' => 1],
        ]);

        $row = $this->promoRow();

        $this->assertSame(1, $row['times_used']);
        $this->assertEqualsWithDelta(250.00, (float) $row['total_before_discounts'], 0.001);
        $this->assertEqualsWithDelta(40.00, (float) $row['total_discount_amount'], 0.001);
        $this->assertEqualsWithDelta(210.00, (float) $row['total_gross_sales'], 0.001);
    }

    private function insertPromoOrder(array $lines): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        $totalGross = array_sum(array_map(fn (array $line) => $line['price'] * $line['quantity'], $lines));

        DB::table('orders')->where('id', $orderId)->update([
            'promo_code_id' => $this->promoCodeId,
            'promo_code' => 'SAVE20',
            'email' => 'buyer+'.uniqid().'@example.test',
            'total_gross' => $totalGross,
        ]);

        foreach ($lines as $line) {
            $itemId = $this->insertOrderItem($orderId, $this->productId, $this->priceId, $this->occurrenceId, $line['quantity']);
            DB::table('order_items')->where('id', $itemId)->update([
                'price' => $line['price'],
                'price_before_discount' => $line['price_before_discount'],
                'total_before_additions' => $line['price'] * $line['quantity'],
            ]);
        }
    }

    private function promoRow(): array
    {
        $rows = $this->getJson("/events/{$this->eventId}/reports/promo_codes_report", ['Authorization' => 'Bearer '.$this->authToken])
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json();

        return collect($rows)->firstWhere('promo_code', 'SAVE20') ?? $this->fail('No row for SAVE20');
    }
}
