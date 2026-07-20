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
        if (config('queue.occurrences_queue_name') !== null) {
            $this->onQueue(config('queue.occurrences_queue_name'));
        }
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
