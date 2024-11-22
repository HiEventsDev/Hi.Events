<?php

namespace HiEvents\Services\Application\Handlers\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\ProductPriceType;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Exceptions\NoTicketsAvailableException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\ProductRepositoryInterface;
use HiEvents\Services\Application\Handlers\Attendee\DTO\EditAttendeeDTO;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditAttendeeHandler
{
    public function __construct(
        private readonly AttendeeRepositoryInterface  $attendeeRepository,
        private readonly ProductRepositoryInterface   $productRepository,
        private readonly ProductQuantityUpdateService $productQuantityService,
        private readonly DatabaseManager              $databaseManager,
    )
    {
    }

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(EditAttendeeDTO $editAttendeeDTO): AttendeeDomainObject
    {
        return $this->databaseManager->transaction(function () use ($editAttendeeDTO) {
            $this->validateProductId($editAttendeeDTO);

            $attendee = $this->getAttendee($editAttendeeDTO);

            $this->adjustProductQuantities($attendee, $editAttendeeDTO);

            return $this->updateAttendee($editAttendeeDTO);
        });
    }

    private function adjustProductQuantities(AttendeeDomainObject $attendee, EditAttendeeDTO $editAttendeeDTO): void
    {
        if ($attendee->getProductPriceId() !== $editAttendeeDTO->product_price_id) {
            $this->productQuantityService->decreaseQuantitySold($editAttendeeDTO->product_price_id);
            $this->productQuantityService->increaseQuantitySold($attendee->getProductPriceId());
        }
    }

    private function updateAttendee(EditAttendeeDTO $editAttendeeDTO): AttendeeDomainObject
    {
        return $this->attendeeRepository->updateByIdWhere($editAttendeeDTO->attendee_id, [
            'first_name' => $editAttendeeDTO->first_name,
            'last_name' => $editAttendeeDTO->last_name,
            'email' => $editAttendeeDTO->email,
            'product_id' => $editAttendeeDTO->product_id,
        ], [
            'event_id' => $editAttendeeDTO->event_id,
        ]);
    }

    /**
     * @throws ValidationException
     * @throws NoTicketsAvailableException
     */
    private function validateProductId(EditAttendeeDTO $editAttendeeDTO): void
    {
        $product = $this->productRepository
            ->loadRelation(ProductPriceDomainObject::class)
            ->findFirstWhere([
                ProductDomainObjectAbstract::ID => $editAttendeeDTO->product_id,
            ]);

        if ($product->getEventId() !== $editAttendeeDTO->event_id) {
            throw ValidationException::withMessages([
                'product_id' => __('Product ID is not valid'),
            ]);
        }

        $availableQuantity = $this->productRepository->getQuantityRemainingForProductPrice(
            productId: $editAttendeeDTO->product_id,
            productPriceId: $product->getType() === ProductPriceType::TIERED->name
                ? $editAttendeeDTO->product_price_id
                : $product->getProductPrices()->first()->getId(),
        );

        if ($availableQuantity <= 0) {
            throw new NoTicketsAvailableException(
                __('There are no products available. If you would like to assign this product to this attendee, please adjust the product\'s available quantity.')
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private function getAttendee(EditAttendeeDTO $editAttendeeDTO): AttendeeDomainObject
    {
        $attendee = $this->attendeeRepository->findFirstWhere([
            AttendeeDomainObjectAbstract::EVENT_ID => $editAttendeeDTO->event_id,
            AttendeeDomainObjectAbstract::ID => $editAttendeeDTO->attendee_id,
        ]);

        if ($attendee === null) {
            throw ValidationException::withMessages([
                'attendee_id' => __('Attendee ID is not valid'),
            ]);
        }

        return $attendee;
    }
}
