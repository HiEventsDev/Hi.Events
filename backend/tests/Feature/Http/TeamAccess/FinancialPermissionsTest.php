<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Role;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsAccountUsers;
use Tests\TestCase;

class FinancialPermissionsTest extends TestCase
{
    use ActsAsAccountUsers;
    use DatabaseTransactions;

    private const MONEY_KEYS = ['total_gross', 'total_tax', 'total_fee', 'total_refunded', 'total_before_additions', 'taxes_and_fees_rollup'];

    private int $accountId;

    private int $organizerId;

    private int $eventId;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        [, $this->accountId] = $this->createAccountOwner();
        $this->organizerId = $this->createOrganizer($this->accountId);
        $this->eventId = $this->createEvent($this->accountId, $this->organizerId);
        $this->orderId = DB::table('orders')->insertGetId([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'ada@example.test',
            'total_gross' => 120.50,
            'total_tax' => 20.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_organizer_without_financials_sees_orders_without_money(): void
    {
        $user = $this->organizerWithoutFinancials();

        $order = $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $this->headers($user))
            ->assertOk()
            ->json('data');

        $this->assertSame('ada@example.test', $order['email']);
        foreach (self::MONEY_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $order);
        }

        $listed = $this->getJson("/events/{$this->eventId}/orders", $this->headers($user))->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('total_gross', $listed);
    }

    public function test_members_without_financials_never_receive_the_buyer_order_key(): void
    {
        $user = $this->organizerWithoutFinancials();
        $headers = $this->headers($user);

        $order = $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $headers)->assertOk()->json('data');
        $listed = $this->getJson("/events/{$this->eventId}/orders", $headers)->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('short_id', $order);
        $this->assertArrayNotHasKey('short_id', $listed);

        $this->app['auth']->forgetGuards();
        $this->getJson("/public/events/{$this->eventId}/order/".($order['short_id'] ?? 'missing'))->assertNotFound();
    }

    public function test_viewers_with_financials_see_totals_but_not_the_buyer_order_key(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $this->setPermissionOverrides($viewer, $this->accountId, ['VIEW_FINANCIALS' => true]);

        $order = $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $this->headers($viewer))->assertOk()->json('data');

        $this->assertSame(120.5, $order['total_gross']);
        $this->assertArrayNotHasKey('short_id', $order);
    }

    public function test_members_without_financials_cannot_filter_orders_by_amount(): void
    {
        DB::table('orders')->insert([
            'short_id' => 'ord_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => 'USD',
            'status' => 'COMPLETED',
            'public_id' => 'PUB_'.uniqid(),
            'first_name' => 'Small',
            'last_name' => 'Buyer',
            'email' => 'small@example.test',
            'total_gross' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $withoutFinancials = $this->organizerWithoutFinancials();
        $withFinancials = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $query = "/events/{$this->eventId}/orders?filter_fields[total_gross][gte]=100";

        $this->assertSame(1, $this->getJson($query, $this->headers($withFinancials))->assertOk()->json('meta.total'));
        $this->assertSame(2, $this->getJson($query, $this->headers($withoutFinancials))->assertOk()->json('meta.total'));
    }

    public function test_organizer_with_financials_sees_order_totals(): void
    {
        $user = $this->addAccountUser($this->accountId, Role::ORGANIZER);

        $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $this->headers($user))
            ->assertOk()
            ->assertJsonPath('data.total_gross', 120.5)
            ->assertJsonPath('data.short_id', DB::table('orders')->where('id', $this->orderId)->value('short_id'));
    }

    public function test_event_list_and_stats_drop_revenue_but_keep_counts(): void
    {
        $user = $this->organizerWithoutFinancials();
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'products_sold' => 2,
            'sales_total_gross' => 120.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = $this->getJson("/events/{$this->eventId}/stats", $this->headers($user))->assertOk()->json('data');
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayNotHasKey('total_gross_sales', $stats);
        $this->assertArrayNotHasKey('total_refunded', $stats);
        foreach ($stats['daily_stats'] as $day) {
            $this->assertArrayNotHasKey('total_sales_gross', $day);
        }

        $organizerStats = $this->getJson("/organizers/{$this->organizerId}/stats", $this->headers($user))->assertOk()->json('data');
        $this->assertArrayHasKey('total_orders', $organizerStats);
        $this->assertArrayNotHasKey('total_gross_sales', $organizerStats);

        $event = $this->getJson('/events', $this->headers($user))->assertOk()->json('data.0');
        $this->assertSame(2, $event['statistics']['products_sold']);
        $this->assertArrayNotHasKey('sales_total_gross', $event['statistics']);
    }

    public function test_financial_endpoints_are_refused(): void
    {
        $user = $this->organizerWithoutFinancials();

        $this->getJson("/events/{$this->eventId}/reports/product_sales", $this->headers($user))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
        $this->getJson("/organizers/{$this->organizerId}/reports/revenue_summary", $this->headers($user))->assertForbidden();
        $this->postJson("/events/{$this->eventId}/orders/export", [], $this->headers($user))->assertForbidden();
        $this->getJson("/events/{$this->eventId}/webhooks", $this->headers($user))->assertForbidden();
        $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/refund", ['amount' => 1], $this->headers($user))->assertForbidden();
        $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/cancel", ['refund' => true], $this->headers($user))->assertForbidden();
    }

    public function test_check_in_report_stays_available_without_financials(): void
    {
        $user = $this->organizerWithoutFinancials();

        $this->getJson("/organizers/{$this->organizerId}/reports/check_in_summary", $this->headers($user))->assertOk();
    }

    public function test_organizer_without_refunds_can_see_money_but_not_refund(): void
    {
        $user = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($user, $this->accountId, ['ISSUE_REFUNDS' => false]);

        $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $this->headers($user))
            ->assertOk()
            ->assertJsonPath('data.total_gross', 120.5);

        $this->postJson("/events/{$this->eventId}/orders/{$this->orderId}/mark-as-paid", [], $this->headers($user))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_REQUIRED');
    }

    public function test_viewer_with_financials_can_read_reports(): void
    {
        $viewer = $this->addAccountUser($this->accountId, Role::VIEWER);
        $this->setPermissionOverrides($viewer, $this->accountId, ['VIEW_FINANCIALS' => true]);

        $this->getJson("/organizers/{$this->organizerId}/reports/revenue_summary", $this->headers($viewer))->assertOk();
        $this->getJson("/events/{$this->eventId}/orders/{$this->orderId}", $this->headers($viewer))
            ->assertOk()
            ->assertJsonPath('data.total_gross', 120.5);
    }

    private function organizerWithoutFinancials(): User
    {
        $user = $this->addAccountUser($this->accountId, Role::ORGANIZER);
        $this->setPermissionOverrides($user, $this->accountId, ['VIEW_FINANCIALS' => false]);

        return $user;
    }

    private function headers(User $user): array
    {
        return $this->headersFor($user, $this->accountId);
    }
}
