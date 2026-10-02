<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Services\Application\Handlers;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventSeatMapBandProductDomainObject;
use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSeatMapBandProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\Enterprise\Seating\Exceptions\SeatMapChangeConflictException;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapBandProductRepositoryInterface;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductLinkDTO;
use HiEvents\Enterprise\Seating\Services\Application\Handlers\DTO\BandProductsDTO;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapGuard;
use HiEvents\Enterprise\Seating\Services\Domain\EventSeatMapLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatingEventLockService;
use HiEvents\Enterprise\Seating\Services\Domain\SeatMapIndex;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderItemRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class UpdateEventSeatMapBandProductsHandler
{
    public function __construct(
        private readonly EventSeatMapBandProductRepositoryInterface $bandProductRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EventSeatMapLookupService $lookupService,
        private readonly SeatClaimRepositoryInterface $seatClaimRepository,
        private readonly EventSeatMapGuard $guard,
        private readonly DatabaseManager $databaseManager,
        private readonly SeatingEventLockService $seatingEventLock,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly OrderItemRepositoryInterface $orderItemRepository,
    ) {}

    /**
     * @param  Collection<BandProductsDTO>  $bandProducts
     *
     * @throws ResourceNotFoundException
     * @throws SeatMapChangeConflictException
     * @throws Throwable
     */
    public function handle(int $eventId, Collection $bandProducts): EventSeatMapDomainObject
    {
        $this->databaseManager->transaction(function () use ($eventId, $bandProducts) {
            $this->seatingEventLock->lock($eventId);

            $eventSeatMap = $this->lookupService->getForEvent($eventId);

            $this->assertBandsExist($bandProducts, $this->lookupService->indexFor($eventId));
            $this->assertProductsAreLinkable($eventId, $bandProducts);
            $this->assertNewlySeatedProductsHaveNoUnseatedSales($eventId, $bandProducts);

            $this->guard->assertClaimedLinksPreserved(
                $this->seatClaimRepository->findLiveSeatsForEvent($eventId),
                $bandProducts->mapWithKeys(fn (BandProductsDTO $band) => [$band->band_key => $this->productIds($band)])->all(),
            );

            $this->bandProductRepository->deleteWhere([
                EventSeatMapBandProductDomainObjectAbstract::EVENT_SEAT_MAP_ID => $eventSeatMap->getId(),
            ]);

            foreach ($bandProducts as $band) {
                foreach ($band->products->unique('product_id') as $link) {
                    $this->bandProductRepository->create([
                        EventSeatMapBandProductDomainObjectAbstract::EVENT_SEAT_MAP_ID => $eventSeatMap->getId(),
                        EventSeatMapBandProductDomainObjectAbstract::BAND_KEY => $band->band_key,
                        EventSeatMapBandProductDomainObjectAbstract::PRODUCT_ID => $link->product_id,
                        EventSeatMapBandProductDomainObjectAbstract::PRICE_ADJUSTMENT => $link->price_adjustment,
                    ]);
                }
            }

            $this->lookupService->forget($eventId);
            $this->seatedProductLookup->forget();
        });

        return $this->lookupService->getForEvent($eventId);
    }

    /**
     * @throws SeatMapChangeConflictException
     */
    private function assertBandsExist(Collection $bandProducts, SeatMapIndex $index): void
    {
        $unknown = $bandProducts->pluck('band_key')->diff($index->bandKeys());

        if ($unknown->isNotEmpty()) {
            throw new SeatMapChangeConflictException(
                __('These bands are not on the seat map: :bands', ['bands' => $unknown->implode(', ')])
            );
        }
    }

    /**
     * @throws SeatMapChangeConflictException
     */
    private function assertProductsAreLinkable(int $eventId, Collection $bandProducts): void
    {
        $productIds = $bandProducts->flatMap(fn (BandProductsDTO $band) => $this->productIds($band))->unique()->values();
        if ($productIds->isEmpty()) {
            return;
        }

        $products = $this->productRepository->findWhereIn(ProductDomainObjectAbstract::ID, $productIds->all());

        $linkable = $products->filter(fn (ProductDomainObject $product) => $product->getEventId() === $eventId
            && $product->getProductType() === ProductType::TICKET->name
            && $product->getType() !== ProductPriceType::DONATION->name);

        if ($linkable->count() !== $productIds->count()) {
            throw new SeatMapChangeConflictException(
                __('Only this event\'s tickets can be linked to seats, and donation tickets cannot be seated')
            );
        }

        $this->assertAdjustmentsAreChargeable($products, $bandProducts);
    }

    /**
     * @param  Collection<int, ProductDomainObject>  $products
     *
     * @throws SeatMapChangeConflictException
     */
    private function assertAdjustmentsAreChargeable(Collection $products, Collection $bandProducts): void
    {
        $freeProductIds = $products
            ->filter(fn (ProductDomainObject $product) => $product->getType() === ProductPriceType::FREE->name)
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->all();

        $adjustedFree = $bandProducts
            ->flatMap(fn (BandProductsDTO $band) => $band->products->all())
            ->filter(fn (BandProductLinkDTO $link) => $link->price_adjustment !== 0
                && in_array($link->product_id, $freeProductIds, true));

        if ($adjustedFree->isNotEmpty()) {
            throw new SeatMapChangeConflictException(
                __('Free tickets cannot have a price band adjustment')
            );
        }
    }

    /**
     * @throws SeatMapChangeConflictException
     */
    private function assertNewlySeatedProductsHaveNoUnseatedSales(int $eventId, Collection $bandProducts): void
    {
        $alreadySeated = $this->seatedProductLookup->linksForEvent($eventId)
            ->map(fn (EventSeatMapBandProductDomainObject $link) => $link->getProductId())
            ->all();

        $newlySeated = $bandProducts
            ->flatMap(fn (BandProductsDTO $band) => $this->productIds($band))
            ->unique()
            ->diff($alreadySeated)
            ->values()
            ->all();

        if ($newlySeated === []) {
            return;
        }

        $withUnseatedTickets = $this->attendeeRepository->findWhereIn(
            AttendeeDomainObjectAbstract::PRODUCT_ID,
            $newlySeated,
            [
                [AttendeeDomainObjectAbstract::STATUS, '!=', AttendeeStatus::CANCELLED->name],
                [AttendeeDomainObjectAbstract::SEAT_UID, 'null', null],
            ],
            [AttendeeDomainObjectAbstract::PRODUCT_ID],
        )->map(fn (AttendeeDomainObject $attendee) => $attendee->getProductId());

        $blocked = $withUnseatedTickets
            ->merge($this->orderItemRepository->getProductIdsInLiveReservations($newlySeated))
            ->unique()
            ->values();

        if ($blocked->isEmpty()) {
            return;
        }

        $titles = $this->productRepository->findWhereIn(ProductDomainObjectAbstract::ID, $blocked->all())
            ->map(fn (ProductDomainObject $product) => $product->getTitle())
            ->implode(', ');

        throw new SeatMapChangeConflictException(
            __('These tickets already have orders without seats, so they cannot be linked to seats: :tickets', ['tickets' => $titles])
        );
    }

    /**
     * @return int[]
     */
    private function productIds(BandProductsDTO $band): array
    {
        return $band->products->map(fn (BandProductLinkDTO $link) => $link->product_id)->all();
    }
}
