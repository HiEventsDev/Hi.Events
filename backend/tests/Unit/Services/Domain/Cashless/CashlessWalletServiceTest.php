<?php

namespace Tests\Unit\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Repository\Interfaces\CashlessTransactionItemRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\CashlessTransactionItemDTO;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CashlessWalletServiceTest extends TestCase
{
    private CashlessWalletRepositoryInterface|MockInterface $walletRepository;

    private CashlessTransactionRepositoryInterface|MockInterface $transactionRepository;

    private CashlessTransactionItemRepositoryInterface|MockInterface $transactionItemRepository;

    private CashlessWalletService $service;

    private array $capturedWalletUpdate = [];

    private array $walletUpdates = [];

    private array $capturedTransaction = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletRepository = Mockery::mock(CashlessWalletRepositoryInterface::class);
        $this->transactionRepository = Mockery::mock(CashlessTransactionRepositoryInterface::class);
        $this->transactionItemRepository = Mockery::mock(CashlessTransactionItemRepositoryInterface::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(static fn (callable $callback) => $callback());

        $this->service = new CashlessWalletService(
            $this->walletRepository,
            $this->transactionRepository,
            $this->transactionItemRepository,
            $databaseManager,
        );
    }

    public function test_online_topup_credits_the_balance_and_tracks_the_total(): void
    {
        $this->givenWallet(balance: 10.00, toppedUp: 10.00);
        $this->expectTransactionCreated();

        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: 1,
            type: CashlessTransactionType::TOPUP_ONLINE,
            positive_amount: 25.00,
            order_id: 99,
        ));

        $this->assertSame(35.00, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::BALANCE]);
        $this->assertSame(35.00, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::TOTAL_TOPPED_UP]);
        $this->assertSame(0.0, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::TOTAL_SPENT]);
        $this->assertSame(25.00, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::AMOUNT]);
        $this->assertSame(35.00, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::BALANCE_AFTER]);
    }

    public function test_staff_topup_records_the_payment_method(): void
    {
        $this->givenWallet(balance: 0.00);
        $this->expectTransactionCreated();

        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: 1,
            type: CashlessTransactionType::TOPUP_STAFF,
            positive_amount: 20.00,
            sales_point_id: 7,
            created_by_user_id: 3,
            staff_payment_method: CashlessStaffPaymentMethod::CASH,
        ));

        $this->assertSame(
            CashlessStaffPaymentMethod::CASH->value,
            $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::STAFF_PAYMENT_METHOD],
        );
        $this->assertSame(20.00, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::BALANCE]);
    }

    public function test_purchase_debits_the_balance_and_stores_the_items(): void
    {
        $this->givenWallet(balance: 30.00, toppedUp: 30.00);
        $this->expectTransactionCreated();

        $this->transactionItemRepository
            ->shouldReceive('insert')
            ->once()
            ->withArgs(function (array $rows) {
                $this->assertCount(1, $rows);
                $this->assertSame('Beer', $rows[0]['product_title']);

                return true;
            })
            ->andReturnTrue();

        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: 1,
            type: CashlessTransactionType::PURCHASE,
            positive_amount: 12.50,
            sales_point_id: 7,
            items: new Collection([
                new CashlessTransactionItemDTO(
                    product_id: 4,
                    product_price_id: 9,
                    product_title: 'Beer',
                    unit_price: 6.25,
                    quantity: 2,
                    total: 12.50,
                ),
            ]),
        ));

        $this->assertSame(17.50, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::BALANCE]);
        $this->assertSame(12.50, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::TOTAL_SPENT]);
        $this->assertSame(-12.50, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::AMOUNT]);
    }

    public function test_purchase_is_rejected_when_the_balance_is_too_low(): void
    {
        $this->givenWallet(balance: 5.00);

        $this->expectException(InsufficientCashlessBalanceException::class);

        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: 1,
            type: CashlessTransactionType::PURCHASE,
            positive_amount: 5.01,
        ));
    }

    public function test_a_frozen_wallet_cannot_be_used(): void
    {
        $this->givenWallet(balance: 50.00, status: CashlessWalletStatus::FROZEN);

        $this->expectException(CashlessWalletUnavailableException::class);

        $this->service->record(new RecordCashlessTransactionDTO(
            wallet_id: 1,
            type: CashlessTransactionType::PURCHASE,
            positive_amount: 5.00,
        ));
    }

    public function test_reversing_a_purchase_gives_the_money_back_and_undoes_the_spend_total(): void
    {
        $this->givenWallet(balance: 17.50, toppedUp: 30.00, spent: 12.50);
        $this->expectTransactionCreated();

        $this->transactionRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturnNull();

        $this->service->reverse($this->purchaseTransaction(), reversedByUserId: 3);

        $this->assertSame(30.00, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::BALANCE]);
        $this->assertSame(0.0, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::TOTAL_SPENT]);
        $this->assertSame(12.50, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::AMOUNT]);
        $this->assertSame(
            CashlessTransactionType::REVERSAL->value,
            $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::TYPE],
        );
    }

    public function test_a_transaction_cannot_be_reversed_twice(): void
    {
        $this->transactionRepository
            ->shouldReceive('findFirstWhere')
            ->once()
            ->andReturn(Mockery::mock(CashlessTransactionDomainObject::class));

        $this->expectException(CashlessTransactionNotReversibleException::class);

        $this->service->reverse($this->purchaseTransaction(), reversedByUserId: 3);
    }

    public function test_a_reversal_cannot_itself_be_reversed(): void
    {
        $reversal = (new CashlessTransactionDomainObject)
            ->setId(88)
            ->setCashlessWalletId(1)
            ->setType(CashlessTransactionType::REVERSAL->value)
            ->setAmount(12.50);

        $this->expectException(CashlessTransactionNotReversibleException::class);

        $this->service->reverse($reversal, reversedByUserId: 3);
    }

    public function test_closing_a_wallet_writes_a_closure_for_the_whole_balance_and_closes_it(): void
    {
        $this->givenWallet(balance: 12.50, toppedUp: 30.00, spent: 17.50);
        $this->expectTransactionCreated();

        $transaction = $this->service->close(walletId: 1, closedByUserId: 9);

        $this->assertNotNull($transaction);
        $this->assertSame(CashlessTransactionType::CLOSURE->value, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::TYPE]);
        $this->assertSame(-12.50, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::AMOUNT]);
        $this->assertSame(0.0, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::BALANCE_AFTER]);
        $this->assertSame(9, $this->capturedTransaction[CashlessTransactionDomainObjectAbstract::CREATED_BY_USER_ID]);
        $this->assertSame(CashlessWalletStatus::CLOSED->value, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::STATUS]);
    }

    public function test_closing_leaves_the_topped_up_spent_and_refunded_totals_alone(): void
    {
        $this->givenWallet(balance: 12.50, toppedUp: 30.00, spent: 17.50, refunded: 0.0);
        $this->expectTransactionCreated();
        $this->service->close(walletId: 1, closedByUserId: null);

        $writeUpdate = $this->walletUpdates[0];
        $this->assertSame(30.00, $writeUpdate[CashlessWalletDomainObjectAbstract::TOTAL_TOPPED_UP]);
        $this->assertSame(17.50, $writeUpdate[CashlessWalletDomainObjectAbstract::TOTAL_SPENT]);
        $this->assertSame(0.0, $writeUpdate[CashlessWalletDomainObjectAbstract::TOTAL_REFUNDED]);
    }

    public function test_closing_an_empty_wallet_writes_no_transaction(): void
    {
        $this->givenWallet(balance: 0.00);
        $this->transactionRepository->shouldNotReceive('create');

        $transaction = $this->service->close(walletId: 1, closedByUserId: null);

        $this->assertNull($transaction);
        $this->assertSame(CashlessWalletStatus::CLOSED->value, $this->capturedWalletUpdate[CashlessWalletDomainObjectAbstract::STATUS]);
    }

    public function test_a_frozen_wallet_can_still_be_closed(): void
    {
        $this->givenWallet(balance: 8.00, status: CashlessWalletStatus::FROZEN);
        $this->expectTransactionCreated();

        $this->assertNotNull($this->service->close(walletId: 1, closedByUserId: null));
    }

    public function test_a_closure_cannot_be_reversed(): void
    {
        $closure = (new CashlessTransactionDomainObject)
            ->setId(90)
            ->setCashlessWalletId(1)
            ->setType(CashlessTransactionType::CLOSURE->value)
            ->setAmount(-12.50);

        $this->expectException(CashlessTransactionNotReversibleException::class);

        $this->service->reverse($closure, reversedByUserId: 3);
    }

    private function purchaseTransaction(): CashlessTransactionDomainObject
    {
        return (new CashlessTransactionDomainObject)
            ->setId(55)
            ->setCashlessWalletId(1)
            ->setCashlessSalesPointId(7)
            ->setType(CashlessTransactionType::PURCHASE->value)
            ->setAmount(-12.50);
    }

    private function givenWallet(
        float $balance,
        float $toppedUp = 0.0,
        float $spent = 0.0,
        float $refunded = 0.0,
        CashlessWalletStatus $status = CashlessWalletStatus::ACTIVE,
    ): void {
        $wallet = (new CashlessWalletDomainObject)
            ->setId(1)
            ->setEventId(2)
            ->setAttendeeId(3)
            ->setCurrency('EUR')
            ->setBalance($balance)
            ->setTotalToppedUp($toppedUp)
            ->setTotalSpent($spent)
            ->setTotalRefunded($refunded)
            ->setStatus($status->value);

        $this->walletRepository->shouldReceive('lockById')->with(1)->andReturn($wallet);

        $this->walletRepository
            ->shouldReceive('updateFromArray')
            ->andReturnUsing(function (int $id, array $attributes) use ($wallet) {
                $this->capturedWalletUpdate = $attributes;
                $this->walletUpdates[] = $attributes;

                return $wallet;
            });
    }

    private function expectTransactionCreated(): void
    {
        $this->transactionRepository
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $attributes) {
                $this->capturedTransaction = $attributes;

                return (new CashlessTransactionDomainObject)
                    ->setId(1000)
                    ->setCashlessWalletId(1)
                    ->setType($attributes[CashlessTransactionDomainObjectAbstract::TYPE])
                    ->setAmount($attributes[CashlessTransactionDomainObjectAbstract::AMOUNT]);
            });
    }
}
