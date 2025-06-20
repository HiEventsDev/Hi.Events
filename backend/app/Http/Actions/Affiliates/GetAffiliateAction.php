<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Affiliates;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Resources\Affiliate\AffiliateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetAffiliateAction extends BaseAction
{
    public function __construct(private readonly AffiliateRepositoryInterface $affiliateRepository)
    {
    }

    public function __invoke(Request $request, int $eventId, int $affiliateId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $affiliate = $this->affiliateRepository->findFirstWhere([
            'event_id' => $eventId,
            'id' => $affiliateId,
        ]);

        if (!$affiliate) {
            throw new NotFoundHttpException(__('Affiliate not found'));
        }

        return $this->resourceResponse(
            resource: AffiliateResource::class,
            data: $affiliate
        );
    }
}
