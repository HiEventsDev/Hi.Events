<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Http\Actions\Contacts;

use HiEvents\Enterprise\Crm\Http\Actions\BaseCrmAction;
use HiEvents\Enterprise\Crm\Resources\ContactActivityResource;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Contact\GetContactActivitiesHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetContactActivitiesAction extends BaseCrmAction
{
    public function __construct(
        private readonly GetContactActivitiesHandler $handler,
    ) {}

    public function __invoke(Request $request, int $organizerId, int $contactId): JsonResponse
    {
        $this->authorizeCrm($organizerId);

        return $this->resourceResponse(
            ContactActivityResource::class,
            $this->handler->handle($organizerId, $contactId, $this->getPaginationQueryParams($request)),
        );
    }
}
