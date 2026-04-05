<?php

namespace HiEvents\Jobs\Order;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderCancelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CancelExpiredFreeOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        EventSettingsRepositoryInterface $eventSettingsRepository,
        OrderRepositoryInterface         $orderRepository,
        AttendeeRepositoryInterface      $attendeeRepository,
        OrderCancelService               $orderCancelService,
    ): void
    {
        $eventSettings = $eventSettingsRepository->findWhere([
            [EventSettingDomainObjectAbstract::FREE_TICKET_EXPIRATION_MINUTES, '!=', null],
        ]);

        foreach ($eventSettings as $settings) {
            /** @var EventSettingDomainObject $settings */
            $expirationMinutes = $settings->getFreeTicketExpirationMinutes();
            $cutoff = Carbon::now()->subMinutes($expirationMinutes);

            $expiredOrders = $orderRepository->findWhere([
                OrderDomainObjectAbstract::EVENT_ID => $settings->getEventId(),
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::NO_PAYMENT_REQUIRED->name,
                [OrderDomainObjectAbstract::CREATED_AT, '<=', $cutoff->toDateTimeString()],
            ]);

            foreach ($expiredOrders as $order) {
                /** @var OrderDomainObject $order */
                try {
                    $hasCheckedInAttendee = $attendeeRepository->findWhere([
                        'order_id' => $order->getId(),
                        'status' => AttendeeStatus::ACTIVE->name,
                        ['checked_in_at', '!=', null],
                    ])->isNotEmpty();

                    if ($hasCheckedInAttendee) {
                        continue;
                    }

                    $orderCancelService->cancelOrder($order);

                    Log::info('Cancelled expired free order', [
                        'order_id' => $order->getId(),
                        'event_id' => $order->getEventId(),
                        'expiration_minutes' => $expirationMinutes,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Failed to cancel expired free order', [
                        'order_id' => $order->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
