<?php

namespace Tests\Unit\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\Exceptions\CashlessClosureNotAllowedException;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessClosureService;
use HiEvents\Services\Domain\Cashless\CashlessOccurrenceResolver;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCashlessClosureService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CashlessClosureServiceTest extends TestCase
{
    private CashlessSettingsService|MockInterface $settingsService;

    private CashlessWalletRepositoryInterface|MockInterface $walletRepository;

    private CashlessWalletService|MockInterface $walletService;

    private CashlessOccurrenceResolver|MockInterface $occurrenceResolver;

    private EventStatisticsCashlessClosureService|MockInterface $statisticsService;

    private EventSettingsRepositoryInterface|MockInterface $eventSettingsRepository;

    private CashlessClosureService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsService = Mockery::mock(CashlessSettingsService::class);
        $this->walletRepository = Mockery::mock(CashlessWalletRepositoryInterface::class);
        $this->walletService = Mockery::mock(CashlessWalletService::class);
        $this->occurrenceResolver = Mockery::mock(CashlessOccurrenceResolver::class);
        $this->statisticsService = Mockery::mock(EventStatisticsCashlessClosureService::class);
        $this->eventSettingsRepository = Mockery::mock(EventSettingsRepositoryInterface::class);

        $databaseManager = Mockery::mock(DatabaseManager::class);
        $databaseManager->shouldReceive('transaction')->andReturnUsing(static fn (callable $callback) => $callback());

        $this->service = new CashlessClosureService(
            $this->settingsService,
            $this->walletRepository,
            $this->walletService,
            $this->occurrenceResolver,
            $this->statisticsService,
            $this->eventSettingsRepository,
            $databaseManager,
        );
    }

    public function test_it_refuses_when_cashless_is_not_enabled(): void
    {
        $this->settingsService->shouldReceive('getEnabledSettings')->andThrow(new CashlessNotEnabledException('off'));
        $this->walletService->shouldNotReceive('close');

        $this->expectException(CashlessNotEnabledException::class);

        $this->service->close(7, 1);
    }

    public function test_it_refuses_when_already_closed(): void
    {
        $this->settingsService->shouldReceive('getEnabledSettings')
            ->andReturn((new EventSettingDomainObject)->setCashlessClosedAt('2026-09-28 10:00:00'));
        $this->walletService->shouldNotReceive('close');

        $this->expectException(CashlessClosureNotAllowedException::class);

        $this->service->close(7, 1);
    }

    public function test_it_refuses_while_balance_refunds_are_open(): void
    {
        $settings = new EventSettingDomainObject;
        $this->settingsService->shouldReceive('getEnabledSettings')->andReturn($settings);
        $this->settingsService->shouldReceive('isRefundWindowOpen')->with($settings)->andReturn(true);
        $this->walletService->shouldNotReceive('close');
        $this->eventSettingsRepository->shouldNotReceive('updateWhere');

        $this->expectException(CashlessClosureNotAllowedException::class);

        $this->service->close(7, 1);
    }

    public function test_it_closes_every_wallet_with_a_balance_and_records_the_total_as_sales(): void
    {
        $settings = new EventSettingDomainObject;
        $this->settingsService->shouldReceive('getEnabledSettings')->andReturn($settings);
        $this->settingsService->shouldReceive('isRefundWindowOpen')->andReturn(false);

        $this->walletRepository->shouldReceive('findWhere')->andReturn(new Collection([
            (new CashlessWalletDomainObject)->setId(1),
            (new CashlessWalletDomainObject)->setId(2),
        ]));
        $this->walletService->shouldReceive('close')->with(1, 9)->once()
            ->andReturn((new CashlessTransactionDomainObject)->setAmount(-10.25));
        $this->walletService->shouldReceive('close')->with(2, 9)->once()
            ->andReturn((new CashlessTransactionDomainObject)->setAmount(-5.50));

        $this->walletRepository->shouldReceive('updateWhere')->once()->withArgs(
            fn (array $attributes, array $where) => $attributes['status'] === 'CLOSED' && $where['event_id'] === 7
        );
        $this->occurrenceResolver->shouldReceive('resolveForSale')->with(7)->andReturn(3);
        $this->statisticsService->shouldReceive('recordClosedBalance')->once()->withArgs(
            fn (int $eventId, ?int $occurrenceId, string $date, float $amount) => $eventId === 7
                && $occurrenceId === 3
                && $amount === 15.75
        );
        $this->eventSettingsRepository->shouldReceive('updateWhere')->once()->withArgs(
            fn (array $attributes, array $where) => isset($attributes['cashless_closed_at']) && $where['event_id'] === 7
        );

        $result = $this->service->close(7, 9);

        $this->assertSame(2, $result->wallets_closed);
        $this->assertSame(15.75, $result->amount_closed);
    }

    public function test_it_records_no_sales_when_there_is_nothing_left_to_close(): void
    {
        $this->settingsService->shouldReceive('getEnabledSettings')->andReturn(new EventSettingDomainObject);
        $this->settingsService->shouldReceive('isRefundWindowOpen')->andReturn(false);
        $this->walletRepository->shouldReceive('findWhere')->andReturn(new Collection);
        $this->walletRepository->shouldReceive('updateWhere')->once();
        $this->statisticsService->shouldNotReceive('recordClosedBalance');
        $this->eventSettingsRepository->shouldReceive('updateWhere')->once();

        $result = $this->service->close(7, 9);

        $this->assertSame(0, $result->wallets_closed);
        $this->assertSame(0.0, $result->amount_closed);
    }
}
