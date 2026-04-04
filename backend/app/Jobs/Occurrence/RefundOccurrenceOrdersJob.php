<?php

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefundOccurrenceOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $eventId,
        public readonly int $occurrenceId,
    ) {
    }

    public function handle(RefundOrderHandler $refundHandler): void
    {
        $orderIds = DB::table('order_items')
            ->where(OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID, $this->occurrenceId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return;
        }

        $refundableOrders = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->where('status', OrderStatus::COMPLETED->name)
            ->where('payment_status', OrderPaymentStatus::PAYMENT_RECEIVED->name)
            ->get(['id', 'total_gross', 'currency']);

        if ($refundableOrders->isEmpty()) {
            return;
        }

        $multiOccurrenceOrderIds = DB::table('order_items')
            ->whereIn('order_id', $refundableOrders->pluck('id'))
            ->whereNull('deleted_at')
            ->select('order_id')
            ->selectRaw('COUNT(DISTINCT event_occurrence_id) as occurrence_count')
            ->groupBy('order_id')
            ->havingRaw('COUNT(DISTINCT event_occurrence_id) > 1')
            ->pluck('order_id')
            ->toArray();

        foreach ($refundableOrders as $order) {
            if (in_array($order->id, $multiOccurrenceOrderIds, true)) {
                Log::warning('Skipping automatic refund for order spanning multiple occurrences', [
                    'order_id' => $order->id,
                    'event_id' => $this->eventId,
                    'cancelled_occurrence_id' => $this->occurrenceId,
                ]);
                continue;
            }

            try {
                $refundHandler->handle(new RefundOrderDTO(
                    event_id: $this->eventId,
                    order_id: $order->id,
                    amount: (float) $order->total_gross,
                    notify_buyer: true,
                    cancel_order: true,
                ));
            } catch (Throwable $e) {
                Log::error('Failed to refund order for cancelled occurrence', [
                    'order_id' => $order->id,
                    'event_id' => $this->eventId,
                    'occurrence_id' => $this->occurrenceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
