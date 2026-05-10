<?php

namespace HiEvents\Services\Domain\Order;

use Exception;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\EventOccurrence\OccurrencePurchaseEligibilityService;
use HiEvents\Services\Domain\Product\AvailableProductQuantitiesFetchService;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesDTO;
use HiEvents\Services\Domain\Product\DTO\AvailableProductQuantitiesResponseDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderCreateRequestValidationService
{
    private AvailableProductQuantitiesResponseDTO $availableProductQuantities;

    public function __construct(
        readonly private ProductRepositoryInterface $productRepository,
        readonly private PromoCodeRepositoryInterface $promoCodeRepository,
        readonly private EventRepositoryInterface $eventRepository,
        readonly private EventOccurrenceRepositoryInterface $occurrenceRepository,
        readonly private AvailableProductQuantitiesFetchService $fetchAvailableProductQuantitiesService,
        readonly private OccurrencePurchaseEligibilityService $occurrenceEligibilityService,
    ) {}

    /**
     * @throws ValidationException
     * @throws Exception
     */
    public function validateRequestData(int $eventId, array $data = []): array
    {
        $event = $this->eventRepository->findById($eventId);
        $data = $this->normalizeOccurrenceIds($event, $data);

        $this->validateTypes($data);
        $this->validatePromoCode($eventId, $data);
        $this->validateProductSelection($data);
        $this->validateOccurrence($eventId, $data);

        // Event-wide snapshot is still needed for `validateOverallCapacity`
        // (event-level capacity assignments).
        $this->availableProductQuantities = $this->fetchAvailableProductQuantitiesService
            ->getAvailableProductQuantities(
                $event->getId(),
                ignoreCache: true,
            );

        $this->validateOverallCapacity($event, $data);

        // Re-fetch availability per occurrence so the product-quantity check
        // matches what `CreateOrderHandler::validateProductAvailability` enforces
        // — previously this used only the event-wide snapshot, letting the
        // validator wave through orders the handler would later reject (causing
        // confusing two-step failures during checkout).
        $this->validateProductDetailsPerOccurrence($event, $data);

        return $data;
    }

    private function normalizeOccurrenceIds(EventDomainObject $event, array $data): array
    {
        if ($event->isRecurring() || empty($data['products']) || ! is_array($data['products'])) {
            return $data;
        }

        $missingOccurrenceId = collect($data['products'])
            ->contains(fn ($product): bool => is_array($product) && empty($product['event_occurrence_id']));

        if (! $missingOccurrenceId) {
            return $data;
        }

        $occurrence = $this->getSingleEventOccurrence($event->getId());
        if ($occurrence === null) {
            return $data;
        }

        $data['products'] = collect($data['products'])
            ->map(function ($product) use ($occurrence) {
                if (! is_array($product)) {
                    return $product;
                }

                if (empty($product['event_occurrence_id'])) {
                    $product['event_occurrence_id'] = $occurrence->getId();
                }

                return $product;
            })
            ->all();

        return $data;
    }

    private function getSingleEventOccurrence(int $eventId): ?EventOccurrenceDomainObject
    {
        return $this->occurrenceRepository
            ->findWhere(
                where: [
                    EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
                ],
                orderAndDirections: [
                    new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, 'asc'),
                ],
            )
            ->first();
    }

    /**
     * Walk each occurrence's product group and validate against availability
     * scoped to that occurrence. For non-recurring events there's only one
     * occurrence, so this is a single iteration with the same per-occurrence
     * data the order handler uses.
     */
    private function validateProductDetailsPerOccurrence(EventDomainObject $event, array $data): void
    {
        $eventWideAvailability = $this->availableProductQuantities;
        $productsByOccurrence = collect($data['products'])->groupBy('event_occurrence_id');

        try {
            foreach ($productsByOccurrence as $occurrenceId => $products) {
                $this->availableProductQuantities = $this->fetchAvailableProductQuantitiesService
                    ->getAvailableProductQuantities(
                        $event->getId(),
                        ignoreCache: true,
                        eventOccurrenceId: $occurrenceId !== null && $occurrenceId !== ''
                            ? (int) $occurrenceId
                            : null,
                    );

                foreach ($products as $productAndQuantities) {
                    $allProducts = $this->getProducts(['products' => [$productAndQuantities]]);
                    $productIndex = collect($data['products'])->search(
                        fn ($p) => $p === $productAndQuantities,
                    );
                    $this->validateSingleProductDetails(
                        $event,
                        is_int($productIndex) ? $productIndex : 0,
                        $productAndQuantities,
                        $allProducts,
                    );
                }
            }
        } finally {
            // Restore so any subsequent caller sees the event-wide snapshot.
            $this->availableProductQuantities = $eventWideAvailability;
        }
    }

    /**
     * @throws ValidationException
     */
    private function validatePromoCode(int $eventId, array $data): void
    {
        if (isset($data['promo_code'])) {
            $promoCode = $this->promoCodeRepository->findFirstWhere([
                PromoCodeDomainObjectAbstract::CODE => strtolower(trim($data['promo_code'])),
                PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

            if (! $promoCode) {
                throw ValidationException::withMessages([
                    'promo_code' => __('This promo code is invalid'),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateTypes(array $data): void
    {
        $validator = Validator::make($data, [
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer',
            'products.*.event_occurrence_id' => 'required|integer',
            'products.*.quantities' => 'required|array',
            // `min:0` blocks the mixed-tier exploit: without it, a single
            // request with one tier at +2 and another at -1 sums to a positive
            // selection but persists a negative order_item that distorts
            // totals/stock and survives downstream availability checks
            // (validateProductPricesQuantity only guards `quantity > available`,
            // and `-1 > N` is false).
            'products.*.quantities.*.quantity' => 'required|integer|min:0',
            'products.*.quantities.*.price_id' => 'required|integer',
            'products.*.quantities.*.price' => 'numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateProductSelection(array $data): void
    {
        $productData = collect($data['products']);
        if ($productData->isEmpty() || $productData->sum(fn ($product) => collect($product['quantities'])->sum('quantity')) === 0) {
            throw ValidationException::withMessages([
                'products' => __('You haven\'t selected any products'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateOccurrence(int $eventId, array $data): void
    {
        $productsByOccurrence = collect($data['products'])->groupBy('event_occurrence_id');

        foreach ($productsByOccurrence as $occurrenceId => $products) {
            if ($occurrenceId === null || $occurrenceId === '') {
                throw ValidationException::withMessages([
                    'event_occurrence_id' => __('An event occurrence must be specified'),
                ]);
            }

            $totalQuantityRequested = (int) $products
                ->sum(fn ($product) => collect($product['quantities'])->sum('quantity'));

            $this->occurrenceEligibilityService->assertOccurrencePurchasable(
                eventId: $eventId,
                occurrenceId: (int) $occurrenceId,
                additionalQuantity: $totalQuantityRequested,
            );

            $productIds = $products->pluck('product_id')->map(fn ($id) => (int) $id)->all();
            $this->occurrenceEligibilityService->assertProductsVisibleOnOccurrence(
                (int) $occurrenceId,
                $productIds,
            );
        }
    }

    /**
     * @throws Exception
     */
    private function getProducts(array $data): Collection
    {
        $productIds = collect($data['products'])->pluck('product_id');

        return $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findWhereIn('id', $productIds->toArray());
    }

    /**
     * @throws ValidationException
     */
    private function validateSingleProductDetails(EventDomainObject $event, int $productIndex, array $productAndQuantities, $products): void
    {
        $productId = $productAndQuantities['product_id'];
        $totalQuantity = collect($productAndQuantities['quantities'])->sum('quantity');

        if ($totalQuantity === 0) {
            return;
        }

        /** @var ProductDomainObject $product */
        $product = $products->filter(fn ($t) => $t->getId() === $productId)->first();
        if (! $product) {
            throw new NotFoundHttpException(sprintf('Product ID %d not found', $productId));
        }

        $this->validateProductEvent(
            event: $event,
            productId: $productId,
            product: $product
        );

        $this->validateProductQuantity(
            productIndex: $productIndex,
            productAndQuantities: $productAndQuantities,
            product: $product
        );

        $this->validateProductTypeAndPrice(
            event: $event,
            productIndex: $productIndex,
            productAndQuantities: $productAndQuantities,
            product: $product
        );

        $this->validateSoldOutProducts(
            productId: $productId,
            productIndex: $productIndex,
            product: $product
        );

        $this->validatePriceIdAndQuantity(
            productIndex: $productIndex,
            productAndQuantities: $productAndQuantities,
            product: $product
        );
    }

    /**
     * @throws ValidationException
     */
    private function validateProductQuantity(int $productIndex, array $productAndQuantities, ProductDomainObject $product): void
    {
        $totalQuantity = collect($productAndQuantities['quantities'])->sum('quantity');
        $maxPerOrder = (int) $product->getMaxPerOrder() ?: 100;

        $capacityMaximum = $this->availableProductQuantities
            ->productQuantities
            ->where('product_id', $product->getId())
            ->map(fn (AvailableProductQuantitiesDTO $price) => $price->capacities)
            ->flatten()
            ->min(fn (CapacityAssignmentDomainObject $capacity) => $capacity->getCapacity());

        $productAvailableQuantity = $this->availableProductQuantities
            ->productQuantities
            ->first(fn (AvailableProductQuantitiesDTO $price) => $price->product_id === $product->getId())
            ->quantity_available;

        // if there are fewer products available than the configured minimum, we allow less than the minimum to be purchased
        $minPerOrder = min((int) $product->getMinPerOrder() ?: 1,
            $capacityMaximum ?: $maxPerOrder,
            $productAvailableQuantity ?: $maxPerOrder);

        $this->validateProductPricesQuantity(
            quantities: $productAndQuantities['quantities'],
            product: $product,
            productIndex: $productIndex
        );

        if ($totalQuantity > $maxPerOrder) {
            throw ValidationException::withMessages([
                "products.$productIndex" => __('The maximum number of products available for :products is :max', [
                    'max' => $maxPerOrder,
                    'product' => $product->getTitle(),
                ]),
            ]);
        }

        if ($totalQuantity < $minPerOrder) {
            throw ValidationException::withMessages([
                "products.$productIndex" => __('You must order at least :min products for :product', [
                    'min' => $minPerOrder,
                    'product' => $product->getTitle(),
                ]),
            ]);
        }
    }

    private function validateProductEvent(EventDomainObject $event, int $productId, ProductDomainObject $product): void
    {
        if ($product->getEventId() !== $event->getId()) {
            throw new NotFoundHttpException(sprintf('Product ID %d not found for event ID %d', $productId, $event->getId()));
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateProductTypeAndPrice(
        EventDomainObject $event,
        int $productIndex,
        array $productAndQuantities,
        ProductDomainObject $product
    ): void {
        if ($product->getType() === ProductPriceType::DONATION->name) {
            $price = $productAndQuantities['quantities'][0]['price'] ?? 0;
            if ($price < $product->getPrice()) {
                $formattedPrice = Currency::format($product->getPrice(), $event->getCurrency());
                throw ValidationException::withMessages([
                    "products.$productIndex.quantities.0.price" => __('The minimum amount is :price', ['price' => $formattedPrice]),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateSoldOutProducts(int $productId, int $productIndex, ProductDomainObject $product): void
    {
        if ($product->isSoldOut()) {
            throw ValidationException::withMessages([
                "products.$productIndex" => __('The product :product is sold out', [
                    'id' => $productId,
                    'product' => $product->getTitle(),
                ]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validatePriceIdAndQuantity(int $productIndex, array $productAndQuantities, ProductDomainObject $product): void
    {
        $errors = [];

        foreach ($productAndQuantities['quantities'] as $quantityIndex => $quantityData) {
            $priceId = $quantityData['price_id'] ?? null;
            $quantity = $quantityData['quantity'] ?? null;

            if ($priceId === null || $quantity === null) {
                $missingField = $priceId === null ? 'price_id' : 'quantity';
                $errors["products.$productIndex.quantities.$quantityIndex.$missingField"] = __(':field must be specified', [
                    'field' => ucfirst($missingField),
                ]);
            }

            $validPriceIds = $product->getProductPrices()?->map(fn (ProductPriceDomainObject $price) => $price->getId());
            if (! in_array($priceId, $validPriceIds->toArray(), true)) {
                $errors["products.$productIndex.quantities.$quantityIndex.price_id"] = __('Invalid price ID');
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateProductPricesQuantity(array $quantities, ProductDomainObject $product, int $productIndex): void
    {
        foreach ($quantities as $productQuantity) {
            if ($productQuantity['quantity'] === 0) {
                continue;
            }

            $numberAvailable = $this->availableProductQuantities
                ->productQuantities
                ->where('product_id', $product->getId())
                ->where('price_id', $productQuantity['price_id'])
                ->first()?->quantity_available;

            /** @var ProductPriceDomainObject $productPrice */
            $productPrice = $product->getProductPrices()
                ?->first(fn (ProductPriceDomainObject $price) => $price->getId() === $productQuantity['price_id']);

            if ($productQuantity['quantity'] > $numberAvailable) {
                if ($numberAvailable === 0) {
                    throw ValidationException::withMessages([
                        "products.$productIndex" => __('The product :product is sold out', [
                            'product' => $product->getTitle().($productPrice->getLabel() ? ' - '.$productPrice->getLabel() : ''),
                        ]),
                    ]);
                }

                throw ValidationException::withMessages([
                    "products.$productIndex" => __('The maximum number of products available for :product is :max', [
                        'max' => $numberAvailable,
                        'product' => $product->getTitle().($productPrice->getLabel() ? ' - '.$productPrice->getLabel() : ''),
                    ]),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateOverallCapacity(EventDomainObject $event, array $data): void
    {
        // Capacity assignments are deliberately not enforced for recurring
        // events — capacity is modelled per-occurrence on the recurrence side
        // and the assignment itself spans every session, which makes a single
        // shared total meaningless for a series. Per-occurrence capacity is
        // still validated in OccurrencePurchaseEligibilityService.
        if ($event->isRecurring()) {
            return;
        }

        foreach ($this->availableProductQuantities->capacities as $capacity) {
            if ($capacity->getProducts() === null) {
                continue;
            }

            $productIds = $capacity->getProducts()->map(fn (ProductDomainObject $product) => $product->getId());
            $totalQuantity = collect($data['products'])
                ->filter(fn ($product) => in_array($product['product_id'], $productIds->toArray(), true))
                ->sum(fn ($product) => collect($product['quantities'])->sum('quantity'));

            $reservedProductQuantities = $capacity->getProducts()
                ->map(fn (ProductDomainObject $product) => $this
                    ->availableProductQuantities
                    ->productQuantities
                    ->where('product_id', $product->getId())
                    ->sum('quantity_reserved')
                )
                ->sum();

            if ($totalQuantity > ($capacity->getAvailableCapacity() - $reservedProductQuantities)) {
                if ($capacity->getAvailableCapacity() - $reservedProductQuantities <= 0) {
                    throw ValidationException::withMessages([
                        'products' => __('Sorry, these products are sold out'),
                    ]);
                }

                throw ValidationException::withMessages([
                    'products' => __('The maximum number of products available is :max', [
                        'max' => $capacity->getAvailableCapacity(),
                    ]),
                ]);
            }
        }
    }
}
