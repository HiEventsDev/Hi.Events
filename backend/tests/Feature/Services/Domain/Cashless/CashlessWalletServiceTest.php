<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Cashless;

use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Models\User;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessTransactionItemDTO;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashlessWalletServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CashlessWalletService $service;

    private int $eventId;

    private int $attendeeId;

    private int $walletId;

    private int $productId;

    private int $productPriceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CashlessWalletService::class);

        $now = now()->toDateTimeString();
        $user = User::factory()->withAccount()->create();
        $accountId = $user->accounts()->first()->id;

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $accountId,
            'name' => 'Cashless Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Cashless Event',
            'account_id' => $accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productId = DB::table('products')->insertGetId([
            'title' => 'Beer',
            'event_id' => $this->eventId,
            'product_type' => 'GENERAL',
            'order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->productPriceId = DB::table('product_prices')->insertGetId([
            'product_id' => $this->productId,
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

        $this->attendeeId = DB::table('attendees')->insertGetId([
            'event_id' => $this->eventId,
            'order_id' => $orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'status' => 'ACTIVE',
            'email' => 'attendee@example.test',
            'first_name' => 'Marie',
            'last_name' => 'Durand',
            'public_id' => 'A-'.strtoupper(uniqid()),
            'short_id' => 'a_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->walletId = DB::table('cashless_wallets')->insertGetId([
            'event_id' => $this->eventId,
            'attendee_id' => $this->attendeeId,
            'currency' => 'USD',
            'status' => CashlessWalletStatus::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_a_full_lifecycle_leaves_the_ledger_and_the_balance_in_agreement(): void
    {
        $this->topUp(50.00);
        $purchase = $this->purchase(12.50);
        $this->service->reverse($purchase, reversedByUserId: null);
        $this->purchase(20.00);
        $this->refundRemaining(30.00);

        $wallet = DB::table('cashless_wallets')->find($this->walletId);
        $ledgerSum = (float) DB::table('cashless_transactions')
            ->where('cashless_wallet_id', $this->walletId)
            ->sum('amount');

        $this->assertSame(0.0, (float) $wallet->balance);
        $this->assertSame((float) $wallet->balance, $ledgerSum);
        $this->assertSame(50.0, (float) $wallet->total_topped_up);
        $this->assertSame(20.0, (float) $wallet->total_spent);
        $this->assertSame(30.0, (float) $wallet->total_refunded);
    }

    public function test_every_transaction_records_the_balance_it_left_behind(): void
    {
        $this->topUp(40.00);
        $this->purchase(15.00);

        $balancesAfter = DB::table('cashless_transactions')
            ->where('cashless_wallet_id', $this->walletId)
            ->orderBy('id')
            ->pluck('balance_after')
            ->map(static fn ($value) => (float) $value)
            ->all();

        $this->assertSame([40.0, 25.0], $balancesAfter);
    }

    public function test_a_purchase_beyond_the_balance_is_refused_and_writes_nothing(): void
    {
        $this->topUp(10.00);

        $refused = false;

        try {
            $this->purchase(10.01);
        } catch (InsufficientCashlessBalanceException) {
            $refused = true;
        }

        $this->assertTrue($refused);

        $this->assertSame(10.0, (float) DB::table('cashless_wallets')->find($this->walletId)->balance);
        $this->assertSame(1, DB::table('cashless_transactions')->where('cashless_wallet_id', $this->walletId)->count());
    }

    public function test_purchase_items_are_stored_against_the_transaction(): void
    {
        $this->topUp(30.00);
        $transaction = $this->purchase(10.00);

        $items = DB::table('cashless_transaction_items')
            ->where('cashless_transaction_id', $transaction->getId())
            ->get();

        $this->assertCount(1, $items);
        $this->assertSame('Beer', $items->first()->product_title);
        $this->assertSame(2, $items->first()->quantity);
    }

    public function test_a_frozen_wallet_refuses_new_transactions(): void
    {
        $this->topUp(25.00);

        DB::table('cashless_wallets')
            ->where('id', $this->walletId)
            ->update(['status' => CashlessWalletStatus::FROZEN->value]);

        $this->expectException(CashlessWalletUnavailableException::class);

        $this->purchase(5.00);
    }

    public function test_a_transaction_can_only_be_reversed_once(): void
    {
        $this->topUp(30.00);
        $purchase = $this->purchase(5.00);

        $this->service->reverse($purchase, reversedByUserId: null);

        $this->expectException(CashlessTransactionNotReversibleException::class);

        $this->service->reverse($purchase, reversedByUserId: null);
    }

    public function test_an_attendee_cannot_hold_two_wallets(): void
    {
        $this->expectException(QueryException::class);

        DB::table('cashless_wallets')->insert([
            'event_id' => $this->eventId,
            'attendee_id' => $this->attendeeId,
            'currency' => 'USD',
            'status' => CashlessWalletStatus::ACTIVE->value,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    private function topUp(float $amount): void
    {
        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: $this->walletId,
            type: CashlessTransactionType::TOPUP_STAFF,
            positive_amount: $amount,
            staff_payment_method: CashlessStaffPaymentMethod::CASH,
        ));
    }

    private function purchase(float $amount)
    {
        return $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: $this->walletId,
            type: CashlessTransactionType::PURCHASE,
            positive_amount: $amount,
            items: new Collection([
                new CashlessTransactionItemDTO(
                    product_id: $this->productId,
                    product_price_id: $this->productPriceId,
                    product_title: 'Beer',
                    unit_price: $amount / 2,
                    quantity: 2,
                    total: $amount,
                ),
            ]),
        ));
    }

    private function refundRemaining(float $amount): void
    {
        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: $this->walletId,
            type: CashlessTransactionType::REFUND_REMAINING,
            positive_amount: $amount,
        ));
    }
}
