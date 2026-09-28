<?php

namespace Tests\Unit\Services\Application\Handlers\Cashless;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessSalesPointTransactionsPublicHandler;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GetCashlessSalesPointTransactionsPublicHandlerTest extends TestCase
{
    private CashlessSalesPointAccessService|MockInterface $accessService;

    private CashlessTransactionRepositoryInterface|MockInterface $transactionRepository;

    private GetCashlessSalesPointTransactionsPublicHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accessService = Mockery::mock(CashlessSalesPointAccessService::class);
        $this->transactionRepository = Mockery::mock(CashlessTransactionRepositoryInterface::class);
        $this->handler = new GetCashlessSalesPointTransactionsPublicHandler(
            $this->accessService,
            $this->transactionRepository,
        );
    }

    public function test_it_returns_the_transactions_of_the_authorised_sales_point(): void
    {
        $transactions = new Collection([new CashlessTransactionDomainObject]);

        $this->accessService->shouldReceive('resolveAuthorised')
            ->with('csp_abc', 'token')
            ->andReturn((new CashlessSalesPointDomainObject)->setId(7));
        $this->transactionRepository->shouldReceive('loadRelation')->andReturnSelf();
        $this->transactionRepository->shouldReceive('findBySalesPointId')
            ->with(7, 200)
            ->once()
            ->andReturn($transactions);

        $this->assertSame($transactions, $this->handler->handle('csp_abc', 'token'));
    }

    public function test_it_refuses_an_unauthorised_session(): void
    {
        $this->accessService->shouldReceive('resolveAuthorised')
            ->andThrow(new CashlessSalesPointAccessException('nope'));
        $this->transactionRepository->shouldNotReceive('findBySalesPointId');

        $this->expectException(CashlessSalesPointAccessException::class);

        $this->handler->handle('csp_abc', null);
    }
}
