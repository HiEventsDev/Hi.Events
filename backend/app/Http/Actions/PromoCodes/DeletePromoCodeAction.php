<?php

namespace TicketKitten\Http\Actions\PromoCodes;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use TicketKitten\DomainObjects\EventDomainObject;
use TicketKitten\Http\Actions\BaseAction;
use TicketKitten\Repository\Interfaces\PromoCodeRepositoryInterface;

class DeletePromoCodeAction extends BaseAction
{
    private PromoCodeRepositoryInterface $promoCodeRepository;

    public function __construct(PromoCodeRepositoryInterface $promoCodeRepository)
    {
        $this->promoCodeRepository = $promoCodeRepository;
    }

    public function __invoke(Request $request, int $eventId, int $promoCodeId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->promoCodeRepository->deleteById($promoCodeId);

        return $this->noContentResponse();
    }
}
