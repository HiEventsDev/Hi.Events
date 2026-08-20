<?php

namespace HiEvents\Services\Domain\Order;

use Exception;
use HiEvents\DomainObjects\CapacityAssignmentDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Enums\ProductType;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\PromoCodeDomainObject;
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
use HiEvents\Services\Domain\Product\ProductPriceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderCreateRequestValidationService
{
    private AvailableProductQuantitiesResponseDTO $availableProductQuantities;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly AvailableProductQuantitiesFetchService $fetchAvailableProductQuantitiesService,
        private readonly OccurrencePurchaseEligibilityService $occurrenceEligibilityService,
        private readonly ProductPriceService $productPriceService,
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
        $promoCode = $this->validatePromoCode($eventId, $data);
        $this->validateProductSelection($data);
        $this->validateAddonProducts($data);
        $this->validateOccurrence($eventId, $data);

        $this->availableProductQuantities = $this->fetchAvailableProductQuantitiesService
            ->getAvailableProductQuantities(
                $event->getId(),
                ignoreCache: true,
            );

        $this->validateOverallCapacity($event, $data);

        $this->validateProductDetailsPerOccurrence($event, $data, $promoCode);

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

    private function validateProductDetailsPerOccurrence(EventDomainObject $event, array $data, ?PromoCodeDomainObject $promoCode): void
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

                $occurrenceRequestedQuantities = $this->sumRequestedQuantities($products->all());

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
                        $promoCode,
                        $occurrenceRequestedQuantities,
                    );
                }
            }
        } finally {
            $this->availableProductQuantities = $eventWideAvailability;
        }

        if ($productsByOccurrence->count() > 1) {
            $this->validateRequestedQuantitiesAcrossOccurrences($data);
        }
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function sumRequestedQuantities(array $productLines): array
    {
        $requestedQuantities = [];
        foreach ($productLines as $line) {
            foreach ($line['quantities'] as $quantity) {
                if ($quantity['quantity'] <= 0) {
                    continue;
                }

                $requestedQuantities[$line['product_id']][$quantity['price_id']] =
                    ($requestedQuantities[$line['product_id']][$quantity['price_id']] ?? 0) + $quantity['quantity'];
            }
        }

        return $requestedQuantities;
    }

    /**
     * @throws ValidationException
     */
    private function validateRequestedQuantitiesAcrossOccurrences(array $data): void
    {
        $requestedQuantities = $this->sumRequestedQuantities($data['products']);
        $products = $this->getProducts($data);
        $productLines = collect($data['products']);

        foreach ($requestedQuantities as $productId => $priceQuantities) {
            $product = $products->first(fn (ProductDomainObject $p) => $p->getId() === $productId);
            $productIndex = $productLines->search(fn ($line) => (int) $line['product_id'] === $productId);

            $this->validateProductPricesQuantity(
                quantities: collect($priceQuantities)
                    ->map(fn ($quantity, $priceId) => ['price_id' => $priceId, 'quantity' => $quantity])
                    ->values()
                    ->all(),
                product: $product,
                productIndex: is_int($productIndex) ? $productIndex : 0,
                requestedQuantities: $requestedQuantities,
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function validatePromoCode(int $eventId, array $data): ?PromoCodeDomainObject
    {
        if (! isset($data['promo_code'])) {
            return null;
        }

        $promoCode = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::CODE => strtolower(trim($data['promo_code'])),
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if (! $promoCode) {
            throw ValidationException::withMessages([
                'promo_code' => __('This promo code is invalid'),
            ]);
        }

        return $promoCode->isValid() ? $promoCode : null;
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
    private function validateAddonProducts(array $data): void
    {
        $productLines = collect($data['products']);

        $requestedQuantities = $productLines
            ->groupBy(fn ($line) => (int) $line['product_id'])
            ->map(fn ($lines) => $lines->sum(fn ($line) => collect($line['quantities'])->sum('quantity')));

        $selectedProductIds = $requestedQuantities->filter(fn ($quantity) => $quantity > 0)->keys();

        $selectedAddonOnlyProducts = $this->getProducts($data)
            ->filter(fn (ProductDomainObject $product) => $product->getIsAddonOnly()
                && $selectedProductIds->contains($product->getId()));

        if ($selectedAddonOnlyProducts->isEmpty()) {
            return;
        }

        $parentIdsByAddon = $this->productRepository->findParentProductIds(
            $selectedAddonOnlyProducts->map(fn (ProductDomainObject $product) => $product->getId())->values()->all(),
        );

        foreach ($selectedAddonOnlyProducts as $addon) {
            $hasSelectedParent = collect($parentIdsByAddon->get($addon->getId(), []))
                ->contains(fn ($parentId) => $selectedProductIds->contains($parentId));

            if (! $hasSelectedParent) {
                $productIndex = $productLines->search(fn ($line) => (int) $line['product_id'] === $addon->getId());
                throw ValidationException::withMessages([
                    'products.'.(is_int($productIndex) ? $productIndex : 0) => __(':product is an add-on and can only be purchased with the product it belongs to', [
                        'product' => $addon->getTitle(),
                    ]),
                ]);
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateOccurrence(int $eventId, array $data): void
    {
        $productsByOccurrence = collect($data['products'])->groupBy('event_occurrence_id');
        $ticketProductIds = $this->getTicketProductIds($data);

        foreach ($productsByOccurrence as $occurrenceId => $products) {
            if ($occurrenceId === null || $occurrenceId === '') {
                throw ValidationException::withMessages([
                    'event_occurrence_id' => __('An event occurrence must be specified'),
                ]);
            }

            $totalQuantityRequested = (int) $products
                ->filter(fn ($product) => in_array((int) $product['product_id'], $ticketProductIds, true))
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
     * @return int[]
     */
    private function getTicketProductIds(array $data): array
    {
        return $this->getProducts($data)
            ->filter(fn (ProductDomainObject $product) => $product->getProductType() === ProductType::TICKET->name)
            ->map(fn (ProductDomainObject $product) => $product->getId())
            ->values()
            ->all();
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
    private function validateSingleProductDetails(EventDomainObject $event, int $productIndex, array $productAndQuantities, $products, ?PromoCodeDomainObject $promoCode, array $requestedQuantities): void
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

        $this->validateProductVisibility(
            product: $product,
            promoCode: $promoCode
        );

        $this->validateProductSaleWindow(
            productIndex: $productIndex,
            product: $product
        );

        $this->validateProductQuantity(
            productIndex: $productIndex,
            productAndQuantities: $productAndQuantities,
            product: $product,
            requestedQuantities: $requestedQuantities,
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
     * @throws NotFoundHttpException
     */
    private function validateProductVisibility(ProductDomainObject $product, ?PromoCodeDomainObject $promoCode): void
    {
        if ($product->getIsHidden()) {
            throw new NotFoundHttpException(sprintf('Product ID %d not found', $product->getId()));
        }

        if ($product->getIsHiddenWithoutPromoCode()
            && ! ($promoCode && $promoCode->appliesToProduct($product))) {
            throw new NotFoundHttpException(sprintf('Product ID %d not found', $product->getId()));
        }
    }

    /**
     * @throws ValidationException
     */
    private function validateProductQuantity(int $productIndex, array $productAndQuantities, ProductDomainObject $product, array $requestedQuantities): void
    {
        $totalQuantity = isset($requestedQuantities[$product->getId()])
            ? array_sum($requestedQuantities[$product->getId()])
            : (int) collect($productAndQuantities['quantities'])->sum('quantity');
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

        $minPerOrder = min((int) $product->getMinPerOrder() ?: 1,
            $capacityMaximum ?: $maxPerOrder,
            $productAvailableQuantity ?: $maxPerOrder);

        $this->validateProductPricesQuantity(
            quantities: $productAndQuantities['quantities'],
            product: $product,
            productIndex: $productIndex,
            requestedQuantities: $requestedQuantities,
        );

        if ($totalQuantity > $maxPerOrder) {
            throw ValidationException::withMessages([
                "products.$productIndex" => __('The maximum number of products available for :products is :max', [
                    'max' => $maxPerOrder,
                    'products' => $product->getTitle(),
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
    private function validateProductSaleWindow(int $productIndex, ProductDomainObject $product): void
    {
        if ($product->isBeforeSaleStartDate()) {
            throw ValidationException::withMessages([
                "products.$productIndex" => __(':product is not yet on sale', [
                    'product' => $product->getTitle(),
                ]),
            ]);
        }

        if ($product->isAfterSaleEndDate()) {
            throw ValidationException::withMessages([
                "products.$productIndex" => __('Sales for :product have ended', [
                    'product' => $product->getTitle(),
                ]),
            ]);
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
            $occurrenceId = $productAndQuantities['event_occurrence_id'] ?? null;
            $minimumPrice = $this->productPriceService->getDonationMinimumPrice(
                product: $product,
                priceId: (int) $productAndQuantities['quantities'][0]['price_id'],
                eventOccurrenceId: $occurrenceId ? (int) $occurrenceId : null,
            );
            if ($price < $minimumPrice) {
                $formattedPrice = Currency::format($minimumPrice, $event->getCurrency());
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

            $productPrices = $product->getProductPrices();
            $validPriceIds = $productPrices?->map(fn (ProductPriceDomainObject $price) => $price->getId());
            if (! in_array($priceId, $validPriceIds->toArray(), true)) {
                $errors["products.$productIndex.quantities.$quantityIndex.price_id"] = __('Invalid price ID');

                continue;
            }

            $selectedPrice = $productPrices?->first(fn (ProductPriceDomainObject $price) => $price->getId() === $priceId);
            if ((int) $quantity > 0 && $this->isPriceUnavailable($selectedPrice)) {
                $errors["products.$productIndex.quantities.$quantityIndex.price_id"] = __('Invalid price ID');
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isPriceUnavailable(?ProductPriceDomainObject $price): bool
    {
        if ($price === null) {
            return true;
        }

        return $price->getIsHidden()
            || $price->isBeforeSaleStartDate()
            || $price->isAfterSaleEndDate();
    }

    /**
     * @throws ValidationException
     */
    private function validateProductPricesQuantity(array $quantities, ProductDomainObject $product, int $productIndex, array $requestedQuantities): void
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

            $requestedQuantity = $requestedQuantities[$product->getId()][$productQuantity['price_id']]
                ?? $productQuantity['quantity'];

            if ($requestedQuantity > $numberAvailable) {
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

            if ($totalQuantity === 0) {
                continue;
            }

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
