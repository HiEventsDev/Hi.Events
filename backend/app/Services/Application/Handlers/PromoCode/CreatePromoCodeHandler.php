<?php

namespace HiEvents\Services\Application\Handlers\PromoCode;

use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Services\Application\Handlers\PromoCode\DTO\UpsertPromoCodeDTO;
use HiEvents\Services\Domain\Product\Exception\UnrecognizedProductIdException;
use HiEvents\Services\Domain\PromoCode\CreatePromoCodeService;

readonly class CreatePromoCodeHandler
{
    public function __construct(
        private CreatePromoCodeService $createPromoCodeService,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     * @throws UnrecognizedProductIdException
     */
    public function handle(int $eventId, UpsertPromoCodeDTO $promoCodeDTO): PromoCodeDomainObject
    {
        return $this->createPromoCodeService->createPromoCode(
            (new PromoCodeDomainObject())
                ->setEventId($eventId)
                ->setCode($promoCodeDTO->code)
                ->setDiscountType($promoCodeDTO->discount_type->name)
                ->setDiscount($promoCodeDTO->discount)
                ->setExpiryDate($promoCodeDTO->expiry_date)
                ->setMaxAllowedUsages($promoCodeDTO->max_allowed_usages)
                ->setApplicableProductIds($promoCodeDTO->applicable_product_ids)
        );
    }
}
