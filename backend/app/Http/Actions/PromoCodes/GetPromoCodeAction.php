<?php

namespace HiEvents\Http\Actions\PromoCodes;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use HiEvents\Resources\PromoCode\PromoCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetPromoCodeAction extends BaseAction
{
    private PromoCodeRepositoryInterface $promoCodeRepository;

    public function __construct(PromoCodeRepositoryInterface $promoCodeRepository)
    {
        $this->promoCodeRepository = $promoCodeRepository;
    }

    public function __invoke(Request $request, int $eventId, int $promoCodeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $codes = $this->promoCodeRepository->findById($promoCodeId);

        return $this->resourceResponse(PromoCodeResource::class, $codes);
    }
}
