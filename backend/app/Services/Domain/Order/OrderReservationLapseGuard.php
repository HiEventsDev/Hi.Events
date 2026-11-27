<?php

namespace HiEvents\Services\Domain\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;

class OrderReservationLapseGuard
{
    public const LAPSE_WINDOW_SECONDS = 120;

    public function __construct(
        private readonly TransactionLockService $transactionLockService,
    ) {}

    /**
     * @throws ResourceConflictException
     */
    public function assertReservationHeldUnderEventLock(OrderDomainObject $order): void
    {
        $reservedUntil = new Carbon($order->getReservedUntil());

        if ($reservedUntil->isAfter(Carbon::now()->addSeconds(self::LAPSE_WINDOW_SECONDS))) {
            return;
        }

        $this->transactionLockService->lockEvent($order->getEventId());

        if ($reservedUntil->isPast()) {
            throw new ResourceConflictException(__('This order has expired'));
        }
    }
}
