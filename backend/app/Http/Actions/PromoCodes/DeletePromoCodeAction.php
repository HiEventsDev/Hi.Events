<?php

namespace HiEvents\Http\Actions\PromoCodes;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;

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
