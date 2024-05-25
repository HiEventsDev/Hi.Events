<?php

namespace HiEvents\Http\Actions\PromoCodes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;

class GetPromoCodePublic extends BaseAction
{
    private PromoCodeRepositoryInterface $promoCodeRepository;

    public function __construct(PromoCodeRepositoryInterface $promoCodeRepository)
    {
        $this->promoCodeRepository = $promoCodeRepository;
    }

    public function __invoke(int $eventId, string $promoCode, Request $request): JsonResponse
    {
        // intentionally not returning a 404
        $promoCode = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::CODE => strtolower(trim($promoCode)),
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        return $this->jsonResponse([
            'valid' => $promoCode !== null && $promoCode->isValid(),
        ]);
    }
}
