<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Affiliates;

use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Resources\Affiliate\AffiliateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetAffiliatesAction extends BaseAction
{
    public function __construct(private readonly AffiliateRepositoryInterface $affiliateRepository)
    {
    }

    public function __invoke(Request $request, int $eventId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $affiliates = $this->affiliateRepository->findByEventId($eventId, QueryParamsDTO::fromArray($request->query->all()));

        return $this->filterableResourceResponse(
            resource: AffiliateResource::class,
            data: $affiliates,
            domainObject: AffiliateDomainObject::class
        );
    }
}
