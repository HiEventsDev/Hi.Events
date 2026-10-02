<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain;

use HiEvents\Constants;
use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\BoxOfficeOrderItemDTO;
use HiEvents\Enterprise\BoxOffice\Services\Application\Handlers\Public\DTO\CreateBoxOfficeOrderDTO;
use HiEvents\Enterprise\Seating\Exceptions\SeatSelectionInvalidException;
use HiEvents\Enterprise\Seating\Services\Domain\SeatSelectionValidationService;
use HiEvents\Services\Application\Handlers\Order\DTO\ProductOrderDetailsDTO;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BoxOfficeCartValidationService
{
    private const DEFAULT_MAX_PER_ORDER = 100;

    public function __construct(
        private readonly BoxOfficeProductCatalogueService $catalogueService,
        private readonly AvailableProductQuantitiesFetchService $availableProductQuantitiesFetchService,
        private readonly OccurrencePurchaseEligibilityService $occurrencePurchaseEligibilityService,
        private readonly SeatSelectionValidationService $seatSelectionValidationService,
    ) {}

    /**
     * @return Collection<ProductOrderDetailsDTO>
     *
     * @throws ValidationException
     * @throws SeatSelectionInvalidException
     */
    public function validate(CreateBoxOfficeOrderDTO $data, ?int $occurrenceId): Collection
    {
        $boxOffice = $data->box_office;
        $sellable = $this->catalogueService->getSellableProducts($boxOffice, $occurrenceId);

        $this->assertPermissions($boxOffice, $data);

        $orderDetails = collect();
        $ticketQuantity = 0;
        $quantityByProduct = [];

        foreach ($data->items as $index => $item) {
            $product = $this->resolveProduct($sellable, $item, $index);
            $quantityByProduct[$product->getId()] = ($quantityByProduct[$product->getId()] ?? 0) + $item->quantity;
            $this->assertQuantityWithinLimit($product, $quantityByProduct[$product->getId()], $index);

            if ($product->getProductType() === ProductType::TICKET->name) {
                $ticketQuantity += $item->quantity;
            }

            $orderDetails->push(new ProductOrderDetailsDTO(
                product_id: $product->getId(),
                quantities: collect([new OrderProductPriceDTO(
                    quantity: $item->quantity,
                    price_id: $item->product_price_id,
                    price: $item->override_price,
                    seat_uids: $item->seat_uids,
                )]),
                event_occurrence_id: $occurrenceId,
            ));
        }

        if ($occurrenceId !== null) {
            $this->occurrencePurchaseEligibilityService->assertOccurrencePurchasable(
                eventId: $boxOffice->getEventId(),
                occurrenceId: $occurrenceId,
                additionalQuantity: $ticketQuantity,
                allowPastOccurrence: true,
            );
            $this->occurrencePurchaseEligibilityService->assertProductsVisibleOnOccurrence(
                $occurrenceId,
                $orderDetails->map(fn (ProductOrderDetailsDTO $detail) => $detail->product_id)->all(),
            );
        }

        if ($occurrenceId === null && $data->items->contains(fn (BoxOfficeOrderItemDTO $item) => $item->seat_uids !== [])) {
            throw ValidationException::withMessages(['items' => __('Choose a date to sell seats for')]);
        }

        $this->seatSelectionValidationService->validate($boxOffice->getEventId(), $data->items->map(fn (BoxOfficeOrderItemDTO $item) => [
            'product_id' => $item->product_id,
            'event_occurrence_id' => $occurrenceId,
            'quantities' => [['quantity' => $item->quantity, 'seat_uids' => $item->seat_uids]],
        ])->all(), enforceSelectionRules: false);

        $availability = $this->availableProductQuantitiesFetchService->getAvailableProductQuantities(
            eventId: $boxOffice->getEventId(),
            ignoreCache: true,
            eventOccurrenceId: $occurrenceId,
            allowPastOccurrence: true,
        );
        $this->assertQuantitiesAvailable($availability, $data->items);
        $this->assertSharedPoolsHaveRoom($availability, $data->items, $quantityByProduct);

        return $orderDetails;
    }

    private function assertPermissions(BoxOfficeDomainObject $boxOffice, CreateBoxOfficeOrderDTO $data): void
    {
        if (! $boxOffice->getAllowDiscounts() && $data->discount !== null) {
            throw ValidationException::withMessages([
                'discount' => __('Discounts are not allowed at this box office'),
            ]);
        }

        if ($boxOffice->getAllowPriceOverride()) {
            return;
        }

        foreach ($data->items as $index => $item) {
            if ($item->override_price !== null) {
                throw ValidationException::withMessages([
                    "items.$index.override_price" => __('Price overrides are not allowed at this box office'),
                ]);
            }
        }
    }

    private function resolveProduct(Collection $sellable, BoxOfficeOrderItemDTO $item, int $index): ProductDomainObject
    {
        /** @var ProductDomainObject|null $product */
        $product = $sellable->first(fn (ProductDomainObject $product) => $product->getId() === $item->product_id);

        if ($product === null) {
            throw ValidationException::withMessages([
                "items.$index.product_id" => __('This product cannot be sold at this box office'),
            ]);
        }

        $priceExists = $product->getProductPrices()
            ?->contains(fn (ProductPriceDomainObject $price) => $price->getId() === $item->product_price_id);

        if (! $priceExists) {
            throw ValidationException::withMessages([
                "items.$index.product_price_id" => __('Invalid price for this product'),
            ]);
        }

        return $product;
    }

    private function assertQuantityWithinLimit(ProductDomainObject $product, int $quantity, int $index): void
    {
        $maxPerOrder = $product->getMaxPerOrder() ?: self::DEFAULT_MAX_PER_ORDER;

        if ($quantity > $maxPerOrder) {
            throw ValidationException::withMessages([
                "items.$index.quantity" => __('You can sell at most :max of this product per order', ['max' => $maxPerOrder]),
            ]);
        }
    }

    /**
     * @param  Collection<BoxOfficeOrderItemDTO>  $items
     */
    private function assertQuantitiesAvailable(AvailableProductQuantitiesResponseDTO $availability, Collection $items): void
    {
        foreach ($items->groupBy(fn (BoxOfficeOrderItemDTO $item) => $item->product_price_id) as $priceId => $priceItems) {
            $requestedQuantity = $priceItems->sum(fn (BoxOfficeOrderItemDTO $item) => $item->quantity);
            $quantities = $availability->productQuantities->firstWhere('price_id', $priceId);
            $available = $quantities?->seats_available === null
                ? $quantities?->quantity_available ?? 0
                : $quantities->quantity_available_before_seats;

            if ($available !== Constants::INFINITE && $requestedQuantity > $available) {
                $this->throwNotEnoughAvailable($items->search(fn (BoxOfficeOrderItemDTO $item) => $item->product_price_id === $priceId), $available);
            }
        }
    }

    /**
     * @param  Collection<BoxOfficeOrderItemDTO>  $items
     * @param  array<int, int>  $quantityByProduct
     */
    private function assertSharedPoolsHaveRoom(AvailableProductQuantitiesResponseDTO $availability, Collection $items, array $quantityByProduct): void
    {
        $pool = $availability->firstOverflowingPool($quantityByProduct);

        if ($pool === null) {
            return;
        }

        $poolProductIds = $pool->getProducts()->map(fn (ProductDomainObject $product) => $product->getId())->all();

        $this->throwNotEnoughAvailable(
            $items->search(fn (BoxOfficeOrderItemDTO $item) => in_array($item->product_id, $poolProductIds, true)),
            $availability->remainingPoolCapacity($pool),
        );
    }

    private function throwNotEnoughAvailable(int $index, int $available): never
    {
        throw ValidationException::withMessages([
            "items.$index.quantity" => $available <= 0
                ? __('Sold out')
                : __('Only :count left', ['count' => $available]),
        ]);
    }
}
