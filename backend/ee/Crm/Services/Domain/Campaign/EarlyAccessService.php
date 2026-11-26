<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Campaign;

use HiEvents\DomainObjects\CrmCampaignDomainObject;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountAppliesToEnum;
use HiEvents\DomainObjects\Enums\PromoCodeDiscountTypeEnum;
use HiEvents\DomainObjects\Generated\CrmCampaignDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Enterprise\Crm\Exceptions\CampaignNotSendableException;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmCampaignRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Campaign\DTO\EarlyAccessGrantDTO;
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
        private readonly CrmCampaignRepositoryInterface $campaignRepository,
    ) {}

    /**
     * @param  int[]  $productIds
     *
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    public function createAccessCode(int $organizerId, int $eventId, array $productIds): EarlyAccessGrantDTO
    {
        $products = $this->findOfferableTickets($organizerId, $eventId, $productIds);
        $productIdsOnOffer = $products->map(static fn (ProductDomainObject $product) => $product->getId())->values()->all();
        $heldByOthers = array_intersect($productIdsOnOffer, $this->heldByOtherCampaigns($eventId, null));
        $hiddenNow = $this->hide($products);

        return new EarlyAccessGrantDTO(
            promo_code_id: $this->createPromoCode($eventId, $productIdsOnOffer)->getId(),
            hidden_product_ids: array_values(array_unique([...$hiddenNow, ...$heldByOthers])),
        );
    }

    /**
     * @param  int[]  $productIds
     *
     * @throws CampaignNotSendableException
     * @throws UnrecognizedProductIdException
     */
    private function createPromoCode(int $eventId, array $productIds): PromoCodeDomainObject
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createPromoCodeService->createPromoCode(
                    (new PromoCodeDomainObject)
                        ->setEventId($eventId)
                        ->setCode(self::CODE_PREFIX.Str::lower(Str::random(8)))
                        ->setDiscountType(PromoCodeDiscountTypeEnum::NONE->name)
                        ->setDiscount(0.0)
                        ->setApplicableProductIds($productIds)
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

    public function makePublic(CrmCampaignDomainObject $campaign): void
    {
        $this->databaseManager->transaction(function () use ($campaign): void {
            foreach ($this->findTickets((int) $campaign->getEventId(), $this->releasableProductIds($campaign)) as $product) {
                if (! $product->getIsHiddenWithoutPromoCode()) {
                    continue;
                }

                $this->productRepository->updateFromArray($product->getId(), [
                    ProductDomainObjectAbstract::IS_HIDDEN_WITHOUT_PROMO_CODE => false,
                ]);
                $this->dispatchUpdatedAfterCommit($product->getId());
            }

            $this->campaignRepository->updateWhere(
                attributes: [CrmCampaignDomainObjectAbstract::EARLY_ACCESS_HIDDEN_PRODUCT_IDS => json_encode([])],
                where: [CrmCampaignDomainObjectAbstract::ID => $campaign->getId()],
            );
            $campaign->setEarlyAccessHiddenProductIds([]);
        });
    }

    /**
     * @return int[]
     */
    public function releasableProductIds(CrmCampaignDomainObject $campaign): array
    {
        $hiddenProductIds = $campaign->earlyAccessHiddenProductIds();

        if ($hiddenProductIds === [] || $campaign->getEventId() === null) {
            return [];
        }

        return array_values(array_diff($hiddenProductIds, $this->heldByOtherCampaigns($campaign->getEventId(), $campaign->getId())));
    }

    /**
     * @return int[]
     */
    private function heldByOtherCampaigns(int $eventId, ?int $exceptCampaignId): array
    {
        return $this->campaignRepository
            ->findWhere(array_filter([
                CrmCampaignDomainObjectAbstract::EVENT_ID => $eventId,
                [CrmCampaignDomainObjectAbstract::EARLY_ACCESS_HIDDEN_PRODUCT_IDS, 'not null', null],
                $exceptCampaignId === null ? null : [CrmCampaignDomainObjectAbstract::ID, '!=', $exceptCampaignId],
            ]))
            ->flatMap(static fn (CrmCampaignDomainObject $other) => $other->earlyAccessHiddenProductIds())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ProductDomainObject>  $products
     * @return int[]
     */
    private function hide(Collection $products): array
    {
        $hiddenProductIds = [];

        foreach ($products as $product) {
            if ($product->getIsHiddenWithoutPromoCode()) {
                continue;
            }

            $this->productRepository->updateFromArray($product->getId(), [
                ProductDomainObjectAbstract::IS_HIDDEN_WITHOUT_PROMO_CODE => true,
            ]);
            $this->dispatchUpdatedAfterCommit($product->getId());
            $hiddenProductIds[] = $product->getId();
        }

        return $hiddenProductIds;
    }

    private function dispatchUpdatedAfterCommit(int $productId): void
    {
        $this->databaseManager->afterCommit(fn () => $this->domainEventDispatcher->dispatch(new ProductEvent(
            type: DomainEventType::PRODUCT_UPDATED,
            productId: $productId,
        )));
    }
}
