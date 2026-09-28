<?php

namespace Tests\Unit\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessTopupDomainObject;
use HiEvents\Repository\Interfaces\CashlessTopupRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessTopupOrderChecker;
use Mockery;
use Tests\TestCase;

class CashlessTopupOrderCheckerTest extends TestCase
{
    public function test_an_order_with_a_topup_row_is_a_topup_order(): void
    {
        $repository = Mockery::mock(CashlessTopupRepositoryInterface::class);
        $repository->shouldReceive('findFirstWhere')->with(['order_id' => 5])->andReturn(new CashlessTopupDomainObject);

        $this->assertTrue((new CashlessTopupOrderChecker($repository))->isTopupOrder(5));
    }

    public function test_an_order_without_a_topup_row_is_not_a_topup_order(): void
    {
        $repository = Mockery::mock(CashlessTopupRepositoryInterface::class);
        $repository->shouldReceive('findFirstWhere')->with(['order_id' => 6])->andReturn(null);

        $this->assertFalse((new CashlessTopupOrderChecker($repository))->isTopupOrder(6));
    }
}
