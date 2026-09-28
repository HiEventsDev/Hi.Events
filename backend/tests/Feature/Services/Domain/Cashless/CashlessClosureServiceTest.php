<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Domain\Cashless;

use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessClosureNotAllowedException;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Models\User;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessClosureService;
use HiEvents\Services\Domain\Cashless\CashlessSummaryService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessTransactionItemDTO;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashlessClosureServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CashlessClosureService $closureService;

    private CashlessWalletService $walletService;

    private int $eventId;

    private int $accountId;

    private int $userId;

    private int $productId;

    private int $productPriceId;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->closureService = $this->app->make(CashlessClosureService::class);
        $this->walletService = $this->app->make(CashlessWalletService::class);

        $now = now()->toDateTimeString();
        $user = User::factory()->withAccount()->create();
        $this->userId = $user->id;
        $this->accountId = $user->accounts()->first()->id;

        $organizerId = DB::table('organizers')->insertGetId([
            'account_id' => $this->accountId,
            'name' => 'Cashless Organizer',
            'email' => 'organizer@example.test',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->eventId = DB::table('events')->insertGetId([
            'title' => 'Cashless Event',
            'account_id' => $this->accountId,
            'user_id' => $user->id,
            'organizer_id' => $organizerId,
            'currency' => 'USD',
            'timezone' => 'UTC',
            'short_id' => 'evt_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('event_settings')->insert([
            'event_id' => $this->eventId,
            'cashless_enabled' => true,
            'cashless_allow_remaining_balance_refund' => false,
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

        $this->orderId = DB::table('orders')->insertGetId([
            'event_id' => $this->eventId,
            'short_id' => 'o_'.uniqid(),
            'public_id' => 'O-'.strtoupper(uniqid()),
            'status' => 'COMPLETED',
            'currency' => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_closing_moves_every_remaining_balance_into_the_sales_totals(): void
    {
        $first = $this->makeWalletWithBalance(10.00);
        $second = $this->makeWalletWithBalance(5.50);
        $empty = $this->makeWalletWithBalance(0.00);

        $result = $this->closureService->close($this->eventId, $this->userId);

        $this->assertSame(2, $result->wallets_closed);
        $this->assertSame(15.5, $result->amount_closed);

        foreach ([$first, $second, $empty] as $walletId) {
            $wallet = DB::table('cashless_wallets')->find($walletId);
            $this->assertSame(0.0, (float) $wallet->balance);
            $this->assertSame(CashlessWalletStatus::CLOSED->value, $wallet->status);
        }

        $statistics = DB::table('event_statistics')->where('event_id', $this->eventId)->first();
        $this->assertSame(15.5, (float) $statistics->sales_total_gross);
        $this->assertSame(15.5, (float) $statistics->sales_total_before_additions);
        $this->assertSame(0, (int) $statistics->orders_created);
        $this->assertSame(0, (int) $statistics->products_sold);

        $daily = DB::table('event_daily_statistics')->where('event_id', $this->eventId)->first();
        $this->assertSame(15.5, (float) $daily->sales_total_gross);

        $this->assertNotNull(DB::table('event_settings')->where('event_id', $this->eventId)->value('cashless_closed_at'));
    }

    public function test_closing_adds_to_existing_statistics_and_bumps_their_version(): void
    {
        DB::table('event_statistics')->insert([
            'event_id' => $this->eventId,
            'sales_total_gross' => 100.00,
            'sales_total_before_additions' => 90.00,
            'version' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeWalletWithBalance(20.00);

        $this->closureService->close($this->eventId, $this->userId);

        $statistics = DB::table('event_statistics')->where('event_id', $this->eventId)->first();
        $this->assertSame(120.0, (float) $statistics->sales_total_gross);
        $this->assertSame(110.0, (float) $statistics->sales_total_before_additions);
        $this->assertSame(4, (int) $statistics->version);
    }

    public function test_the_ledger_still_resums_to_zero_after_closing(): void
    {
        $walletId = $this->makeWalletWithBalance(30.00);

        $this->closureService->close($this->eventId, $this->userId);

        $closure = DB::table('cashless_transactions')
            ->where('cashless_wallet_id', $walletId)
            ->where('type', CashlessTransactionType::CLOSURE->value)
            ->first();

        $this->assertSame(-30.0, (float) $closure->amount);
        $this->assertSame(0.0, (float) $closure->balance_after);
        $this->assertSame($this->userId, (int) $closure->created_by_user_id);
        $this->assertSame(0.0, (float) DB::table('cashless_transactions')->where('cashless_wallet_id', $walletId)->sum('amount'));
    }

    public function test_cashless_can_only_be_closed_once(): void
    {
        $this->makeWalletWithBalance(10.00);
        $this->closureService->close($this->eventId, $this->userId);

        $this->expectException(CashlessClosureNotAllowedException::class);

        $this->closureService->close($this->eventId, $this->userId);
    }

    public function test_closing_is_refused_while_balance_refunds_are_still_open(): void
    {
        $walletId = $this->makeWalletWithBalance(10.00);
        DB::table('event_settings')->where('event_id', $this->eventId)->update([
            'cashless_allow_remaining_balance_refund' => true,
            'cashless_refund_deadline_at' => now()->addDay(),
        ]);

        $refused = false;

        try {
            $this->closureService->close($this->eventId, $this->userId);
        } catch (CashlessClosureNotAllowedException) {
            $refused = true;
        }

        $this->assertTrue($refused);

        $this->assertSame(10.0, (float) DB::table('cashless_wallets')->find($walletId)->balance);
        $this->assertNull(DB::table('event_settings')->where('event_id', $this->eventId)->value('cashless_closed_at'));
        $this->assertSame(0, DB::table('event_statistics')->where('event_id', $this->eventId)->count());
    }

    public function test_closing_is_allowed_once_the_refund_deadline_has_passed(): void
    {
        $this->makeWalletWithBalance(10.00);
        DB::table('event_settings')->where('event_id', $this->eventId)->update([
            'cashless_allow_remaining_balance_refund' => true,
            'cashless_refund_deadline_at' => now()->subDay(),
        ]);

        $result = $this->closureService->close($this->eventId, $this->userId);

        $this->assertSame(10.0, $result->amount_closed);
    }

    public function test_a_closed_wallet_refuses_new_transactions(): void
    {
        $walletId = $this->makeWalletWithBalance(10.00);
        $this->closureService->close($this->eventId, $this->userId);

        $this->expectException(CashlessWalletUnavailableException::class);

        $this->walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $walletId,
            type: CashlessTransactionType::TOPUP_STAFF,
            positive_amount: 5.00,
        ));
    }

    public function test_a_closure_cannot_be_reversed(): void
    {
        $walletId = $this->makeWalletWithBalance(10.00);
        $this->closureService->close($this->eventId, $this->userId);

        $closure = $this->app->make(CashlessTransactionRepositoryInterface::class)
            ->findFirstWhere([
                'cashless_wallet_id' => $walletId,
                'type' => CashlessTransactionType::CLOSURE->value,
            ]);

        $this->expectException(CashlessTransactionNotReversibleException::class);

        $this->walletService->reverse($closure, reversedByUserId: null);
    }

    public function test_a_wallet_created_after_the_closure_is_born_closed(): void
    {
        $this->closureService->close($this->eventId, $this->userId);
        $attendee = $this->makeAttendee();

        $wallet = $this->app->make(CashlessWalletResolveService::class)
            ->resolveByAttendeePublicId($this->eventId, $attendee['public_id']);

        $this->assertSame(CashlessWalletStatus::CLOSED->value, $wallet->getStatus());
    }

    public function test_the_summary_accounts_for_every_dollar(): void
    {
        $walletId = $this->makeWalletWithBalance(40.00);
        $this->walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $walletId,
            type: CashlessTransactionType::PURCHASE,
            positive_amount: 12.00,
            items: collect([new CashlessTransactionItemDTO(
                product_id: $this->productId,
                product_price_id: $this->productPriceId,
                product_title: 'Beer',
                unit_price: 4.00,
                quantity: 3,
                total: 12.00,
            )]),
        ));
        $this->makeWalletWithBalance(8.00);

        $this->closureService->close($this->eventId, $this->userId);
        $summary = $this->app->make(CashlessSummaryService::class)->getSummary($this->eventId);

        $this->assertSame(48.0, $summary->topped_up_online + $summary->topped_up_staff);
        $this->assertSame(12.0, $summary->spent);
        $this->assertSame(36.0, $summary->closed);
        $this->assertSame(0.0, $summary->outstanding_balance);
        $this->assertSame(1, $summary->purchases_count);
        $this->assertSame(2, $summary->wallets_total);
        $this->assertSame(2, $summary->wallets_closed);
        $this->assertSame('Beer', $summary->top_products[0]->title);
        $this->assertSame(3, $summary->top_products[0]->quantity);
    }

    private function makeAttendee(): array
    {
        $now = now()->toDateTimeString();
        $publicId = 'A-'.strtoupper(substr(uniqid(), -7));

        $attendeeId = DB::table('attendees')->insertGetId([
            'event_id' => $this->eventId,
            'order_id' => $this->orderId,
            'product_id' => $this->productId,
            'product_price_id' => $this->productPriceId,
            'status' => 'ACTIVE',
            'email' => uniqid().'@example.test',
            'first_name' => 'Marie',
            'last_name' => 'Durand',
            'public_id' => $publicId,
            'short_id' => 'a_'.uniqid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['id' => $attendeeId, 'public_id' => $publicId];
    }

    private function makeWalletWithBalance(float $balance): int
    {
        $now = now()->toDateTimeString();

        $walletId = DB::table('cashless_wallets')->insertGetId([
            'event_id' => $this->eventId,
            'attendee_id' => $this->makeAttendee()['id'],
            'currency' => 'USD',
            'status' => CashlessWalletStatus::ACTIVE->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($balance > 0) {
            $this->walletService->record(new RecordCashlessTransactionDTO(
                wallet_id: $walletId,
                type: CashlessTransactionType::TOPUP_ONLINE,
                positive_amount: $balance,
            ));
        }

        return $walletId;
    }
}
