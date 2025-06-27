<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Affiliates;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Status\AffiliateStatus;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\Request\Affiliate\UpdateAffiliateRequest;
use HiEvents\Resources\Affiliate\AffiliateResource;
use HiEvents\Services\Application\Handlers\Affiliate\DTO\UpsertAffiliateDTO;
use HiEvents\Services\Application\Handlers\Affiliate\UpdateAffiliateHandler;
use Illuminate\Http\JsonResponse;

class UpdateAffiliateAction extends BaseAction
{
    public function __construct(
        private readonly UpdateAffiliateHandler $updateAffiliateHandler
    )
    {
    }

    public function __invoke(UpdateAffiliateRequest $request, int $eventId, int $affiliateId): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $affiliate = $this->updateAffiliateHandler->handle(
            $affiliateId,
            $eventId,
            new UpsertAffiliateDTO(
                name: $request->input('name'),
                code: '', // Code cannot be updated
                email: $request->input('email'),
                status: AffiliateStatus::from($request->input('status')),
            )
        );

        return $this->resourceResponse(
            resource: AffiliateResource::class,
            data: $affiliate
        );
    }
}
