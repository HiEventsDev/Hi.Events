<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactConsentEventResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\GetContactConsentHistoryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetContactConsentHistoryAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactConsentHistoryHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(
            ContactConsentEventResource::class,
            $this->handler->handle($organizerId, $contactId, $this->getPaginationQueryParams($request)),
        );
    }
}
