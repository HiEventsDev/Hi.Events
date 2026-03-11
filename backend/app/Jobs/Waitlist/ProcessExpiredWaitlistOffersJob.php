<?php

namespace HiEvents\Jobs\Waitlist;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\Enums\CapacityChangeDirection;
use HiEvents\Events\CapacityChangedEvent;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessExpiredWaitlistOffersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        WaitlistEntryRepositoryInterface $repository,
        OrderRepositoryInterface         $orderRepository,
        ProductPriceRepositoryInterface  $productPriceRepository,
        DatabaseManager                  $databaseManager,
    ): void
    {
        $expiredEntries = $repository->findWhere([
            'status' => WaitlistEntryStatus::OFFERED->name,
            ['offer_expires_at', '<=', now()->toDateTimeString()],
            ['offer_expires_at', '!=', null],
        ]);

        foreach ($expiredEntries as $entry) {
            try {
                $databaseManager->transaction(function () use ($entry, $repository, $orderRepository) {
                    $lockedEntry = $repository->findByIdLocked($entry->getId());

                    if ($lockedEntry === null || $lockedEntry->getStatus() !== WaitlistEntryStatus::OFFERED->name) {
                        return;
                    }

                    if ($lockedEntry->getOrderId() !== null) {
                        $orderRepository->deleteWhere([
                            'id' => $lockedEntry->getOrderId(),
                            'status' => OrderStatus::RESERVED->name,
                        ]);
                    }

                    $repository->updateWhere(
                        attributes: [
                            'status' => WaitlistEntryStatus::OFFER_EXPIRED->name,
                            'offer_token' => null,
                            'offered_at' => null,
                            'offer_expires_at' => null,
                            'order_id' => null,
                        ],
                        where: ['id' => $entry->getId()],
                    );
                });

                $freshEntry = $repository->findById($entry->getId());

                if ($freshEntry->getStatus() !== WaitlistEntryStatus::OFFER_EXPIRED->name) {
                    continue;
                }

                SendWaitlistOfferExpiredEmailJob::dispatch($entry);

                $productPrice = $productPriceRepository->findById($entry->getProductPriceId());

                event(new CapacityChangedEvent(
                    eventId: $entry->getEventId(),
                    direction: CapacityChangeDirection::INCREASED,
                    productId: $productPrice->getProductId(),
                    productPriceId: $entry->getProductPriceId(),
                ));
            } catch (Throwable $e) {
                Log::error('Failed to process expired waitlist offer', [
                    'entry_id' => $entry->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
