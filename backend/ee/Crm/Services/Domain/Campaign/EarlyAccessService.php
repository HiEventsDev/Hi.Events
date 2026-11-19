<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Domain\PromoCode\CreatePromoCodeService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\ProductEvent;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EarlyAccessService
{
    private const CODE_PREFIX = 'early-';

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CreatePromoCodeService $createPromoCodeService,
        private readonly DomainEventDispatcherService $domainEventDispatcher,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @param  int[]  $productIds
     *
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    public function createAccessCode(int $organizerId, int $eventId, array $productIds): PromoCodeDomainObject
    {
        $products = $this->findOfferableTickets($organizerId, $eventId, $productIds);

        $this->hide($products);
        $hiddenProductIds = $products->map(static fn (ProductDomainObject $product) => $product->getId())->values()->all();

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createPromoCodeService->createPromoCode(
                    (new PromoCodeDomainObject)
                        ->setEventId($eventId)
                        ->setCode(self::CODE_PREFIX.Str::lower(Str::random(8)))
                        ->setDiscountType(PromoCodeDiscountTypeEnum::NONE->name)
                        ->setDiscount(0.0)
                        ->setApplicableProductIds($hiddenProductIds)
                        ->setDiscountAppliesTo(PromoCodeDiscountAppliesToEnum::EACH_PRODUCT->name),
                );
            } catch (ResourceConflictException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw new CampaignNotSendableException($exception->getMessage());
                }
            }
        }
    }

    /**
     * @param  int[]  $productIds
     * @return Collection<int, ProductDomainObject>
     *
     * @throws CampaignNotSendableException
     */
    public function findOfferableTickets(int $organizerId, int $eventId, array $productIds): Collection
    {
        $event = $this->eventRepository->findFirstWhere([
            EventDomainObjectAbstract::ID => $eventId,
            EventDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($event === null) {
            throw new CampaignNotSendableException(__('The event linked to this campaign no longer exists.'));
        }

        if ($event->getStatus() !== EventStatus::LIVE->name) {
            throw new CampaignNotSendableException(__('Publish the event before sending people an early access link.'));
        }

        $products = $this->findTickets($eventId, $productIds);

        if ($products->isEmpty()) {
            throw new CampaignNotSendableException(__('The early access tickets for this campaign were deleted. Choose tickets again.'));
        }

        return $products;
    }

    /**
     * @param  int[]  $productIds
     * @return Collection<int, ProductDomainObject>
     */
    public function findTickets(int $eventId, array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return $this->productRepository->findWhereIn(ProductDomainObjectAbstract::ID, $productIds, [
            ProductDomainObjectAbstract::EVENT_ID => $eventId,
        ]);
    }

    /**
     * @param  int[]  $productIds
     */
    public function makePublic(int $eventId, array $productIds): void
    {
        $this->databaseManager->transaction(function () use ($eventId, $productIds): void {
            foreach ($this->findTickets($eventId, $productIds) as $product) {
                if (! $product->getIsHiddenWithoutPromoCode()) {
                    continue;
                }

                $this->productRepository->updateFromArray($product->getId(), [
                    ProductDomainObjectAbstract::IS_HIDDEN_WITHOUT_PROMO_CODE => false,
                ]);
                $this->dispatchUpdatedAfterCommit($product->getId());
            }
        });
    }

    /**
     * @param  Collection<int, ProductDomainObject>  $products
     */
    private function hide(Collection $products): void
    {
        foreach ($products as $product) {
            if ($product->getIsHiddenWithoutPromoCode()) {
                continue;
            }

            $this->productRepository->updateFromArray($product->getId(), [
                ProductDomainObjectAbstract::IS_HIDDEN_WITHOUT_PROMO_CODE => true,
            ]);
            $this->dispatchUpdatedAfterCommit($product->getId());
        }
    }

    private function dispatchUpdatedAfterCommit(int $productId): void
    {
        $this->databaseManager->afterCommit(fn () => $this->domainEventDispatcher->dispatch(new ProductEvent(
            type: DomainEventType::PRODUCT_UPDATED,
            productId: $productId,
        )));
    }
}
