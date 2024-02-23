<?php

namespace TicketKitten\Http\Actions\PromoCodes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\DomainObjects\PromoCodeDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Http\DataTransferObjects\QueryParamsDTO;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;
use TicketKitten\Resources\PromoCode\PromoCodeResource;

class GetPromoCodesAction extends BaseAction
{
    private PromoCodeRepositoryInterface $promoCodeRepository;

    public function __construct(PromoCodeRepositoryInterface $promoCodeRepository)
    {
        $this->promoCodeRepository = $promoCodeRepository;
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $codes = $this->promoCodeRepository->findByEventId($eventId, QueryParamsDTO::fromArray($request->query->all()));

        return $this->filterableResourceResponse(
            resource: PromoCodeResource::class,
            data: $codes,
            domainObject: PromoCodeDomainObject::class
        );
    }
}
