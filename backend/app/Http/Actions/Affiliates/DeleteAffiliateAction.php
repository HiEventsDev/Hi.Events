<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Affiliates;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Services\Application\Handlers\Affiliate\DeleteAffiliateHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeleteAffiliateAction extends BaseAction
{
    public function __construct(
        private readonly DeleteAffiliateHandler $deleteAffiliateHandler
    )
    {
    }

    public function __invoke(Request $request, int $eventId, int $affiliateId): Response
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $this->deleteAffiliateHandler->handle($affiliateId, $eventId);

        return $this->deletedResponse();
    }
}
