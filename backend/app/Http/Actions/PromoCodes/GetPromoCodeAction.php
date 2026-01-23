<?php

namespace HiEvents\Http\Actions\PromoCodes;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
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

    /**
     * @throws ResourceNotFoundException
     */
    public function __invoke(Request $request, int $eventId, int $promoCodeId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $promoCode = $this->promoCodeRepository->findFirstWhere([
            PromoCodeDomainObjectAbstract::ID => $promoCodeId,
            PromoCodeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($promoCode === null) {
            throw new ResourceNotFoundException(__('Promo code not found'));
        }

        return $this->resourceResponse(PromoCodeResource::class, $promoCode);
    }
}
