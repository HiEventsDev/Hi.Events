<?php

declare(strict_types=1);

namespace Tests\Feature\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashlessTransactionRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CashlessTransactionRepositoryInterface $repository;

    private int $eventId;

    private int $salesPointId;

    private int $marieWalletId;

    private int $paulWalletId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(CashlessTransactionRepositoryInterface::class);
        $walletService = $this->app->make(CashlessWalletService::class);

        $now = now()->toDateTimeString();
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $productId = DB::table('products')->insertGetId([
            'title' => 'Beer',
            'event_id' => $this->eventId,
            'product_type' => 'GENERAL',
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $priceId = DB::table('product_prices')->insertGetId([
            'product_id' => $productId,
            'price' => 5.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'o_'.uniqid(),
            'public_id' => 'O-'.strtoupper(uniqid()),
            'status' => 'COMPLETED',
            'currency' => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->salesPointId = DB::table('cashless_sales_points')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'csp_'.uniqid(),
            'name' => 'Main Bar',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->marieWalletId = $this->makeWallet($orderId, $productId, $priceId, 'Marie', 'Durand');
        $this->paulWalletId = $this->makeWallet($orderId, $productId, $priceId, 'Paul', 'Martin');

        $walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $this->marieWalletId,
            type: CashlessTransactionType::TOPUP_ONLINE,
            positive_amount: 20.00,
        ));
        $walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $this->marieWalletId,
            type: CashlessTransactionType::PURCHASE,
            positive_amount: 5.00,
            sales_point_id: $this->salesPointId,
        ));
        $walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $this->paulWalletId,
            type: CashlessTransactionType::TOPUP_STAFF,
            positive_amount: 10.00,
            sales_point_id: $this->salesPointId,
        ));
    }

    public function test_it_can_be_filtered_to_one_wallet(): void
    {
        $ids = $this->walletIdsFor(['filter_fields' => ['cashless_wallet_id' => ['eq' => $this->paulWalletId]]]);

        $this->assertSame([$this->paulWalletId], $ids);
    }

    public function test_it_can_be_filtered_to_one_sales_point(): void
    {
        $result = $this->repository->findByEventId($this->eventId, QueryParamsDTO::fromArray([
            'filter_fields' => ['cashless_sales_point_id' => ['eq' => $this->salesPointId]],
        ]));

        $this->assertCount(2, $result->items());
    }

    public function test_it_can_be_filtered_to_several_types(): void
    {
        $result = $this->repository->findByEventId($this->eventId, QueryParamsDTO::fromArray([
            'filter_fields' => ['type' => ['in' => 'TOPUP_ONLINE,TOPUP_STAFF']],
        ]));

        $this->assertCount(2, $result->items());
    }

    public function test_it_can_be_searched_by_attendee_name(): void
    {
        $this->assertCount(2, $this->walletIdsFor(['query' => 'durand']));
        $this->assertSame([$this->paulWalletId], $this->walletIdsFor(['query' => 'Paul']));
    }

    public function test_a_search_with_no_match_returns_nothing(): void
    {
        $this->assertSame([], $this->walletIdsFor(['query' => 'nobody-here']));
    }

    private function walletIdsFor(array $params): array
    {
        return collect($this->repository->findByEventId($this->eventId, QueryParamsDTO::fromArray($params))->items())
            ->map(fn ($transaction) => $transaction->getCashlessWalletId())
            ->all();
    }

    private function makeWallet(int $orderId, int $productId, int $priceId, string $first, string $last): int
    {
        $now = now()->toDateTimeString();

        $attendeeId = DB::table('attendees')->insertGetId([
            'event_id' => $this->eventId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_price_id' => $priceId,
            'status' => 'ACTIVE',
            'email' => strtolower($first).'@example.test',
            'first_name' => $first,
            'last_name' => $last,
            'public_id' => 'A-'.strtoupper(substr(uniqid(), -7)),
            'short_id' => 'a_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('cashless_wallets')->insertGetId([
            'event_id' => $this->eventId,
            'attendee_id' => $attendeeId,
            'currency' => 'USD',
            'status' => CashlessWalletStatus::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
