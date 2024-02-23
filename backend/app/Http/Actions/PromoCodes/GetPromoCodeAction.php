<?php

namespace TicketKitten\Http\Actions\PromoCodes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Resources\PromoCode\PromoCodeResource;

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
