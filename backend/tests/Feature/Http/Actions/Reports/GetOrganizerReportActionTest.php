<?php

namespace Tests\Feature\Http\Actions\Reports;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class GetOrganizerReportActionTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;

    private const LOCAL_DAY_START_UTC = '2026-03-10T15:00:00Z';

    private const LOCAL_DAY_END_UTC = '2026-03-11T14:59:59Z';

    private const INSIDE_LOCAL_DAY_UTC = '2026-03-10 16:00:00';

    private const AFTER_LOCAL_DAY_UTC = '2026-03-11 16:00:00';

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->insertAccountAndOrganizer();
        DB::table('organizers')->where('id', $this->organizerId)->update(['timezone' => 'Asia/Tokyo']);
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('events')->where('id', $this->eventId)->update(['timezone' => 'Asia/Tokyo']);
        $this->authToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public static function currencyFilteredReports(): array
    {
        return [
            'revenue_summary' => ['revenue_summary'],
            'events_performance' => ['events_performance'],
            'tax_summary' => ['tax_summary'],
            'platform_fees' => ['platform_fees'],
        ];
    }

    #[DataProvider('currencyFilteredReports')]
    public function test_a_quote_in_the_currency_is_treated_as_data(string $reportType): void
    {
        $this->insertCompletedOrder(self::INSIDE_LOCAL_DAY_UTC, taxValue: 5.00, feeGross: 100);

        $this->getJson("/organizers/{$this->organizerId}/reports/$reportType?currency=".rawurlencode("'||"), $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK);
    }

    public function test_the_currency_filter_still_matches_orders_in_that_currency(): void
    {
        $this->insertCompletedOrder(self::INSIDE_LOCAL_DAY_UTC, taxValue: 5.00, feeGross: 100);

        $usd = $this->localDayReport('tax_summary', ['currency' => 'USD'])['data'];
        $eur = $this->localDayReport('tax_summary', ['currency' => 'EUR'])['data'];

        $this->assertCount(1, $usd);
        $this->assertSame([], $eur);
    }

    public function test_tax_summary_covers_the_organizer_local_day(): void
    {
        $this->insertCompletedOrder(self::INSIDE_LOCAL_DAY_UTC, taxValue: 5.00, feeGross: 0);
        $this->insertCompletedOrder(self::AFTER_LOCAL_DAY_UTC, taxValue: 50.00, feeGross: 0);

        $rows = $this->localDayReport('tax_summary')['data'];

        $this->assertCount(1, $rows);
        $this->assertEquals(5.00, $rows[0]['total_collected']);
        $this->assertSame(1, $rows[0]['order_count']);
    }

    public function test_platform_fees_cover_the_organizer_local_day(): void
    {
        $insideOrderId = $this->insertCompletedOrder(self::INSIDE_LOCAL_DAY_UTC, taxValue: 0, feeGross: 100);
        $this->insertCompletedOrder(self::AFTER_LOCAL_DAY_UTC, taxValue: 0, feeGross: 900);

        $response = $this->localDayReport('platform_fees');

        $this->assertSame([$insideOrderId], array_column($response['data'], 'order_id'));
    }

    private function localDayReport(string $reportType, array $extra = []): array
    {
        $query = http_build_query([
            'start_date' => self::LOCAL_DAY_START_UTC,
            'end_date' => self::LOCAL_DAY_END_UTC,
            ...$extra,
        ]);

        return $this->getJson("/organizers/{$this->organizerId}/reports/$reportType?$query", $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->json();
    }

    private function insertCompletedOrder(string $createdAtUtc, float $taxValue, int $feeGross): int
    {
        $orderId = $this->insertOrder(OrderStatus::COMPLETED->name);
        DB::table('orders')->where('id', $orderId)->update([
            'created_at' => $createdAtUtc,
            'total_gross' => 10.00 + $taxValue,
            'taxes_and_fees_rollup' => json_encode([
                'taxes' => [['name' => 'VAT', 'rate' => 23, 'value' => $taxValue]],
                'fees' => [],
            ]),
        ]);

        if ($feeGross > 0) {
            DB::table('stripe_payments')->insert([
                'order_id' => $orderId,
                'payment_intent_id' => 'pi_'.uniqid(),
                'amount_received' => 1000,
                'application_fee_gross' => $feeGross,
                'currency' => 'usd',
                'created_at' => $createdAtUtc,
                'updated_at' => $createdAtUtc,
            ]);
        }

        return $orderId;
    }

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->authToken];
    }
}
