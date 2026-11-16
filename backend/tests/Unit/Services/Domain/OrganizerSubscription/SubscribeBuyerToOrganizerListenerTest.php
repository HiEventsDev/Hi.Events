<?php

namespace Tests\Unit\Services\Domain\OrganizerSubscription;

use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Jobs\OrganizerSubscription\SubscribeCompletedOrderJob;
use HiEvents\Listeners\Order\SubscribeBuyerToOrganizerListener;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SubscribeBuyerToOrganizerListenerTest extends TestCase
{
    public function test_free_card_and_offline_completed_orders_schedule_confirmation_after_commit(): void
    {
        Bus::fake();
        foreach (['NO_PAYMENT_REQUIRED', 'PAYMENT_RECEIVED'] as $paymentStatus) {
            $order = (new OrderDomainObject)->setId(1)->setStatus('COMPLETED')->setPaymentStatus($paymentStatus)
                ->setNewEventSubscriptionRequestedAt('2026-11-16 12:00:00')->setOptedIntoMarketingAt('2026-11-16 12:00:00');
            (new SubscribeBuyerToOrganizerListener)->handle(new OrderStatusChangedEvent($order));
        }
        Bus::assertDispatchedTimes(SubscribeCompletedOrderJob::class, 2);
        Bus::assertDispatched(SubscribeCompletedOrderJob::class, fn ($job) => $job->orderId === 1 && $job->afterCommit === true);
    }

    public function test_pending_abandoned_and_historical_orders_never_schedule_subscriptions(): void
    {
        Bus::fake();
        foreach (['RESERVED', 'AWAITING_OFFLINE_PAYMENT', 'ABANDONED', 'CANCELLED'] as $status) {
            $order = (new OrderDomainObject)->setId(1)->setStatus($status)
                ->setNewEventSubscriptionRequestedAt('2026-11-16 12:00:00')->setOptedIntoMarketingAt('2026-11-16 12:00:00');
            (new SubscribeBuyerToOrganizerListener)->handle(new OrderStatusChangedEvent($order));
        }
        $historical = (new OrderDomainObject)->setId(1)->setStatus('COMPLETED')->setOptedIntoMarketingAt('2026-11-01 12:00:00');
        (new SubscribeBuyerToOrganizerListener)->handle(new OrderStatusChangedEvent($historical));
        Bus::assertNothingDispatched();
    }
}
