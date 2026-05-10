<?php

namespace HiEvents\Jobs\Occurrence;

use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Generated\OrderItemDomainObjectAbstract;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Repository\Interfaces\OrderAuditLogRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\Payment\Stripe\RefundOrderHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefundOccurrenceOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $eventId,
        public readonly int $occurrenceId,
    ) {
        $this->onQueue('occurrences');
    }

    public function uniqueId(): string
    {
        return "occurrence:{$this->occurrenceId}";
    }

    public function handle(
        RefundOrderHandler $refundHandler,
        OrderAuditLogRepositoryInterface $auditLogRepository,
    ): void {
        $orderIds = DB::table('order_items')
            ->where(OrderItemDomainObjectAbstract::EVENT_OCCURRENCE_ID, $this->occurrenceId)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return;
        }

        // Only refund orders that haven't already had a refund started. Skipping rows where
        // refund_status is set guards against duplicate Stripe refunds on job retry when a
        // previous attempt crashed between the Stripe API call and the refund_status write.
        $refundableOrders = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->where('status', OrderStatus::COMPLETED->name)
            ->where('payment_status', OrderPaymentStatus::PAYMENT_RECEIVED->name)
            ->whereNull('refund_status')
            ->get(['id', 'total_gross', 'currency']);

        if ($refundableOrders->isEmpty()) {
            return;
        }

        $multiOccurrenceOrderIds = DB::table('order_items')
            ->whereIn('order_id', $refundableOrders->pluck('id'))
            ->whereNull('deleted_at')
            ->select('order_id')
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

                // Surface the skip on the order's audit log so admins see it in
                // the order's history and can issue a manual partial refund.
                // Wrapped: audit-log failure shouldn't derail the rest of the
                // batch (other orders are still queued for refund/skip below).
                try {
                    $auditLogRepository->create([
                        'event_id' => $this->eventId,
                        'order_id' => $order->id,
                        'attendee_id' => null,
                        'action' => OrderAuditAction::AUTOMATIC_REFUND_SKIPPED->value,
                        'old_values' => null,
                        'new_values' => [
                            'cancelled_occurrence_id' => $this->occurrenceId,
                            'reason' => 'order spans multiple occurrences',
                        ],
                        'changed_fields' => null,
                        'ip_address' => null,
                        'user_agent' => null,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Failed to write refund-skipped audit log entry', [
                        'order_id' => $order->id,
                        'event_id' => $this->eventId,
                        'error' => $e->getMessage(),
                    ]);
                }

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

                try {
                    $auditLogRepository->create([
                        'event_id' => $this->eventId,
                        'order_id' => $order->id,
                        'attendee_id' => null,
                        'action' => OrderAuditAction::AUTOMATIC_REFUND_FAILED->value,
                        'old_values' => null,
                        'new_values' => [
                            'cancelled_occurrence_id' => $this->occurrenceId,
                            'error' => $e->getMessage(),
                        ],
                        'changed_fields' => null,
                        'ip_address' => null,
                        'user_agent' => null,
                    ]);
                } catch (Throwable $auditError) {
                    Log::error('Failed to write refund-failed audit log entry', [
                        'order_id' => $order->id,
                        'event_id' => $this->eventId,
                        'error' => $auditError->getMessage(),
                    ]);
                }
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('RefundOccurrenceOrdersJob permanently failed after retries', [
            'event_id' => $this->eventId,
            'occurrence_id' => $this->occurrenceId,
            'error' => $exception->getMessage(),
        ]);
    }
}
