<?php

namespace Tests\Feature\Http\Actions\Reports;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class GetReportActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const LOCAL_DAY_START_UTC = '2026-03-10T15:00:00Z';

    private const LOCAL_DAY_END_UTC = '2026-03-11T14:59:59Z';

    private const INSIDE_LOCAL_DAY_UTC = '2026-03-10 16:00:00';

    private const AFTER_LOCAL_DAY_UTC = '2026-03-11 16:00:00';

    private string $authToken;

    private int $firstDateId;

    private int $secondDateId;

    private int $productId;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::RECURRING->name);
        DB::table('events')->where('id', $this->eventId)->update(['timezone' => 'Asia/Tokyo']);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));

        $this->firstDateId = $this->insertOccurrence(daysAhead: 1);
        $this->secondDateId = $this->insertOccurrence(daysAhead: 2);
        $this->productId = $this->insertProduct();
        $this->priceId = $this->insertPrice($this->productId, null);
    }

    public function test_occurrence_summary_for_one_date_returns_only_that_date(): void
    {
        $rows = $this->getJson(
            "/events/{$this->eventId}/reports/occurrence_summary?occurrence_id={$this->firstDateId}",
            $this->headers(),
        )->assertStatus(ResponseCodes::HTTP_OK)->json();

        $this->assertSame([$this->firstDateId], array_column($rows, 'occurrence_id'));
    }

    public function test_occurrence_summary_without_a_date_returns_every_date(): void
    {
        $rows = $this->getJson("/events/{$this->eventId}/reports/occurrence_summary", $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json();

        $this->assertSame([$this->firstDateId, $this->secondDateId], array_column($rows, 'occurrence_id'));
    }

    public function test_product_sales_covers_the_event_local_day(): void
    {
        $this->insertCompletedOrder(self::INSIDE_LOCAL_DAY_UTC, quantity: 2);
        $this->insertCompletedOrder(self::AFTER_LOCAL_DAY_UTC, quantity: 5);

        $rows = $this->localDayReport('product_sales');

        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows[0]['number_sold']);
    }

    public function test_promo_codes_cover_the_event_local_day(): void
    {
        $promoCodeId = DB::table('promo_codes')->insertGetId([
            'code' => 'early',
            'discount' => 10,
            'discount_type' => 'PERCENTAGE',
            'event_id' => $this->eventId,
            'created_at' => now(),
        ]);
        $this->insertCompletedOrder(self::INSIDE_LOCAL_DAY_UTC, quantity: 1, promoCodeId: $promoCodeId, totalGross: 9.00);
        $this->insertCompletedOrder(self::AFTER_LOCAL_DAY_UTC, quantity: 1, promoCodeId: $promoCodeId, totalGross: 90.00);

        $rows = $this->localDayReport('promo_codes_report');

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['times_used']);
        $this->assertEquals(9.00, $rows[0]['total_gross_sales']);
    }

    private function localDayReport(string $reportType): array
    {
        $query = http_build_query(['start_date' => self::LOCAL_DAY_START_UTC, 'end_date' => self::LOCAL_DAY_END_UTC]);

        return $this->getJson("/events/{$this->eventId}/reports/$reportType?$query", $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json();
    }

    private function insertCompletedOrder(string $createdAtUtc, int $quantity, ?int $promoCodeId = null, float $totalGross = 0): void
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        DB::table('orders')->where('id', $orderId)->update([
            'created_at' => $createdAtUtc,
            'promo_code_id' => $promoCodeId,
            'total_gross' => $totalGross,
            'email' => 'buyer+'.$orderId.'@example.test',
        ]);
        $this->insertOrderItem($orderId, $this->productId, $this->priceId, $this->firstDateId, $quantity);
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
