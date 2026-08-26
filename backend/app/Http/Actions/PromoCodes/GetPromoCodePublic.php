<?php

namespace HiEvents\Http\Actions\PromoCodes;

use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Services\Domain\PromoCode\PromoCodeUsageValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPromoCodePublic extends BaseAction
{
    public function __construct(
        private readonly PromoCodeRepositoryInterface $promoCodeRepository,
        private readonly PromoCodeUsageValidationService $promoCodeUsageValidationService,
    ) {}

    public function __invoke(int $eventId, string $promoCode, Request $request): JsonResponse
    {
        $promoCode = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::CODE => strtolower(trim($promoCode)),
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        $isUsable = $this->promoCodeUsageValidationService->isPromoCodeUsable($promoCode);

        if (! $isUsable) {
            return $this->jsonResponse([
                'valid' => false,
            ]);
        }

        return $this->jsonResponse([
            'valid' => true,
            'discount' => $promoCode->getDiscount(),
            'discount_type' => $promoCode->getDiscountType(),
            'discount_applies_to' => $promoCode->getDiscountAppliesTo(),
            'applies_to_all_products' => empty($promoCode->getApplicableProductIds()),
        ]);
    }
}
