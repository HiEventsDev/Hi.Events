<?php

declare(strict_types=1);

namespace Tests\Feature\Enterprise\Licensing;

use HiEvents\DomainObjects\Enums\EventType;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Licensing\Plan;
use HiEvents\Http\ResponseCodes;
use HiEvents\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Concerns\ManagesLicence;
use Tests\Feature\Support\InsertsRecurringEventRows;
use Tests\TestCase;

class LicenceSalesTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRecurringEventRows;
    use ManagesLicence;

    private string $superAdminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2041-03-01 12:00:00');
        $this->insertAccountAndOrganizer();
        $this->eventId = $this->insertEvent(EventType::SINGLE->name);
        DB::table('account_users')->where('user_id', $this->userId)->update(['role' => 'SUPERADMIN']);
        $this->superAdminToken = JWTAuth::claims(['account_id' => $this->accountId])->fromUser(User::find($this->userId));
    }

    public function test_the_licence_shows_the_sales_it_covers(): void
    {
        $this->useLicence(expiresAt: '2041-07-01', plan: Plan::OWN_EVENTS, coveredSales: 300000);

        $this->getJson('/admin/licence', $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonPath('data.plan', 'own_events')
            ->assertJsonPath('data.covered_sales', 300000)
            ->assertJsonPath('data.features', ['white_label']);
    }

    public function test_sales_are_reported_for_the_current_and_previous_licence_year(): void
    {
        $this->useLicence(expiresAt: '2041-07-01', coveredSales: 300000);

        $this->order('2040-07-01 00:00:00', beforeAdditions: 100, gross: 123);
        $this->order('2041-02-01 10:00:00', beforeAdditions: 200, gross: 246, refunded: 123);
        $this->order('2041-02-02 10:00:00', beforeAdditions: 80, gross: 80, refunded: 80);
        $this->order('2041-02-02 11:00:00', beforeAdditions: 90, gross: 90, refunded: 150);
        $this->order('2041-02-03 10:00:00', beforeAdditions: 50, gross: 60, currency: 'NOK');
        $this->order('2041-02-03 11:00:00', beforeAdditions: 70, gross: 70, refunded: 70, currency: 'SEK');
        $this->order('2041-02-04 10:00:00', beforeAdditions: 300, gross: 300, status: OrderStatus::CANCELLED->name);
        $this->order('2041-02-04 11:00:00', beforeAdditions: 500, gross: 500, status: OrderStatus::CANCELLED->name, paymentStatus: OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name);
        $this->order('2041-02-04 12:00:00', beforeAdditions: 500, gross: 500, status: OrderStatus::AWAITING_OFFLINE_PAYMENT->name, paymentStatus: OrderPaymentStatus::AWAITING_OFFLINE_PAYMENT->name);
        $this->order('2041-02-04 13:00:00', beforeAdditions: 500, gross: 500, status: OrderStatus::RESERVED->name, paymentStatus: OrderPaymentStatus::AWAITING_PAYMENT->name);
        $this->order('2041-02-05 10:00:00', beforeAdditions: 500, gross: 500, deleted: true);
        $this->order('2041-02-06 10:00:00', beforeAdditions: 0, gross: 0, paymentStatus: OrderPaymentStatus::NO_PAYMENT_REQUIRED->name);
        $this->order('2040-06-30 23:59:59', beforeAdditions: 40, gross: 40);
        $this->order('2039-06-30 23:59:59', beforeAdditions: 999, gross: 999);

        $this->getJson('/admin/licence/sales', $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.starts_on', '2040-07-01')
            ->assertJsonPath('data.0.ends_on', '2041-06-30')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.0.sales', [
                ['currency' => 'EUR', 'amount' => 500],
                ['currency' => 'NOK', 'amount' => 50],
            ])
            ->assertJsonPath('data.1.starts_on', '2039-07-01')
            ->assertJsonPath('data.1.ends_on', '2040-06-30')
            ->assertJsonPath('data.1.is_current', false)
            ->assertJsonPath('data.1.sales', [['currency' => 'EUR', 'amount' => 40]]);
    }

    public function test_a_lapsed_licence_reports_its_final_year_as_not_current(): void
    {
        $this->useLicence(expiresAt: '2040-07-01', coveredSales: 300000);

        $this->getJson('/admin/licence/sales', $this->headers())
            ->assertJsonPath('data.0.starts_on', '2039-07-01')
            ->assertJsonPath('data.0.ends_on', '2040-06-30')
            ->assertJsonPath('data.0.is_current', false);
    }

    public function test_a_key_without_covered_sales_reports_no_sales(): void
    {
        $this->useCloudLicence();

        $this->getJson('/admin/licence/sales', $this->headers())
            ->assertStatus(ResponseCodes::HTTP_OK)
            ->assertJsonCount(0, 'data');
    }

    public function test_only_a_superadmin_can_see_licence_sales(): void
    {
        DB::table('account_users')->where('user_id', $this->userId)->update(['role' => 'ADMIN']);

        $this->getJson('/admin/licence/sales', $this->headers())
            ->assertStatus(ResponseCodes::HTTP_FORBIDDEN);
    }

    private function order(
        string $createdAt,
        float $beforeAdditions,
        float $gross,
        float $refunded = 0,
        string $currency = 'EUR',
        string $status = 'COMPLETED',
        string $paymentStatus = 'PAYMENT_RECEIVED',
        bool $deleted = false,
    ): void {
        DB::table('orders')->insert([
            'short_id' => 'ord_'.uniqid(),
            'public_id' => 'PUB_'.uniqid(),
            'event_id' => $this->eventId,
            'currency' => $currency,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'total_before_additions' => $beforeAdditions,
            'total_gross' => $gross,
            'total_tax' => $gross - $beforeAdditions,
            'total_refunded' => $refunded,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'deleted_at' => $deleted ? $createdAt : null,
        ]);
    }

    private function headers(): array
    {
        $this->app['auth']->forgetGuards();
        $this->app['tymon.jwt']->unsetToken();

        return ['Authorization' => "Bearer {$this->superAdminToken}", 'Accept' => 'application/json'];
    }
}
