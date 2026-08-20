<?php

namespace HiEvents\Listeners\Waitlist;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class ResolveWaitlistEntryOnOrderCompletedListener
{
    public function __construct(
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    public function handle(OrderStatusChangedEvent $event): void
    {
        $order = $event->order;

        if ($order->getStatus() !== OrderStatus::COMPLETED->name) {
            return;
        }

        $this->resolveByOrderId($order->getId());
    }

    private function resolveByOrderId(int $orderId): void
    {
        $this->databaseManager->transaction(function () use ($orderId) {
            $entries = $this->waitlistEntryRepository->findWhere([
                'order_id' => $orderId,
                ['status', 'in', [WaitlistEntryStatus::OFFERED->name]],
            ]);

            foreach ($entries as $entry) {
                $this->markAsPurchased($entry);
            }
        });
    }

    private function markAsPurchased(WaitlistEntryDomainObject $entry): void
    {
        $this->waitlistEntryRepository->updateWhere(
            attributes: [
                'status' => WaitlistEntryStatus::PURCHASED->name,
                'purchased_at' => Carbon::now()->toDateTimeString(),
            ],
            where: [
                'id' => $entry->getId(),
                'status' => WaitlistEntryStatus::OFFERED->name,
            ],
        );
    }
}
