<?php

namespace Tests\Unit\Services\Domain\EventStatistics;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessTopupOrderChecker;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCancellationService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsIncrementService;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class EventStatisticsTopupOrderTest extends TestCase
{
    public function test_increment_ignores_cashless_topup_orders(): void
    {
        $service = new EventStatisticsIncrementService(
            Mockery::mock(PromoCodeRepositoryInterface::class),
            Mockery::mock(ProductRepositoryInterface::class),
            Mockery::mock(EventStatisticRepositoryInterface::class),
            Mockery::mock(EventDailyStatisticRepositoryInterface::class),
            Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class),
            Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class),
            Mockery::mock(DatabaseManager::class),
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(LoggerInterface::class),
            Mockery::mock(Retrier::class),
            Mockery::mock(CashlessTopupOrderChecker::class, ['isTopupOrder' => true]),
        );

        $order = (new OrderDomainObject)->setId(42);

        $service->incrementForOrder($order);

        $this->addToAssertionCount(1);
    }

    public function test_refund_ignores_cashless_topup_orders(): void
    {
        $service = new EventStatisticsRefundService(
            Mockery::mock(EventStatisticRepositoryInterface::class),
            Mockery::mock(EventDailyStatisticRepositoryInterface::class),
            Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class),
            Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class),
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(LoggerInterface::class),
            Mockery::mock(CashlessTopupOrderChecker::class, ['isTopupOrder' => true]),
        );

        $order = (new OrderDomainObject)->setId(42);

        $service->updateForRefund($order, MoneyValue::fromFloat(10.0, 'USD'));

        $this->addToAssertionCount(1);
    }

    public function test_cancellation_ignores_cashless_topup_orders(): void
    {
        $service = new EventStatisticsCancellationService(
            Mockery::mock(EventStatisticRepositoryInterface::class),
            Mockery::mock(EventDailyStatisticRepositoryInterface::class),
            Mockery::mock(EventOccurrenceStatisticRepositoryInterface::class),
            Mockery::mock(EventOccurrenceDailyStatisticRepositoryInterface::class),
            Mockery::mock(AttendeeRepositoryInterface::class),
            Mockery::mock(OrderRepositoryInterface::class),
            Mockery::mock(LoggerInterface::class),
            Mockery::mock(DatabaseManager::class),
            Mockery::mock(Retrier::class),
            Mockery::mock(PromoCodeRepositoryInterface::class),
            Mockery::mock(ProductRepositoryInterface::class),
            Mockery::mock(AffiliateRepositoryInterface::class),
            Mockery::mock(CashlessTopupOrderChecker::class, ['isTopupOrder' => true]),
        );

        $order = (new OrderDomainObject)->setId(42);

        $service->decrementForCancelledOrder($order);

        $this->addToAssertionCount(1);
    }
}
