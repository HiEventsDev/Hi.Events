<?php

namespace Tests\Unit\Services\Application\Handlers\Cashless;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\Public\GetCashlessWalletForSalesPointHandler;
use HiEvents\Services\Domain\Cashless\CashlessAttendeeNameMasker;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class GetCashlessWalletForSalesPointHandlerTest extends TestCase
{
    public function test_a_sales_point_that_cannot_top_up_only_sees_the_last_name_initial(): void
    {
        $wallet = $this->lookUpWith(allowStaffTopups: false);

        $this->assertSame('Marie', $wallet->getAttendee()->getFirstName());
        $this->assertSame('D.', $wallet->getAttendee()->getLastName());
    }

    public function test_a_sales_point_that_can_top_up_sees_the_full_name(): void
    {
        $wallet = $this->lookUpWith(allowStaffTopups: true);

        $this->assertSame('Durand', $wallet->getAttendee()->getLastName());
    }

    private function lookUpWith(bool $allowStaffTopups): CashlessWalletDomainObject
    {
        $accessService = Mockery::mock(CashlessSalesPointAccessService::class);
        $accessService->shouldReceive('resolveAuthorised')->andReturn(
            (new CashlessSalesPointDomainObject)->setEventId(1)->setAllowStaffTopups($allowStaffTopups)
        );

        $wallet = (new CashlessWalletDomainObject)
            ->setId(3)
            ->setAttendee((new AttendeeDomainObject)->setFirstName('Marie')->setLastName('Durand'));
        $resolver = Mockery::mock(CashlessWalletResolveService::class);
        $resolver->shouldReceive('resolveByAttendeePublicId')->andReturn($wallet);

        $transactions = Mockery::mock(CashlessTransactionRepositoryInterface::class);
        $transactions->shouldReceive('loadRelation')->andReturnSelf();
        $transactions->shouldReceive('findByWalletId')->andReturn(new Collection);

        return (new GetCashlessWalletForSalesPointHandler(
            $accessService,
            $resolver,
            $transactions,
            new CashlessAttendeeNameMasker,
        ))->handle('csp_x', 'A-ABC1234', 'token');
    }
}
